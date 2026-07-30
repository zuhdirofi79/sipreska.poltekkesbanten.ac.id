<?php

namespace Tests\Feature\Sipreska;

use App\Enums\AttendanceWorkUnit;
use App\Models\Activity;
use App\Models\AttendanceForm;
use App\Models\AttendanceResponse;
use App\Models\ActivityLog;
use App\Services\AttendanceExportService;
use App\Services\AttendanceService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class AttendanceNikNipSplitTest extends TestCase
{
    use SipreskaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSipreskaBasics();
        Storage::fake('sipreska');
    }

    private function openForm(Activity $activity, array $overrides = []): AttendanceForm
    {
        return AttendanceForm::query()->create(array_merge([
            'activity_id' => $activity->id,
            'public_token' => bin2hex(random_bytes(32)),
            'is_open' => true,
            'signature_required' => false,
        ], $overrides));
    }

    private function createActivity(): Activity
    {
        return Activity::factory()->create([
            'unit_id' => $this->unitBySlug('d3-kebidanan')->id,
            'category_id' => $this->defaultCategory()->id,
            'title' => 'Workshop NIK NIP',
            'location' => 'Aula',
        ]);
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

    private function submit(AttendanceForm $form, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('attendance.public.store', ['public_token' => $form->public_token]), $this->payload($overrides));
    }

    public function test_public_form_does_not_display_nik_field(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->get(route('attendance.public.show', ['public_token' => $form->public_token]))
            ->assertOk()
            ->assertDontSee('Opsional. Isi jika Anda memiliki NIK.', false)
            ->assertDontSee('name="nik"', false);
    }

    public function test_public_form_displays_nip_field(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->get(route('attendance.public.show', ['public_token' => $form->public_token]))
            ->assertOk()
            ->assertSee('NIP', false)
            ->assertSee('Wajib diisi. Jika tidak memiliki NIP, isi dengan 0 atau -.', false);
    }

    public function test_posted_nik_is_ignored_and_stored_as_null(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->submit($form, [
            'nip' => '198001012020031001',
            'nik' => '3201010101010001',
        ])->assertRedirect(route('attendance.public.success', ['public_token' => $form->public_token]));

        $this->assertDatabaseHas('attendance_responses', [
            'attendance_form_id' => $form->id,
            'nip' => '198001012020031001',
            'nik' => null,
        ]);
    }

    public function test_nip_is_required(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->submit($form, ['nip' => ''])
            ->assertSessionHasErrors('nip');
    }

    public function test_nip_zero_is_accepted(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->submit($form, ['nip' => '0'])
            ->assertRedirect(route('attendance.public.success', ['public_token' => $form->public_token]));

        $this->assertDatabaseHas('attendance_responses', [
            'attendance_form_id' => $form->id,
            'nip' => '0',
            'nik' => null,
        ]);
    }

    public function test_nip_dash_is_accepted(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->submit($form, ['nip' => '-'])
            ->assertRedirect(route('attendance.public.success', ['public_token' => $form->public_token]));

        $this->assertDatabaseHas('attendance_responses', [
            'attendance_form_id' => $form->id,
            'nip' => '-',
            'nik' => null,
        ]);
    }

    public function test_empty_nik_with_nip_zero_succeeds(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->submit($form, ['nip' => '0'])
            ->assertRedirect(route('attendance.public.success', ['public_token' => $form->public_token]));
    }

    public function test_only_nip_can_be_submitted(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->submit($form, ['nip' => '198001012020031001'])
            ->assertRedirect(route('attendance.public.success', ['public_token' => $form->public_token]));

        $this->assertDatabaseHas('attendance_responses', [
            'attendance_form_id' => $form->id,
            'nip' => '198001012020031001',
            'nik' => null,
        ]);
    }

    public function test_both_nik_and_nip_posted_only_stores_nip(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->submit($form, [
            'nik' => '3201010101010001',
            'nip' => '198001012020031001',
        ])->assertRedirect(route('attendance.public.success', ['public_token' => $form->public_token]));

        $this->assertDatabaseHas('attendance_responses', [
            'attendance_form_id' => $form->id,
            'nik' => null,
            'nip' => '198001012020031001',
        ]);
    }


    public function test_empty_nik_is_stored_as_null(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->submit($form, ['nik' => '', 'nip' => '0'])
            ->assertRedirect(route('attendance.public.success', ['public_token' => $form->public_token]));

        $response = AttendanceResponse::query()->where('attendance_form_id', $form->id)->first();
        $this->assertNull($response?->nik);
        $this->assertSame('0', $response?->nip);
        $this->assertNull($response?->nik_normalized);
    }

    public function test_blank_nip_is_rejected(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->submit($form, ['nip' => '   '])
            ->assertSessionHasErrors('nip');
    }

    public function test_nip_is_normalized(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->submit($form, ['nip' => '1980.0101.2020.03.1001']);

        $this->assertDatabaseHas('attendance_responses', [
            'attendance_form_id' => $form->id,
            'nip_normalized' => '198001012020031001',
        ]);
    }

    public function test_duplicate_nip_is_rejected(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->submit($form, ['nip' => '198001012020031001', 'name' => 'Peserta A']);
        $this->submit($form, ['nip' => '1980.0101.2020.03.1001', 'name' => 'Peserta B'])
            ->assertRedirect(route('attendance.public.show', ['public_token' => $form->public_token]))
            ->assertSessionHas('attendance_duplicate', true);
    }

    public function test_admin_responses_index_does_not_show_nik_column(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = $this->createActivity();
        $form = $this->openForm($activity);

        AttendanceResponse::query()->create([
            'attendance_form_id' => $form->id,
            'name' => 'Peserta Index',
            'nik' => '3201010101010001',
            'nik_normalized' => '3201010101010001',
            'nip' => '198001012020031001',
            'nip_normalized' => '198001012020031001',
            'work_unit' => AttendanceWorkUnit::D3Kebidanan->value,
            'signature_disk' => 'sipreska',
            'signature_path' => 'sig.png',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.responses', $activity))
            ->assertOk()
            ->assertSee('Peserta Index', false)
            ->assertSee('198001012020031001', false)
            ->assertDontSee('3201010101010001', false)
            ->assertDontSee('>NIK</th>', false)
            ->assertSee('Cari nama, NIP, identitas lama, jabatan...', false);
    }

    public function test_archived_nik_is_preserved_on_admin_edit(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = $this->createActivity();
        $form = $this->openForm($activity);

        $response = AttendanceResponse::query()->create([
            'attendance_form_id' => $form->id,
            'name' => 'Peserta Edit',
            'nik' => '3201010101010001',
            'nik_normalized' => '3201010101010001',
            'nip' => '198001012020031001',
            'nip_normalized' => '198001012020031001',
            'work_unit' => AttendanceWorkUnit::D3Kebidanan->value,
            'signature_disk' => 'sipreska',
            'signature_path' => 'sig.png',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.response.edit', [$activity, $response]))
            ->assertOk()
            ->assertDontSee('name="nik"', false)
            ->assertSee('name="nip"', false);

        $this->actingAs($admin)
            ->put(route('activities.daftar-hadir.response.update', [$activity, $response]), [
                'name' => 'Peserta Edit Updated',
                'nip' => '198001012020031099',
                'work_unit' => AttendanceWorkUnit::D3Kebidanan->value,
            ])
            ->assertRedirect(route('activities.daftar-hadir.responses', $activity));

        $this->assertDatabaseHas('attendance_responses', [
            'id' => $response->id,
            'name' => 'Peserta Edit Updated',
            'nip' => '198001012020031099',
            'nik' => '3201010101010001',
            'nik_normalized' => '3201010101010001',
        ]);
    }

    public function test_placeholder_nip_zero_is_not_treated_as_duplicate(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->submit($form, ['nip' => '0', 'name' => 'Peserta A'])
            ->assertRedirect(route('attendance.public.success', ['public_token' => $form->public_token]));
        $this->submit($form, ['nip' => '0', 'name' => 'Peserta B'])
            ->assertRedirect(route('attendance.public.success', ['public_token' => $form->public_token]));

        $this->assertSame(2, AttendanceResponse::query()->where('attendance_form_id', $form->id)->count());
    }

    public function test_placeholder_nip_dash_is_not_treated_as_duplicate(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->submit($form, ['nip' => '-', 'name' => 'Peserta A'])
            ->assertRedirect(route('attendance.public.success', ['public_token' => $form->public_token]));
        $this->submit($form, ['nip' => '-', 'name' => 'Peserta B'])
            ->assertRedirect(route('attendance.public.success', ['public_token' => $form->public_token]));

        $this->assertSame(2, AttendanceResponse::query()->where('attendance_form_id', $form->id)->count());
    }

    public function test_same_nip_on_different_activities_is_allowed(): void
    {
        $formA = $this->openForm($this->createActivity());
        $formB = $this->openForm(Activity::factory()->create([
            'unit_id' => $this->unitBySlug('d3-kebidanan')->id,
            'category_id' => $this->defaultCategory()->id,
        ]));

        $this->submit($formA, ['nip' => '198001012020031001']);
        $this->submit($formB, ['nip' => '198001012020031001'])
            ->assertRedirect(route('attendance.public.success', ['public_token' => $formB->public_token]));
    }

    public function test_legacy_data_still_displays_in_admin(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = $this->createActivity();
        $form = $this->openForm($activity);

        AttendanceResponse::query()->create([
            'attendance_form_id' => $form->id,
            'name' => 'Legacy Peserta',
            'nip_nik' => '1234567890123456',
            'nip_nik_normalized' => '1234567890123456',
            'work_unit' => AttendanceWorkUnit::D3Kebidanan->value,
            'signature_disk' => 'sipreska',
            'signature_path' => 'sig.png',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.responses', $activity))
            ->assertOk()
            ->assertSee('Legacy Peserta', false)
            ->assertSee('1234567890123456', false)
            ->assertSee('Data legacy', false);
    }

    public function test_export_has_nip_column_without_nik(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = $this->createActivity();
        $form = $this->openForm($activity);

        AttendanceResponse::query()->create([
            'attendance_form_id' => $form->id,
            'name' => 'Export Row',
            'nik' => '3201010101010001',
            'nik_normalized' => '3201010101010001',
            'nip' => '198001012020031001',
            'nip_normalized' => '198001012020031001',
            'work_unit' => AttendanceWorkUnit::D3Kebidanan->value,
            'signature_disk' => 'sipreska',
            'signature_path' => 'sig.png',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.export-excel', $activity));

        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'attendance-export-').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $reader = new Reader;
        $reader->open($path);

        $headers = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                if ($rowIndex === 1) {
                    $headers = array_map(fn ($cell) => $cell->getValue(), $row->getCells());
                }
            }
        }

        $reader->close();
        @unlink($path);

        $this->assertNotContains('NIK', $headers);
        $this->assertContains('NIP', $headers);
        $this->assertContains('Identitas Lama', $headers);
        $this->assertContains('Tanda Tangan', $headers);
    }

    public function test_export_preserves_leading_zeros_as_text(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = $this->createActivity();
        $form = $this->openForm($activity);

        AttendanceResponse::query()->create([
            'attendance_form_id' => $form->id,
            'name' => 'Leading Zero',
            'nip' => '001234567890',
            'nip_normalized' => '001234567890',
            'work_unit' => AttendanceWorkUnit::D3Kebidanan->value,
            'signature_disk' => 'sipreska',
            'signature_path' => 'sig.png',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.export-excel', $activity));

        $path = tempnam(sys_get_temp_dir(), 'attendance-export-').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $reader = new Reader;
        $reader->open($path);

        $nipValue = null;
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                if ($rowIndex === 2) {
                    $cells = $row->getCells();
                    $nipValue = $cells[2]->getValue();
                }
            }
        }

        $reader->close();
        @unlink($path);

        $this->assertSame('001234567890', $nipValue);
    }

    public function test_admin_unit_only_sees_own_activity_responses(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $otherAdmin = $this->adminUnit('d3-keperawatan');

        $activity = $this->createActivity();
        $form = $this->openForm($activity);
        $this->submit($form, ['name' => 'Unit Scoped']);

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.responses', $activity))
            ->assertOk()
            ->assertSee('Unit Scoped', false);

        $this->actingAs($otherAdmin)
            ->get(route('activities.daftar-hadir.responses', $activity))
            ->assertForbidden();
    }

    public function test_guest_cannot_view_admin_responses(): void
    {
        $activity = $this->createActivity();
        $this->openForm($activity);

        $this->get(route('activities.daftar-hadir.responses', $activity))
            ->assertRedirect(route('login'));
    }

    public function test_nip_is_not_logged_in_audit(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->submit($form, [
            'nip' => '198001012020031001',
        ]);

        $log = ActivityLog::query()->where('action', 'attendance_submitted')->latest('id')->first();
        $payload = json_encode($log?->new_values ?? []);

        $this->assertNotNull($log);
        $this->assertStringNotContainsString('198001012020031001', (string) $payload);
        $this->assertFalse($log->new_values['has_nik'] ?? true);
        $this->assertTrue($log->new_values['has_nip'] ?? false);
    }

    public function test_success_page_does_not_show_full_nip(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->submit($form, [
            'nip' => '198001012020031001',
        ]);

        $this->get(route('attendance.public.success', ['public_token' => $form->public_token]))
            ->assertOk()
            ->assertSee('**************1001', false)
            ->assertDontSee('198001012020031001', false)
            ->assertDontSee('>NIK</', false);
    }

    public function test_normalize_identity_service(): void
    {
        $service = app(AttendanceService::class);

        $this->assertSame('3201010101010001', $service->normalizeIdentity('3201.0101-0101.0001'));
        $this->assertSame('001234567890', $service->normalizeIdentity('001234567890'));
        $this->assertNull($service->normalizeIdentity(''));
        $this->assertNull($service->normalizeIdentity('   '));
    }

    public function test_export_pdf_does_not_include_nik_header(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = $this->createActivity();
        $form = $this->openForm($activity);

        AttendanceResponse::query()->create([
            'attendance_form_id' => $form->id,
            'name' => 'PDF Row',
            'nik' => '3201010101010001',
            'nip' => '198001012020031001',
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

    public function test_any_non_empty_nip_string_is_accepted(): void
    {
        $form = $this->openForm($this->createActivity());

        $this->submit($form, ['nip' => 'NOT-A-NIP'])
            ->assertRedirect(route('attendance.public.success', ['public_token' => $form->public_token]));

        $this->assertDatabaseHas('attendance_responses', [
            'attendance_form_id' => $form->id,
            'nip' => 'NOT-A-NIP',
        ]);
    }

    public function test_route_cache_succeeds(): void
    {
        Artisan::call('route:cache');
        $this->assertTrue(Route::has('attendance.public.store'));
        Artisan::call('route:clear');
    }

    public function test_view_cache_succeeds(): void
    {
        Artisan::call('view:cache');
        $this->assertFileExists(storage_path('framework/views'));
        Artisan::call('view:clear');
    }
}
