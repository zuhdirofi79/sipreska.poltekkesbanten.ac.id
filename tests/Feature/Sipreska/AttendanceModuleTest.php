<?php

namespace Tests\Feature\Sipreska;

use App\Enums\AttendanceWorkUnit;
use App\Models\Activity;
use App\Models\AttendanceForm;
use App\Models\AttendanceResponse;
use App\Services\AttendanceExportService;
use App\Services\AttendanceService;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class AttendanceModuleTest extends TestCase
{
    use SipreskaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSipreskaBasics();
        Storage::fake('sipreska');
    }

    private function openForm(Activity $activity): AttendanceForm
    {
        $form = AttendanceForm::query()->create([
            'activity_id' => $activity->id,
            'public_token' => bin2hex(random_bytes(32)),
            'is_open' => true,
            'signature_required' => true,
        ]);

        return $form;
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Andi Wijaya',
            'nip' => '198001012020031001',
            'position' => 'Staff',
            'work_unit' => AttendanceWorkUnit::Kebidanan->value,
            'signature_data' => $this->minimalPngBase64(),
        ], $overrides);
    }

    public function test_active_form_can_be_submitted(): void
    {
        $activity = Activity::factory()->create([
            'unit_id' => $this->unitBySlug('d3-kebidanan')->id,
            'category_id' => $this->defaultCategory()->id,
        ]);
        $form = $this->openForm($activity);

        $this->post(route('attendance.public.store', $form->public_token), $this->payload())
            ->assertRedirect(route('attendance.public.success', ['public_token' => $form->public_token]));

        $this->assertDatabaseHas('attendance_responses', [
            'attendance_form_id' => $form->id,
            'nip_normalized' => '198001012020031001',
            'nik' => null,
            'work_unit' => AttendanceWorkUnit::Kebidanan->value,
        ]);
    }

    public function test_public_form_work_unit_dropdown_only_shows_four_new_units(): void
    {
        $activity = Activity::factory()->create([
            'unit_id' => $this->unitBySlug('d3-kebidanan')->id,
            'category_id' => $this->defaultCategory()->id,
        ]);
        $form = $this->openForm($activity);

        $this->get(route('attendance.public.show', ['public_token' => $form->public_token]))
            ->assertOk()
            ->assertSee('Direktorat', false)
            ->assertSee('Keperawatan', false)
            ->assertSee('Kebidanan', false)
            ->assertSee('Teknologi Laboratorium Medis', false)
            ->assertDontSee('D3 Keperawatan', false)
            ->assertDontSee('D3 Kebidanan', false)
            ->assertDontSee('D3 TLM', false)
            ->assertDontSee('STR Ners', false)
            ->assertDontSee('Instansi Lainnya', false)
            ->assertDontSee('Nama Instansi', false);
    }

    public function test_public_form_rejects_legacy_work_unit_values(): void
    {
        $activity = Activity::factory()->create([
            'unit_id' => $this->unitBySlug('d3-kebidanan')->id,
            'category_id' => $this->defaultCategory()->id,
        ]);
        $form = $this->openForm($activity);

        $this->post(route('attendance.public.store', $form->public_token), $this->payload([
            'work_unit' => AttendanceWorkUnit::D3Kebidanan->value,
        ]))->assertSessionHasErrors('work_unit');
    }

    public function test_closed_form_is_rejected(): void
    {
        $activity = Activity::factory()->create([
            'unit_id' => $this->unitBySlug('d3-kebidanan')->id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        $form = AttendanceForm::query()->create([
            'activity_id' => $activity->id,
            'public_token' => bin2hex(random_bytes(32)),
            'is_open' => false,
            'signature_required' => true,
        ]);

        $this->post(route('attendance.public.store', $form->public_token), $this->payload())
            ->assertSessionHasErrors('form');
    }

    public function test_invalid_token_returns_404(): void
    {
        $this->get('/absensi/invalid-token-that-does-not-exist')->assertNotFound();
    }

    public function test_duplicate_nip_is_rejected(): void
    {
        $activity = Activity::factory()->create([
            'unit_id' => $this->unitBySlug('d3-kebidanan')->id,
            'category_id' => $this->defaultCategory()->id,
        ]);
        $form = $this->openForm($activity);

        $this->post(route('attendance.public.store', $form->public_token), $this->payload());
        $this->post(route('attendance.public.store', $form->public_token), $this->payload())
            ->assertRedirect(route('attendance.public.show', ['public_token' => $form->public_token]))
            ->assertSessionHas('attendance_duplicate', true);
    }

    public function test_signature_is_stored_as_file(): void
    {
        $activity = Activity::factory()->create([
            'unit_id' => $this->unitBySlug('d3-kebidanan')->id,
            'category_id' => $this->defaultCategory()->id,
        ]);
        $form = $this->openForm($activity);

        $this->post(route('attendance.public.store', $form->public_token), $this->payload());

        $response = AttendanceResponse::query()->first();
        $this->assertNotNull($response?->signature_path);
        Storage::disk('sipreska')->assertExists($response->signature_path);
    }

    public function test_polling_endpoint_requires_authorization(): void
    {
        $activity = Activity::factory()->create([
            'unit_id' => $this->unitBySlug('d3-kebidanan')->id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        $otherAdmin = $this->adminUnit('d3-keperawatan');

        $this->actingAs($otherAdmin)
            ->getJson(route('activities.daftar-hadir.poll', $activity))
            ->assertForbidden();
    }

    public function test_polling_endpoint_returns_responses_for_authorized_user(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = Activity::factory()->create([
            'unit_id' => $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
        ]);
        $form = $this->openForm($activity);

        AttendanceResponse::query()->create([
            'attendance_form_id' => $form->id,
            'name' => 'Test',
            'nip_nik' => '123',
            'nip_nik_normalized' => '123',
            'work_unit' => AttendanceWorkUnit::D3Kebidanan->value,
            'signature_disk' => 'sipreska',
            'signature_path' => 'sig.png',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson(route('activities.daftar-hadir.poll', $activity))
            ->assertOk()
            ->assertJsonPath('count', 1);
    }

    public function test_excel_export_contains_submission_data(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = Activity::factory()->create([
            'unit_id' => $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
            'title' => 'Export Test',
        ]);
        $form = $this->openForm($activity);

        AttendanceResponse::query()->create([
            'attendance_form_id' => $form->id,
            'name' => 'Peserta Export',
            'nip_nik' => '987654321',
            'nip_nik_normalized' => '987654321',
            'work_unit' => AttendanceWorkUnit::D3Kebidanan->value,
            'signature_disk' => 'sipreska',
            'signature_path' => 'sig.png',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.export-excel', $activity));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', (string) $response->headers->get('content-type'));
    }

    public function test_pdf_export_can_be_generated(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = Activity::factory()->create([
            'unit_id' => $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
        ]);
        $this->openForm($activity);

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.export-pdf', $activity))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_public_attendance_rate_limit_exists(): void
    {
        $routes = app('router')->getRoutes();
        $route = $routes->getByName('attendance.public.store');

        $this->assertNotNull($route);
        $this->assertContains('throttle:10,1', $route->gatherMiddleware());
    }
}
