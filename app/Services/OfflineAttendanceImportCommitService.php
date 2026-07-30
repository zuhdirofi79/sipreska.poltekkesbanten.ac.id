<?php

namespace App\Services;

use App\Enums\AttendanceWorkUnit;
use App\Models\AttendanceForm;
use App\Models\AttendanceResponse;
use App\Models\OfflineAttendanceImport;
use App\Models\OfflineAttendanceImportRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OfflineAttendanceImportCommitService
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly FileStorageService $fileStorage,
        private readonly AuditLogService $auditLog,
        private readonly OfflineAttendanceImportService $importService,
    ) {}

    /**
     * @return array{imported: int, skipped_duplicates: int, skipped_invalid: int}
     */
    public function commit(OfflineAttendanceImport $import): array
    {
        if ($import->status === OfflineAttendanceImport::STATUS_IMPORTED) {
            throw new RuntimeException('Import ini sudah diselesaikan sebelumnya.');
        }

        if (! $import->isEditable()) {
            throw new RuntimeException('Import ini tidak siap untuk di-commit.');
        }

        $form = $import->attendanceForm
            ?? AttendanceForm::query()->find($import->attendance_form_id);

        if ($form === null) {
            throw new RuntimeException('Form absensi kegiatan tidak ditemukan.');
        }

        $workUnit = AttendanceWorkUnit::tryFrom((string) $import->default_work_unit)
            ?? $this->importService->defaultWorkUnit($import->activity);

        $imported = 0;
        $skippedDuplicates = 0;
        $skippedInvalid = 0;

        DB::transaction(function () use (
            $import,
            $form,
            $workUnit,
            &$imported,
            &$skippedDuplicates,
            &$skippedInvalid,
        ): void {
            $rows = $import->rows()->where('is_selected', true)->orderBy('row_number')->orderBy('id')->get();

            foreach ($rows as $row) {
                /** @var OfflineAttendanceImportRow $row */
                $name = $row->effectiveName();
                $nip = $row->effectiveNip();

                if ($name === null || $nip === null) {
                    $skippedInvalid++;
                    continue;
                }

                if ($this->isDuplicate($form, $name, $nip)) {
                    $skippedDuplicates++;
                    $row->review_notes = trim(($row->review_notes ? $row->review_notes.' ' : '').'[Dilewati: duplikat]');
                    $row->save();
                    continue;
                }

                $response = $this->createResponse($form, $row, $name, $nip, $workUnit);
                $row->imported_attendance_response_id = $response->id;
                $row->save();
                $imported++;
            }

            $import->update([
                'status' => OfflineAttendanceImport::STATUS_IMPORTED,
                'imported_rows_count' => $imported,
                'skipped_duplicate_count' => $skippedDuplicates,
                'notes' => trim(($import->notes ? $import->notes."\n" : '')."Import final: {$imported} masuk, {$skippedDuplicates} duplikat dilewati, {$skippedInvalid} baris tidak valid."),
            ]);
        });

        $this->auditLog->log(
            'attendance_offline_import_committed',
            'offline_attendance_import',
            $import->id,
            null,
            [
                'activity_id' => $import->activity_id,
                'imported' => $imported,
                'skipped_duplicates' => $skippedDuplicates,
                'skipped_invalid' => $skippedInvalid,
            ],
            request(),
        );

        return [
            'imported' => $imported,
            'skipped_duplicates' => $skippedDuplicates,
            'skipped_invalid' => $skippedInvalid,
        ];
    }

    private function isDuplicate(AttendanceForm $form, string $name, string $nip): bool
    {
        $identity = $this->attendanceService->resolveIdentityFields([
            'nik' => null,
            'nip' => $nip,
        ]);

        if ($this->attendanceService->isDuplicateNip($form, $identity['nip_normalized'])) {
            return true;
        }

        // Placeholder NIP (0 / - / non-digit) — match by exact name + nip string.
        $normalized = $identity['nip_normalized'];
        if ($normalized === null || $normalized === '0') {
            return AttendanceResponse::query()
                ->where('attendance_form_id', $form->id)
                ->where('name', $name)
                ->where('nip', $nip)
                ->exists();
        }

        return false;
    }

    private function createResponse(
        AttendanceForm $form,
        OfflineAttendanceImportRow $row,
        string $name,
        string $nip,
        AttendanceWorkUnit $workUnit,
    ): AttendanceResponse {
        $identity = $this->attendanceService->resolveIdentityFields([
            'nik' => null,
            'nip' => $nip,
        ]);

        $signatureBinary = $this->resolveSignatureBinary($row);
        $signatureMeta = $this->fileStorage->storeBinary(
            $signatureBinary,
            'signature',
            (int) $form->activity_id,
            'png',
            'image/png',
        );

        $institutionName = $workUnit === AttendanceWorkUnit::InstansiLainnya
            ? ($form->activity?->unit?->name ?? $form->activity?->unit?->short_name)
            : null;

        $response = AttendanceResponse::query()->create([
            'attendance_form_id' => $form->id,
            'name' => $name,
            'nik' => null,
            'nik_normalized' => null,
            'nip' => $identity['nip'],
            'nip_normalized' => $identity['nip_normalized'],
            'nip_nik' => null,
            'nip_nik_normalized' => null,
            'position' => $row->effectivePosition(),
            'work_unit' => $workUnit->value,
            'institution_name' => $institutionName,
            'signature_disk' => $signatureMeta['disk'],
            'signature_path' => $signatureMeta['path'],
            'ip_address' => request()->ip(),
            'user_agent' => 'offline-import:'.$row->offline_attendance_import_id,
            'submitted_at' => now(),
        ]);

        $this->auditLog->log(
            'attendance_offline_imported',
            'attendance_response',
            $response->id,
            null,
            array_merge($response->auditSnapshot(), [
                'source' => 'offline_import',
                'offline_import_id' => $row->offline_attendance_import_id,
                'offline_import_row_id' => $row->id,
            ]),
            request(),
        );

        return $response;
    }

    private function resolveSignatureBinary(OfflineAttendanceImportRow $row): string
    {
        if ($row->review_signature_path) {
            $disk = $row->review_signature_disk ?: 'local';
            $binary = Storage::disk($disk)->get($row->review_signature_path);

            if (is_string($binary) && $binary !== '') {
                // Convert JPEG/etc to stored PNG path still accepts any binary labeled png;
                // store as-is when already png-like; otherwise keep bytes for archive.
                return $binary;
            }
        }

        return $this->placeholderPng();
    }

    private function placeholderPng(): string
    {
        $decoded = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);

        return $decoded !== false ? $decoded : '';
    }
}
