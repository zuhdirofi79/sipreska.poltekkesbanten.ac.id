<?php

namespace Tests\Feature\Sipreska;

use App\Enums\AttendanceWorkUnit;
use App\Models\Activity;
use App\Models\AttendanceForm;
use App\Models\AttendanceResponse;
use App\Support\AttendanceLetterhead;
use App\Support\SipreskaBranding;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class AttendanceLetterheadTest extends TestCase
{
    use SipreskaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSipreskaBasics();
    }

    private function openForm(Activity $activity): AttendanceForm
    {
        return AttendanceForm::query()->create([
            'activity_id' => $activity->id,
            'public_token' => bin2hex(random_bytes(32)),
            'is_open' => true,
            'signature_required' => false,
        ]);
    }

    private function createActivity(): Activity
    {
        return Activity::factory()->create([
            'unit_id' => $this->unitBySlug('d3-kebidanan')->id,
            'category_id' => $this->defaultCategory()->id,
            'title' => 'Workshop Letterhead',
            'location' => 'Aula Utama',
        ]);
    }

    private function letterheadUrl(): string
    {
        return asset('images/daftar-hadir-header.jpeg');
    }

    public function test_letterhead_asset_file_is_available(): void
    {
        $this->assertTrue(AttendanceLetterhead::exists());
        $this->assertFileExists(public_path('images/daftar-hadir-header.jpeg'));
    }

    public function test_public_attendance_form_renders_letterhead(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->get(route('attendance.public.show', ['public_token' => $form->public_token]))
            ->assertOk()
            ->assertSee($this->letterheadUrl(), false)
            ->assertSee('attendance-letterhead', false)
            ->assertSee('Form Daftar Hadir Kegiatan', false);
    }

    public function test_public_success_page_renders_letterhead(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->post(route('attendance.public.store', ['public_token' => $form->public_token]), [
            'name' => 'Peserta Letterhead',
            'nip' => '0',
            'work_unit' => AttendanceWorkUnit::Kebidanan->value,
        ])->assertRedirect(route('attendance.public.success', ['public_token' => $form->public_token]));

        $this->get(route('attendance.public.success', ['public_token' => $form->public_token]))
            ->assertOk()
            ->assertSee($this->letterheadUrl(), false)
            ->assertSee('attendance-letterhead', false);
    }

    public function test_print_page_renders_letterhead(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = $this->createActivity();
        $form = $this->openForm($activity);

        AttendanceResponse::query()->create([
            'attendance_form_id' => $form->id,
            'name' => 'Print Participant',
            'work_unit' => AttendanceWorkUnit::D3Kebidanan->value,
            'signature_disk' => 'sipreska',
            'signature_path' => 'sig.png',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.print', $activity))
            ->assertOk()
            ->assertSee('Daftar Hadir Kegiatan', false)
            ->assertSee('attendance-letterhead', false)
            ->assertSee('data:image/jpeg;base64,', false);
    }

    public function test_pdf_export_renders_letterhead(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = $this->createActivity();
        $form = $this->openForm($activity);

        AttendanceResponse::query()->create([
            'attendance_form_id' => $form->id,
            'name' => 'PDF Participant',
            'work_unit' => AttendanceWorkUnit::D3Kebidanan->value,
            'signature_disk' => 'sipreska',
            'signature_path' => 'sig.png',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.export-pdf', $activity))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_public_preview_uses_same_public_route_with_letterhead(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = $this->createActivity();
        $form = $this->openForm($activity);
        $publicUrl = route('attendance.public.show', ['public_token' => $form->public_token]);

        $this->actingAs($admin)
            ->get($publicUrl)
            ->assertOk()
            ->assertSee($this->letterheadUrl(), false)
            ->assertSee('Form Daftar Hadir Kegiatan', false);
    }

    public function test_small_logo_is_not_shown_on_public_form(): void
    {
        $form = $this->openForm($this->createActivity());

        $response = $this->get(route('attendance.public.show', ['public_token' => $form->public_token]));

        $response->assertOk();
        $response->assertDontSee('sipreska-logo-login', false);
        $response->assertDontSee(SipreskaBranding::logoUrl(), false);
    }

    public function test_small_logo_is_not_shown_on_success_page(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->post(route('attendance.public.store', ['public_token' => $form->public_token]), [
            'name' => 'No Logo Success',
            'nip' => '0',
            'work_unit' => AttendanceWorkUnit::Kebidanan->value,
        ]);

        $this->get(route('attendance.public.success', ['public_token' => $form->public_token]))
            ->assertOk()
            ->assertDontSee('sipreska-logo-login', false)
            ->assertDontSee(SipreskaBranding::logoUrl(), false);
    }

    public function test_letterhead_is_not_shown_on_dashboard(): void
    {
        $this->actingAs($this->superadmin())
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee($this->letterheadUrl(), false)
            ->assertDontSee('attendance-letterhead', false);
    }

    public function test_letterhead_is_not_shown_on_login_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee($this->letterheadUrl(), false)
            ->assertDontSee('attendance-letterhead', false);
    }

    public function test_source_does_not_contain_hostinger_file_manager_url(): void
    {
        $paths = [
            base_path('resources/views'),
            base_path('app'),
            base_path('config'),
        ];

        foreach ($paths as $path) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname()) ?: '';
                $this->assertStringNotContainsString('srv1863-files.hstgr.io', $contents, $file->getPathname());
            }
        }
    }

    public function test_missing_letterhead_file_does_not_cause_500_on_public_form(): void
    {
        config(['sipreska.attendance_letterhead_path' => 'images/missing-letterhead.jpeg']);

        $form = $this->openForm($this->createActivity());

        $this->get(route('attendance.public.show', ['public_token' => $form->public_token]))
            ->assertOk()
            ->assertSee('KEMENTERIAN KESEHATAN', false)
            ->assertSee('POLTEKKES KEMENKES BANTEN', false);
    }

    public function test_letterhead_image_uses_full_width_not_fixed_logo_height(): void
    {
        $css = file_get_contents(resource_path('css/sipreska.css')) ?: '';

        preg_match('/\.attendance-letterhead\s*\{([^}]+)\}/', $css, $matches);
        $letterheadRules = $matches[1] ?? '';

        $this->assertStringContainsString('width: 100%', $letterheadRules);
        $this->assertStringContainsString('height: auto', $letterheadRules);
        $this->assertStringContainsString('object-fit: contain', $letterheadRules);
        $this->assertStringNotContainsString('object-fit: cover', $letterheadRules);
    }

    public function test_public_page_layout_avoids_horizontal_overflow(): void
    {
        $css = file_get_contents(resource_path('css/sipreska.css')) ?: '';

        $this->assertStringContainsString('.public-attendance-page {', $css);
        $this->assertStringContainsString('overflow-x: hidden', $css);
    }

    public function test_view_cache_succeeds_with_letterhead_component(): void
    {
        Artisan::call('view:cache');
        $this->assertFileExists(storage_path('framework/views'));
        Artisan::call('view:clear');
    }
}
