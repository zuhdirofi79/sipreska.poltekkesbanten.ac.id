<?php

namespace Tests\Feature\Sipreska;

use App\Enums\AttendanceWorkUnit;
use App\Models\Activity;
use App\Models\AttendanceForm;
use App\Models\AttendanceResponse;
use App\Support\AttendancePublicRoutes;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class AttendanceShortLinkTest extends TestCase
{
    use SipreskaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSipreskaBasics();
    }

    private function openForm(array $overrides = []): AttendanceForm
    {
        $activity = Activity::factory()->create([
            'unit_id' => $this->unitBySlug('d3-kebidanan')->id,
            'category_id' => $this->defaultCategory()->id,
            'title' => 'Workshop Short Link',
            'location' => 'Aula',
        ]);

        return AttendanceForm::query()->create(array_merge([
            'activity_id' => $activity->id,
            'public_token' => bin2hex(random_bytes(32)),
            'is_open' => true,
            'signature_required' => false,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Andi Wijaya',
            'nip' => '198001012020031001',
            'position' => 'Staff',
            'work_unit' => AttendanceWorkUnit::D3Kebidanan->value,
        ], $overrides);
    }

    public function test_new_form_automatically_gets_short_code(): void
    {
        $form = $this->openForm();

        $this->assertNotNull($form->short_code);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{6,12}$/', $form->short_code);
    }

    public function test_short_codes_are_unique(): void
    {
        $codes = [];

        for ($i = 0; $i < 5; $i++) {
            $form = $this->openForm(['public_token' => bin2hex(random_bytes(32))]);
            $codes[] = $form->short_code;
        }

        $this->assertSame(count($codes), count(array_unique($codes)));
    }

    public function test_long_public_url_still_opens_form(): void
    {
        $form = $this->openForm();

        $this->get(route(AttendancePublicRoutes::SHOW, ['public_token' => $form->public_token]))
            ->assertOk()
            ->assertSee('Form Daftar Hadir Kegiatan', false);
    }

    public function test_short_url_opens_same_form(): void
    {
        $form = $this->openForm();

        $this->get(route(AttendancePublicRoutes::SHORT_SHOW, ['short_code' => $form->short_code]))
            ->assertOk()
            ->assertSee('Workshop Short Link', false)
            ->assertSee('Form Daftar Hadir Kegiatan', false);
    }

    public function test_post_via_short_url_succeeds(): void
    {
        $form = $this->openForm();

        $this->post(route(AttendancePublicRoutes::SHORT_STORE, ['short_code' => $form->short_code]), $this->payload())
            ->assertRedirect(route(AttendancePublicRoutes::SUCCESS, ['public_token' => $form->public_token]));

        $this->assertDatabaseHas('attendance_responses', [
            'attendance_form_id' => $form->id,
            'name' => 'Andi Wijaya',
        ]);
    }

    public function test_post_via_long_url_still_succeeds(): void
    {
        $form = $this->openForm();

        $this->post(route(AttendancePublicRoutes::STORE, ['public_token' => $form->public_token]), $this->payload())
            ->assertRedirect(route(AttendancePublicRoutes::SUCCESS, ['public_token' => $form->public_token]));
    }

    public function test_unknown_short_code_returns_404(): void
    {
        $this->get('/a/ZZZZZZ')->assertNotFound();
        $this->post('/a/ZZZZZZ', $this->payload())->assertNotFound();
    }

    public function test_admin_tab_shows_short_url_not_long_token(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $form = $this->openForm();
        $shortUrl = AttendancePublicRoutes::showUrlForForm($form);

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $form->activity_id, 'tab' => 'attendance']))
            ->assertOk()
            ->assertSee($shortUrl, false)
            ->assertSee('/a/'.$form->short_code, false)
            ->assertDontSee('/absensi/'.$form->public_token, false)
            ->assertSee('Link pendek otomatis untuk QR dan share.', false);
    }

    public function test_qr_endpoint_encodes_short_url(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $form = $this->openForm();
        $shortUrl = AttendancePublicRoutes::showUrlForForm($form);

        $this->assertNotNull($shortUrl);
        $this->assertStringContainsString('/a/'.$form->short_code, $shortUrl);

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.qr', $form->activity_id))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_show_url_for_form_falls_back_to_long_url_without_short_code(): void
    {
        $form = $this->openForm();

        AttendanceForm::query()->whereKey($form->id)->update(['short_code' => null]);
        $form->refresh();

        $url = AttendancePublicRoutes::showUrlForForm($form);

        $this->assertSame(
            route(AttendancePublicRoutes::SHOW, ['public_token' => $form->public_token]),
            $url,
        );
    }

    public function test_backfill_command_fills_missing_short_codes(): void
    {
        $form = $this->openForm();

        AttendanceForm::query()->whereKey($form->id)->update(['short_code' => null]);
        $this->assertNull($form->fresh()->short_code);

        Artisan::call('sipreska:backfill-attendance-short-codes');

        $form->refresh();
        $this->assertNotNull($form->short_code);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{6,12}$/', $form->short_code);
    }

    public function test_short_routes_are_registered_and_cacheable(): void
    {
        foreach (AttendancePublicRoutes::SHORT_ALL as $name) {
            $this->assertTrue(Route::has($name), "Route [{$name}] should be registered.");
        }

        Artisan::call('route:cache');

        foreach (AttendancePublicRoutes::SHORT_ALL as $name) {
            $this->assertTrue(Route::has($name));
        }

        Artisan::call('route:clear');
    }

    public function test_duplicate_via_short_url_redirects_to_short_show(): void
    {
        $form = $this->openForm();

        $this->post(route(AttendancePublicRoutes::SHORT_STORE, ['short_code' => $form->short_code]), $this->payload());

        $this->post(route(AttendancePublicRoutes::SHORT_STORE, ['short_code' => $form->short_code]), $this->payload())
            ->assertRedirect(route(AttendancePublicRoutes::SHORT_SHOW, ['short_code' => $form->short_code]))
            ->assertSessionHas('attendance_duplicate', true);

        $this->assertSame(1, AttendanceResponse::query()->where('attendance_form_id', $form->id)->count());
    }
}
