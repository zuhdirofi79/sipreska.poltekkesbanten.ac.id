<?php

namespace Tests\Feature\Sipreska;

use App\Models\Activity;
use App\Models\Category;
use App\Models\MinutesDocument;
use App\Support\ActivityDocumentRoutes;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class ActivityDocumentRouteTest extends TestCase
{
    use SipreskaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSipreskaBasics();
    }

    public function test_edit_minutes_tab_does_not_return_500(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = Activity::factory()->create([
            'unit_id' => $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'minutes']))
            ->assertOk()
            ->assertSee('Notulensi')
            ->assertSee('Notulensi belum diunggah');
    }

    public function test_minutes_partial_renders_upload_form_when_empty(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = Activity::factory()->create([
            'unit_id' => $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'minutes']))
            ->assertOk()
            ->assertSee(route('activities.notulensi.store', $activity), false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('Unggah Notulensi');
    }

    public function test_minutes_partial_renders_existing_pdf_with_preview(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = Activity::factory()->create([
            'unit_id' => $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        MinutesDocument::query()->create([
            'activity_id' => $activity->id,
            'minutes_date' => '2026-07-10',
            'notulist_name' => 'Budi Santoso',
            'disk' => 'sipreska',
            'path' => "docs/notulensi/{$activity->id}/notulen.pdf",
            'original_name' => 'notulen.pdf',
            'mime_type' => 'application/pdf',
            'size' => 2048,
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'minutes']))
            ->assertOk()
            ->assertSee('notulen.pdf')
            ->assertSee('Budi Santoso')
            ->assertSee(route('activities.notulensi.view', $activity), false)
            ->assertSee('Pratinjau')
            ->assertSee(route('activities.notulensi.download', $activity), false)
            ->assertSee(route('activities.notulensi.destroy', $activity), false)
            ->assertSee('Ganti File Notulensi');
    }

    public function test_minutes_partial_renders_word_without_preview(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = Activity::factory()->create([
            'unit_id' => $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        MinutesDocument::query()->create([
            'activity_id' => $activity->id,
            'minutes_date' => '2026-07-10',
            'notulist_name' => 'Ani Wijaya',
            'disk' => 'sipreska',
            'path' => "docs/notulensi/{$activity->id}/notulen.docx",
            'original_name' => 'notulen.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => 4096,
            'uploaded_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'minutes']));

        $response->assertOk()
            ->assertSee('notulen.docx')
            ->assertSee(route('activities.notulensi.download', $activity), false)
            ->assertDontSee(route('activities.notulensi.view', $activity), false);
    }

    public function test_notulensi_routes_are_registered(): void
    {
        foreach ([
            'activities.notulensi.store',
            'activities.notulensi.view',
            'activities.notulensi.download',
            'activities.notulensi.destroy',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Route [{$routeName}] is not registered.");
        }

        $store = Route::getRoutes()->getByName('activities.notulensi.store');
        $this->assertNotNull($store);
        $this->assertContains('POST', $store->methods());

        $download = Route::getRoutes()->getByName('activities.notulensi.download');
        $this->assertNotNull($download);
        $this->assertContains('GET', $download->methods());

        $destroy = Route::getRoutes()->getByName('activities.notulensi.destroy');
        $this->assertNotNull($destroy);
        $this->assertContains('DELETE', $destroy->methods());
    }

    public function test_admin_unit_cannot_open_other_unit_minutes_tab(): void
    {
        $keperawatan = $this->unitBySlug('d3-keperawatan');
        $activity = Activity::factory()->create([
            'unit_id' => $keperawatan->id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        $admin = $this->adminUnit('d3-kebidanan');

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'minutes']))
            ->assertForbidden();
    }

    public function test_superadmin_can_open_minutes_tab_for_any_unit(): void
    {
        $keperawatan = $this->unitBySlug('d3-keperawatan');
        $activity = Activity::factory()->create([
            'unit_id' => $keperawatan->id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        $this->actingAs($this->superadmin())
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'minutes']))
            ->assertOk();
    }

    public function test_all_activity_edit_document_tabs_return_200_for_authorized_user(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = Activity::factory()->create([
            'unit_id' => $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        foreach (['invitation', 'attendance', 'minutes', 'documentation', 'publication'] as $tab) {
            $this->actingAs($admin)
                ->get(route('activities.edit', ['activity' => $activity, 'tab' => $tab]))
                ->assertOk("Tab [{$tab}] should return HTTP 200.");
        }
    }

    public function test_activity_blade_views_can_be_cached_without_parse_error(): void
    {
        Artisan::call('view:clear');

        $exitCode = Artisan::call('view:cache');

        $this->assertSame(0, $exitCode);

        Artisan::call('view:clear');
    }

    public function test_edit_invitation_tab_does_not_return_500(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = Activity::factory()->create([
            'unit_id' => $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'invitation']))
            ->assertOk()
            ->assertSee('Surat Undangan')
            ->assertSee('Belum diunggah');
    }

    public function test_invitation_partial_renders_upload_form(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = Activity::factory()->create([
            'unit_id' => $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'invitation']))
            ->assertOk()
            ->assertSee(route('activities.surat-undangan.store', $activity), false);
    }

    public function test_surat_undangan_store_route_is_registered(): void
    {
        $this->assertTrue(Route::has('activities.surat-undangan.store'));

        $route = Route::getRoutes()->getByName('activities.surat-undangan.store');
        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
    }

    public function test_all_blade_document_route_names_are_registered(): void
    {
        foreach (ActivityDocumentRoutes::BLADE_ROUTES as $routeName) {
            $this->assertTrue(
                Route::has($routeName),
                "Named route [{$routeName}] is not registered.",
            );
        }
    }

    public function test_surat_undangan_store_url_can_be_generated(): void
    {
        $activity = Activity::factory()->create([
            'unit_id' => $this->unitBySlug('d3-kebidanan')->id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        $url = route('activities.surat-undangan.store', $activity);

        $this->assertStringContainsString("/kegiatan/{$activity->id}/surat-undangan", $url);
    }

    public function test_document_routes_use_expected_http_methods(): void
    {
        $expectations = [
            'activities.surat-undangan.store' => ['POST'],
            'activities.surat-undangan.view' => ['GET', 'HEAD'],
            'activities.surat-undangan.download' => ['GET', 'HEAD'],
            'activities.surat-undangan.destroy' => ['DELETE'],
            'activities.notulensi.store' => ['POST'],
            'activities.dokumentasi.update' => ['PUT'],
            'activities.publikasi.destroy' => ['DELETE'],
        ];

        foreach ($expectations as $routeName => $methods) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Route [{$routeName}] not found.");

            foreach ($methods as $method) {
                $this->assertContains($method, $route->methods(), "Route [{$routeName}] should accept {$method}.");
            }
        }
    }

    public function test_admin_unit_cannot_open_other_unit_edit_tab(): void
    {
        $keperawatan = $this->unitBySlug('d3-keperawatan');
        $activity = Activity::factory()->create([
            'unit_id' => $keperawatan->id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        $admin = $this->adminUnit('d3-kebidanan');

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'invitation']))
            ->assertForbidden();
    }

    public function test_superadmin_can_open_edit_tab_for_any_unit(): void
    {
        $keperawatan = $this->unitBySlug('d3-keperawatan');
        $activity = Activity::factory()->create([
            'unit_id' => $keperawatan->id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        $this->actingAs($this->superadmin())
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'invitation']))
            ->assertOk();
    }

    public function test_route_cache_can_be_built_without_error(): void
    {
        Artisan::call('route:clear');

        $exitCode = Artisan::call('route:cache');

        $this->assertSame(0, $exitCode);
        $this->assertTrue(Route::has('activities.surat-undangan.store'));

        foreach (\App\Support\AttendancePublicRoutes::ALL as $name) {
            $this->assertTrue(Route::has($name), "Route [{$name}] should survive route:cache.");
        }

        Artisan::call('route:clear');
    }
}
