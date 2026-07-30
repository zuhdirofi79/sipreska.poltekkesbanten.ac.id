<?php

namespace App\Services;

use App\Enums\AttendanceWorkUnit;
use App\Models\Activity;
use App\Models\AttendanceForm;
use App\Models\OfflineAttendanceImport;
use App\Models\OfflineAttendanceImportRow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class OfflineAttendanceImportService
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly AuditLogService $auditLog,
    ) {}

    public function createFromUpload(
        Activity $activity,
        AttendanceForm $form,
        UploadedFile $file,
        ?User $uploader = null,
    ): OfflineAttendanceImport {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'scan';
        $originalFilename = $safeName.'.'.$extension;

        $import = OfflineAttendanceImport::query()->create([
            'activity_id' => $activity->id,
            'attendance_form_id' => $form->id,
            'uploaded_by' => $uploader?->id,
            'original_filename' => $originalFilename,
            'stored_disk' => 'local',
            'stored_path' => '',
            'mime_type' => $file->getMimeType(),
            'file_size' => (int) $file->getSize(),
            'status' => OfflineAttendanceImport::STATUS_UPLOADED,
            'default_work_unit' => $this->defaultWorkUnit($activity)->value,
        ]);

        $relativeDir = "offline-attendance-imports/{$activity->id}/{$import->id}";
        $storedName = 'original.'.$extension;
        $path = $file->storeAs($relativeDir, $storedName, 'local');

        if ($path === false) {
            $import->delete();
            throw new RuntimeException('Gagal menyimpan file upload.');
        }

        $import->update([
            'stored_path' => $path,
            'status' => OfflineAttendanceImport::STATUS_PROCESSING,
        ]);

        $this->process($import);

        $this->auditLog->log(
            'attendance_offline_import_uploaded',
            'offline_attendance_import',
            $import->id,
            null,
            [
                'activity_id' => $activity->id,
                'original_filename' => $originalFilename,
                'mime_type' => $import->mime_type,
                'status' => $import->fresh()->status,
            ],
            request(),
        );

        return $import->fresh(['rows']) ?? $import;
    }

    public function process(OfflineAttendanceImport $import): OfflineAttendanceImport
    {
        // OCR is intentionally optional. Without a local OCR engine we still open
        // a review workspace with blank rows so admins can type from the scan.
        $import->update([
            'status' => OfflineAttendanceImport::STATUS_NEEDS_REVIEW,
            'notes' => 'Deteksi otomatis (OCR) tidak tersedia di server ini. '
                .'Gunakan preview scan di samping untuk mengisi data secara manual dari template SIPRESKA.',
            'error_message' => null,
        ]);

        if ($import->rows()->count() === 0) {
            $this->seedBlankRows($import, 5);
        }

        $import->update([
            'detected_rows_count' => $import->rows()->count(),
        ]);

        return $import->fresh(['rows']) ?? $import;
    }

    public function seedBlankRows(OfflineAttendanceImport $import, int $count = 1): void
    {
        $start = ((int) $import->rows()->max('row_number')) + 1;

        for ($i = 0; $i < $count; $i++) {
            OfflineAttendanceImportRow::query()->create([
                'offline_attendance_import_id' => $import->id,
                'row_number' => $start + $i,
                'is_selected' => true,
                'confidence_score' => null,
            ]);
        }
    }

    public function addBlankRow(OfflineAttendanceImport $import): OfflineAttendanceImportRow
    {
        if (! $import->isEditable()) {
            throw new RuntimeException('Import ini sudah tidak dapat diedit.');
        }

        $rowNumber = ((int) $import->rows()->max('row_number')) + 1;

        $row = OfflineAttendanceImportRow::query()->create([
            'offline_attendance_import_id' => $import->id,
            'row_number' => $rowNumber,
            'is_selected' => true,
        ]);

        $import->update([
            'detected_rows_count' => $import->rows()->count(),
        ]);

        return $row;
    }

    /**
     * @param  array{default_work_unit?: ?string, rows?: array<int, array<string, mixed>>}  $payload
     */
    public function updateReview(OfflineAttendanceImport $import, array $payload): OfflineAttendanceImport
    {
        if (! $import->isEditable()) {
            throw new RuntimeException('Import ini sudah tidak dapat diedit.');
        }

        return DB::transaction(function () use ($import, $payload) {
            if (! empty($payload['default_work_unit'])) {
                $import->default_work_unit = (string) $payload['default_work_unit'];
                $import->save();
            }

            foreach ($payload['rows'] ?? [] as $rowId => $rowData) {
                /** @var OfflineAttendanceImportRow|null $row */
                $row = $import->rows()->whereKey((int) $rowId)->first();

                if ($row === null) {
                    continue;
                }

                $row->is_selected = (bool) ($rowData['is_selected'] ?? false);
                $row->review_name = $this->nullableTrim($rowData['review_name'] ?? null);
                $row->review_nip = $this->nullableTrim($rowData['review_nip'] ?? null);
                $row->review_position = $this->nullableTrim($rowData['review_position'] ?? null);
                $row->review_notes = $this->nullableTrim($rowData['review_notes'] ?? null);

                if (($rowData['signature'] ?? null) instanceof UploadedFile) {
                    $this->storeRowSignature($import, $row, $rowData['signature']);
                }

                $row->save();
            }

            return $import->fresh(['rows']) ?? $import;
        });
    }

    public function destroy(OfflineAttendanceImport $import): void
    {
        $disk = $import->stored_disk ?: 'local';
        $path = $import->stored_path;

        foreach ($import->rows as $row) {
            if ($row->review_signature_path) {
                Storage::disk($row->review_signature_disk ?: 'local')->delete($row->review_signature_path);
            }
        }

        $import->delete();

        if ($path !== '') {
            Storage::disk($disk)->delete($path);
            $dir = dirname($path);
            if ($dir !== '.' && $dir !== '') {
                Storage::disk($disk)->deleteDirectory($dir);
            }
        }
    }

    public function defaultWorkUnit(Activity $activity): AttendanceWorkUnit
    {
        $slug = $activity->unit?->slug;

        return AttendanceWorkUnit::tryFrom((string) $slug) ?? AttendanceWorkUnit::InstansiLainnya;
    }

    private function storeRowSignature(
        OfflineAttendanceImport $import,
        OfflineAttendanceImportRow $row,
        UploadedFile $file,
    ): void {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        $relativeDir = "offline-attendance-imports/{$import->activity_id}/{$import->id}/signatures";
        $name = 'row-'.$row->id.'-'.Str::lower(Str::random(8)).'.'.$extension;
        $path = $file->storeAs($relativeDir, $name, 'local');

        if ($path === false) {
            throw new RuntimeException('Gagal menyimpan tanda tangan baris.');
        }

        if ($row->review_signature_path) {
            Storage::disk($row->review_signature_disk ?: 'local')->delete($row->review_signature_path);
        }

        $row->review_signature_disk = 'local';
        $row->review_signature_path = $path;
    }

    private function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
