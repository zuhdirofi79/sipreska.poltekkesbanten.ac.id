<?php

namespace Tests\Feature\Sipreska;

use App\Enums\AttendanceWorkUnit;
use App\Models\Activity;
use App\Models\AttendanceForm;
use App\Models\AttendanceResponse;
use App\Models\OfflineAttendanceImport;
use App\Models\OfflineAttendanceImportRow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class OfflineAttendanceImportTest extends TestCase
{
    use SipreskaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSipreskaBasics();
        Storage::fake('local');
        Storage::fake('sipreska');
    }

    /** @return array{0: \App\Models\User, 1: Activity, 2: AttendanceForm} */
    private function activityWithForm(): array
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = Activity::factory()->create([
            'unit_id' => $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
            'title' => 'Workshop Offline Absensi',
            'location' => 'Aula Utama',
        ]);

        $form = AttendanceForm::query()->create([
            'activity_id' => $activity->id,
            'public_token' => bin2hex(random_bytes(32)),
            'is_open' => true,
            'signature_required' => false,
        ]);

        return [$admin, $activity, $form];
    }

    public function test_authorized_user_can_download_offline_template(): void
    {
        [$admin, $activity] = $this->activityWithForm();

        $response = $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.template-offline', $activity));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'attachment; filename=',
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringContainsString(
            'template-daftar-hadir-workshop-offline-absensi.pdf',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_guest_cannot_download_offline_template(): void
    {
        [, $activity] = $this->activityWithForm();

        $this->get(route('activities.daftar-hadir.template-offline', $activity))
            ->assertRedirect(route('login'));
    }

    public function test_other_unit_admin_cannot_download_template(): void
    {
        [, $activity] = $this->activityWithForm();
        $otherAdmin = $this->adminUnit('d3-keperawatan');

        $this->actingAs($otherAdmin)
            ->get(route('activities.daftar-hadir.template-offline', $activity))
            ->assertForbidden();
    }

    public function test_attendance_tab_shows_offline_panel(): void
    {
        [$admin, $activity] = $this->activityWithForm();

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'attendance']))
            ->assertOk()
            ->assertSee('Absensi Offline', false)
            ->assertSee('Download Template Kertas Absensi', false)
            ->assertSee('Import Absensi Offline', false)
            ->assertSee(route('activities.daftar-hadir.template-offline', $activity), false);
    }

    public function test_upload_creates_import_and_redirects_to_review(): void
    {
        [$admin, $activity] = $this->activityWithForm();
        $file = UploadedFile::fake()->image('scan-daftar-hadir.jpg', 800, 600);

        $response = $this->actingAs($admin)
            ->post(route('activities.daftar-hadir.import.store', $activity), [
                'scan' => $file,
            ]);

        $import = OfflineAttendanceImport::query()->first();
        $this->assertNotNull($import);
        $this->assertSame(OfflineAttendanceImport::STATUS_NEEDS_REVIEW, $import->status);
        $this->assertGreaterThan(0, $import->rows()->count());

        $response->assertRedirect(route('activities.daftar-hadir.import.review', [$activity, $import]));
    }

    public function test_commit_creates_attendance_responses_for_selected_rows(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();
        $file = UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf');

        $this->actingAs($admin)
            ->post(route('activities.daftar-hadir.import.store', $activity), [
                'scan' => $file,
            ]);

        $import = OfflineAttendanceImport::query()->firstOrFail();
        $rows = $import->rows()->orderBy('id')->take(2)->get();

        $payload = [
            'default_work_unit' => AttendanceWorkUnit::D3Kebidanan->value,
            'rows' => [],
        ];

        foreach ($import->rows as $row) {
            $payload['rows'][$row->id] = [
                'is_selected' => false,
                'review_name' => '',
                'review_nip' => '',
                'review_position' => '',
            ];
        }

        $payload['rows'][$rows[0]->id] = [
            'is_selected' => '1',
            'review_name' => 'Peserta Offline A',
            'review_nip' => '198001012020031001',
            'review_position' => 'Dosen',
        ];
        $payload['rows'][$rows[1]->id] = [
            'is_selected' => '1',
            'review_name' => 'Peserta Offline B',
            'review_nip' => '0',
            'review_position' => 'Staff',
        ];

        $this->actingAs($admin)
            ->post(route('activities.daftar-hadir.import.update-review', [$activity, $import]), $payload)
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('activities.daftar-hadir.import.commit', [$activity, $import]))
            ->assertRedirect(route('activities.daftar-hadir.responses', $activity));

        $this->assertDatabaseHas('attendance_responses', [
            'attendance_form_id' => $form->id,
            'name' => 'Peserta Offline A',
            'nip' => '198001012020031001',
            'nik' => null,
        ]);
        $this->assertDatabaseHas('attendance_responses', [
            'attendance_form_id' => $form->id,
            'name' => 'Peserta Offline B',
            'nip' => '0',
        ]);
        $this->assertSame(2, AttendanceResponse::query()->where('attendance_form_id', $form->id)->count());
        $this->assertSame(OfflineAttendanceImport::STATUS_IMPORTED, $import->fresh()->status);
    }

    public function test_commit_accepts_nip_dash_and_skips_duplicates(): void
    {
        [$admin, $activity, $form] = $this->activityWithForm();

        AttendanceResponse::query()->create([
            'attendance_form_id' => $form->id,
            'name' => 'Sudah Ada',
            'nip' => '198001012020031001',
            'nip_normalized' => '198001012020031001',
            'work_unit' => AttendanceWorkUnit::D3Kebidanan->value,
            'signature_disk' => 'sipreska',
            'signature_path' => 'sig.png',
            'submitted_at' => now(),
        ]);

        $file = UploadedFile::fake()->image('scan2.png');
        $this->actingAs($admin)
            ->post(route('activities.daftar-hadir.import.store', $activity), ['scan' => $file]);

        $import = OfflineAttendanceImport::query()->latest('id')->firstOrFail();
        $first = $import->rows()->orderBy('id')->firstOrFail();
        $second = $import->rows()->orderBy('id')->skip(1)->firstOrFail();

        $payload = ['default_work_unit' => AttendanceWorkUnit::D3Kebidanan->value, 'rows' => []];
        foreach ($import->rows as $row) {
            $payload['rows'][$row->id] = ['is_selected' => false, 'review_name' => '', 'review_nip' => ''];
        }
        $payload['rows'][$first->id] = [
            'is_selected' => '1',
            'review_name' => 'Duplikat NIP',
            'review_nip' => '198001012020031001',
        ];
        $payload['rows'][$second->id] = [
            'is_selected' => '1',
            'review_name' => 'Tanpa NIP',
            'review_nip' => '-',
        ];

        $this->actingAs($admin)
            ->post(route('activities.daftar-hadir.import.update-review', [$activity, $import]), $payload);
        $this->actingAs($admin)
            ->post(route('activities.daftar-hadir.import.commit', [$activity, $import]));

        $this->assertSame(2, AttendanceResponse::query()->where('attendance_form_id', $form->id)->count());
        $this->assertDatabaseHas('attendance_responses', [
            'attendance_form_id' => $form->id,
            'name' => 'Tanpa NIP',
            'nip' => '-',
        ]);
        $this->assertSame(1, (int) $import->fresh()->skipped_duplicate_count);
    }

    public function test_ocr_unavailable_still_opens_review_without_500(): void
    {
        [$admin, $activity] = $this->activityWithForm();
        $file = UploadedFile::fake()->image('fallback.jpg');

        $this->actingAs($admin)
            ->post(route('activities.daftar-hadir.import.store', $activity), ['scan' => $file])
            ->assertRedirect();

        $import = OfflineAttendanceImport::query()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.import.review', [$activity, $import]))
            ->assertOk()
            ->assertSee('Review Import Offline', false)
            ->assertSee('Deteksi otomatis (OCR) tidak tersedia', false)
            ->assertSee('Import Final', false);
    }

    public function test_destroy_removes_draft_import(): void
    {
        [$admin, $activity] = $this->activityWithForm();
        $file = UploadedFile::fake()->image('delete-me.jpg');

        $this->actingAs($admin)
            ->post(route('activities.daftar-hadir.import.store', $activity), ['scan' => $file]);

        $import = OfflineAttendanceImport::query()->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('activities.daftar-hadir.import.destroy', [$activity, $import]))
            ->assertRedirect(route('activities.daftar-hadir.import.create', $activity));

        $this->assertDatabaseMissing('offline_attendance_imports', ['id' => $import->id]);
        $this->assertSame(0, OfflineAttendanceImportRow::query()->count());
    }
}
