<?php

namespace Tests\Feature\Sipreska;

use App\Models\Activity;
use App\Models\AttendanceForm;
use App\Models\AttendanceResponse;
use App\Models\Documentation;
use App\Models\InvitationDocument;
use App\Models\MinutesDocument;
use App\Models\Publication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class FileUploadTest extends TestCase
{
    use SipreskaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSipreskaBasics();
        Storage::fake('sipreska');
    }

    private function activityForAdmin(?int $unitId = null): array
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = Activity::factory()->create([
            'unit_id' => $unitId ?? $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        return [$admin, $activity];
    }

    public function test_create_page_does_not_offer_upload_forms(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');

        $this->actingAs($admin)
            ->get(route('activities.create'))
            ->assertOk()
            ->assertSee('Simpan dan Lanjutkan')
            ->assertSee('Dapat dilengkapi setelah kegiatan disimpan')
            ->assertDontSee('enctype="multipart/form-data"', false);
    }

    public function test_store_redirects_to_edit_with_document_tabs(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');

        $response = $this->actingAs($admin)
            ->post(route('activities.store'), [
                'title' => 'Workshop Baru',
                'category_id' => $this->defaultCategory()->id,
                'activity_date' => '2026-07-13',
                'start_time' => '08:00',
                'end_time' => '10:00',
                'location' => 'Ruang Aula',
                'unit_id' => $admin->unit_id,
                'pic_name' => 'Dr. Budi',
            ]);

        $activity = Activity::query()->first();
        $this->assertNotNull($activity);

        $response
            ->assertRedirect(route('activities.edit', ['activity' => $activity, 'tab' => 'invitation']))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('attendance_forms', ['activity_id' => $activity->id]);

        $this->actingAs($admin)
            ->get(route('activities.edit', ['activity' => $activity, 'tab' => 'invitation']))
            ->assertOk()
            ->assertSee('Surat Undangan')
            ->assertSee('Daftar Hadir / Absensi')
            ->assertSee('Notulensi')
            ->assertSee('Dokumentasi')
            ->assertSee('Publikasi')
            ->assertSee('Belum diunggah');
    }

    public function test_valid_pdf_is_accepted_for_invitation(): void
    {
        [$admin, $activity] = $this->activityForAdmin();

        $file = UploadedFile::fake()->createWithContent(
            'undangan.pdf',
            '%PDF-1.4 valid pdf content',
            'application/pdf',
        );

        $this->actingAs($admin)
            ->post(route('activities.surat-undangan.store', $activity), [
                'file' => $file,
                'letter_number' => '001/UN/2026',
            ])
            ->assertRedirect();

        $document = InvitationDocument::query()->where('activity_id', $activity->id)->first();
        $this->assertNotNull($document);
        $this->assertSame('001/UN/2026', $document->letter_number);
        $this->assertStringStartsWith("docs/surat_undangan/{$activity->id}/", $document->path);
        $this->assertSame('undangan.pdf', $document->original_name);
        $this->assertNotSame('undangan.pdf', basename($document->path));
        Storage::disk('sipreska')->assertExists($document->path);
    }

    public function test_fake_pdf_mime_is_rejected(): void
    {
        [$admin, $activity] = $this->activityForAdmin();

        $file = UploadedFile::fake()->create('fake.pdf', 100, 'text/plain');

        $this->actingAs($admin)
            ->post(route('activities.surat-undangan.store', $activity), ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    public function test_executable_upload_is_rejected(): void
    {
        [$admin, $activity] = $this->activityForAdmin();

        $file = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

        $this->actingAs($admin)
            ->post(route('activities.surat-undangan.store', $activity), ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    public function test_doc_and_docx_are_accepted_for_minutes(): void
    {
        [$admin, $activity] = $this->activityForAdmin();

        foreach (['doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'] as $ext => $mime) {
            $file = UploadedFile::fake()->create("notulen.{$ext}", 100, $mime);

            $this->actingAs($admin)
                ->post(route('activities.notulensi.store', $activity), ['file' => $file])
                ->assertRedirect();

            $document = MinutesDocument::query()->where('activity_id', $activity->id)->first();
            $this->assertNotNull($document);
            Storage::disk('sipreska')->assertExists($document->path);

            $document->delete();
            Storage::disk('sipreska')->deleteDirectory("docs/notulensi/{$activity->id}");
        }
    }

    public function test_valid_image_is_accepted_for_documentation(): void
    {
        [$admin, $activity] = $this->activityForAdmin();

        $file = UploadedFile::fake()->image('foto.jpg');

        $this->actingAs($admin)
            ->post(route('activities.dokumentasi.store', $activity), [
                'file' => $file,
                'type' => 'foto_kegiatan',
                'title' => 'Foto Kegiatan',
            ])
            ->assertRedirect();

        $doc = Documentation::query()->where('activity_id', $activity->id)->first();
        $this->assertNotNull($doc);
        $this->assertStringStartsWith("docs/dokumentasi/{$activity->id}/", $doc->path);
        Storage::disk('sipreska')->assertExists($doc->path);
    }

    public function test_svg_is_rejected_for_documentation(): void
    {
        [$admin, $activity] = $this->activityForAdmin();

        $file = UploadedFile::fake()->createWithContent('icon.svg', '<svg></svg>', 'image/svg+xml');

        $this->actingAs($admin)
            ->post(route('activities.dokumentasi.store', $activity), [
                'file' => $file,
                'type' => 'banner',
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_admin_unit_cannot_upload_to_other_unit_activity(): void
    {
        $keperawatan = $this->unitBySlug('d3-keperawatan');
        $activity = Activity::factory()->create([
            'unit_id' => $keperawatan->id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        $admin = $this->adminUnit('d3-kebidanan');
        $file = UploadedFile::fake()->createWithContent('undangan.pdf', '%PDF-1.4', 'application/pdf');

        $this->actingAs($admin)
            ->post(route('activities.surat-undangan.store', $activity), ['file' => $file])
            ->assertForbidden();
    }

    public function test_superadmin_can_upload_to_any_unit(): void
    {
        $keperawatan = $this->unitBySlug('d3-keperawatan');
        $activity = Activity::factory()->create([
            'unit_id' => $keperawatan->id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        $superadmin = $this->superadmin();
        $file = UploadedFile::fake()->createWithContent('undangan.pdf', '%PDF-1.4', 'application/pdf');

        $this->actingAs($superadmin)
            ->post(route('activities.surat-undangan.store', $activity), ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('invitation_documents', ['activity_id' => $activity->id]);
    }

    public function test_unauthorized_file_download_is_denied(): void
    {
        $kebidananAdmin = $this->adminUnit('d3-kebidanan');
        $keperawatanAdmin = $this->adminUnit('d3-keperawatan');

        $activity = Activity::factory()->create([
            'unit_id' => $kebidananAdmin->unit_id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        $document = InvitationDocument::query()->create([
            'activity_id' => $activity->id,
            'disk' => 'sipreska',
            'path' => "docs/surat_undangan/{$activity->id}/test.pdf",
            'original_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        Storage::disk('sipreska')->put($document->path, '%PDF-1.4');

        $this->actingAs($keperawatanAdmin)
            ->get(route('activities.surat-undangan.download', $activity))
            ->assertForbidden();
    }

    public function test_authorized_file_download_works(): void
    {
        [$admin, $activity] = $this->activityForAdmin();

        InvitationDocument::query()->create([
            'activity_id' => $activity->id,
            'disk' => 'sipreska',
            'path' => "docs/surat_undangan/{$activity->id}/test.pdf",
            'original_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        Storage::disk('sipreska')->put("docs/surat_undangan/{$activity->id}/test.pdf", '%PDF-1.4 content');

        $this->actingAs($admin)
            ->get(route('activities.surat-undangan.download', $activity))
            ->assertOk();
    }

    public function test_path_traversal_in_stored_path_is_rejected_on_download(): void
    {
        [$admin, $activity] = $this->activityForAdmin();

        InvitationDocument::query()->create([
            'activity_id' => $activity->id,
            'disk' => 'sipreska',
            'path' => '../../.env',
            'original_name' => 'evil.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        $this->actingAs($admin)
            ->get(route('activities.surat-undangan.download', $activity))
            ->assertStatus(404);
    }

    public function test_replacing_invitation_does_not_leave_orphan_file(): void
    {
        [$admin, $activity] = $this->activityForAdmin();

        $first = UploadedFile::fake()->createWithContent('first.pdf', '%PDF-1.4 first', 'application/pdf');
        $this->actingAs($admin)->post(route('activities.surat-undangan.store', $activity), ['file' => $first]);

        $firstDoc = InvitationDocument::query()->where('activity_id', $activity->id)->first();
        $firstPath = $firstDoc->path;

        $second = UploadedFile::fake()->createWithContent('second.pdf', '%PDF-1.4 second', 'application/pdf');
        $this->actingAs($admin)->post(route('activities.surat-undangan.store', $activity), ['file' => $second]);

        Storage::disk('sipreska')->assertMissing($firstPath);
        $this->assertSame(1, InvitationDocument::query()->where('activity_id', $activity->id)->count());
    }

    public function test_deleting_invitation_removes_database_and_file(): void
    {
        [$admin, $activity] = $this->activityForAdmin();

        $file = UploadedFile::fake()->createWithContent('undangan.pdf', '%PDF-1.4', 'application/pdf');
        $this->actingAs($admin)->post(route('activities.surat-undangan.store', $activity), ['file' => $file]);

        $document = InvitationDocument::query()->where('activity_id', $activity->id)->first();
        $path = $document->path;

        $this->actingAs($admin)
            ->delete(route('activities.surat-undangan.destroy', $activity))
            ->assertRedirect();

        $this->assertDatabaseMissing('invitation_documents', ['id' => $document->id]);
        Storage::disk('sipreska')->assertMissing($path);
    }

    public function test_publication_stores_url_not_file(): void
    {
        [$admin, $activity] = $this->activityForAdmin();

        $this->actingAs($admin)
            ->post(route('activities.publikasi.store', $activity), [
                'platform' => 'instagram',
                'title' => 'Posting IG',
                'url' => 'https://instagram.com/p/abc123',
                'published_at' => '2026-07-13',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('publications', [
            'activity_id' => $activity->id,
            'url' => 'https://instagram.com/p/abc123',
        ]);
    }

    public function test_signature_is_stored_as_file_not_base64_in_database(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = Activity::factory()->create([
            'unit_id' => $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
        ]);

        $form = AttendanceForm::query()->create([
            'activity_id' => $activity->id,
            'public_token' => Str::random(64),
            'is_open' => true,
            'signature_required' => true,
        ]);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);

        $this->post(route('attendance.public.store', $form->public_token), [
            'name' => 'Peserta A',
            'nip' => '198001012020031001',
            'work_unit' => 'd3-kebidanan',
            'signature_data' => 'data:image/png;base64,'.base64_encode($png),
        ])->assertRedirect();

        $response = AttendanceResponse::query()->first();
        $this->assertNotNull($response);
        $this->assertStringStartsWith("docs/tanda_tangan/{$activity->id}/", $response->signature_path);
        $this->assertStringNotContainsString('base64', $response->signature_path);
        Storage::disk('sipreska')->assertExists($response->signature_path);
    }

    public function test_guest_cannot_download_protected_files_route(): void
    {
        [$admin, $activity] = $this->activityForAdmin();

        InvitationDocument::query()->create([
            'activity_id' => $activity->id,
            'disk' => 'sipreska',
            'path' => "docs/surat_undangan/{$activity->id}/test.pdf",
            'original_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        $this->get(route('activities.surat-undangan.download', $activity))
            ->assertRedirect(route('login'));
    }

    public function test_no_storage_symlink_dependency_in_disk_config(): void
    {
        $this->assertSame(public_path(), config('filesystems.disks.sipreska.root'));
        $this->assertStringContainsString('docs', (string) config('sipreska.document_root'));
    }
}
