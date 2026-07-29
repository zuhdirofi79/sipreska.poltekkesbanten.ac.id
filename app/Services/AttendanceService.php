<?php

namespace App\Services;

use App\Enums\AttendanceWorkUnit;
use App\Models\Activity;
use App\Models\AttendanceForm;
use App\Models\AttendanceResponse;
use App\Support\DateTimeFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AttendanceService
{
    public function __construct(
        private readonly FileStorageService $fileStorage,
        private readonly AuditLogService $auditLog,
    ) {}

    public function ensureForm(Activity $activity): AttendanceForm
    {
        $existing = $activity->attendanceForm;

        if ($existing !== null) {
            return $existing;
        }

        return AttendanceForm::query()->create([
            'activity_id' => $activity->id,
            'public_token' => $this->generateToken(),
            'is_open' => false,
            'signature_required' => true,
        ]);
    }

    public function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function normalizeIdentity(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $withoutSeparators = str_replace([' ', '.', '-'], '', $trimmed);
        $digits = preg_replace('/\D+/', '', $withoutSeparators) ?? '';

        return $digits === '' ? null : $digits;
    }

    /** @deprecated Use normalizeIdentity() instead. Kept for legacy references. */
    public function normalizeNipNik(string $value): string
    {
        return $this->normalizeIdentity($value) ?? '';
    }

    public function isDuplicateNik(AttendanceForm $form, ?string $nikNormalized, ?int $excludeId = null): bool
    {
        if ($nikNormalized === null || $nikNormalized === '') {
            return false;
        }

        return $this->identityExists($form, 'nik_normalized', $nikNormalized, $excludeId);
    }

    public function isDuplicateNip(AttendanceForm $form, ?string $nipNormalized, ?int $excludeId = null): bool
    {
        // "0" is a public placeholder for participants without a real NIP; do not treat as unique identity.
        if ($nipNormalized === null || $nipNormalized === '' || $nipNormalized === '0') {
            return false;
        }

        return $this->identityExists($form, 'nip_normalized', $nipNormalized, $excludeId);
    }

    /**
     * @return array{nik: ?string, nip: ?string, nik_normalized: ?string, nip_normalized: ?string}
     */
    public function resolveIdentityFields(array $data): array
    {
        $nik = $this->nullableString($data['nik'] ?? null);
        $nip = $this->nullableString($data['nip'] ?? null);

        return [
            'nik' => $nik,
            'nip' => $nip,
            'nik_normalized' => $this->normalizeIdentity($nik),
            'nip_normalized' => $this->normalizeIdentity($nip),
        ];
    }

    public function duplicateSubmissionMessage(?string $nikNormalized, ?string $nipNormalized, AttendanceForm $form, ?int $excludeId = null): ?string
    {
        $nikDuplicate = $this->isDuplicateNik($form, $nikNormalized, $excludeId);
        $nipDuplicate = $this->isDuplicateNip($form, $nipNormalized, $excludeId);

        if ($nikDuplicate && $nipDuplicate) {
            return 'Data kehadiran Anda sudah tercatat untuk kegiatan ini.';
        }

        if ($nikDuplicate) {
            return 'Data dengan NIK tersebut sudah tercatat untuk kegiatan ini.';
        }

        if ($nipDuplicate) {
            return 'Data dengan NIP tersebut sudah tercatat untuk kegiatan ini.';
        }

        return null;
    }

    public function submitResponse(AttendanceForm $form, array $data, Request $request): AttendanceResponse
    {
        if (! $form->isAcceptingSubmissions()) {
            throw new RuntimeException('Formulir absensi sedang ditutup.');
        }

        $identity = $this->resolveIdentityFields($data);

        if ($message = $this->duplicateSubmissionMessage($identity['nik_normalized'], $identity['nip_normalized'], $form)) {
            throw new RuntimeException($message);
        }

        $workUnit = AttendanceWorkUnit::from((string) $data['work_unit']);

        if ($workUnit === AttendanceWorkUnit::InstansiLainnya && empty($data['institution_name'])) {
            throw new RuntimeException('Nama instansi wajib diisi untuk instansi lainnya.');
        }

        $signatureBinary = null;

        if ($form->signature_required) {
            $signatureBinary = $this->decodeSignaturePng((string) ($data['signature_data'] ?? ''));
        }

        return DB::transaction(function () use ($form, $data, $request, $identity, $workUnit, $signatureBinary) {
            if ($message = $this->duplicateSubmissionMessage($identity['nik_normalized'], $identity['nip_normalized'], $form)) {
                throw new RuntimeException($message);
            }

            $signatureMeta = $this->fileStorage->storeBinary(
                $signatureBinary ?? $this->placeholderPng(),
                'signature',
                $form->activity_id,
                'png',
                'image/png',
            );

            $response = AttendanceResponse::query()->create([
                'attendance_form_id' => $form->id,
                'name' => (string) $data['name'],
                'nik' => $identity['nik'],
                'nik_normalized' => $identity['nik_normalized'],
                'nip' => $identity['nip'],
                'nip_normalized' => $identity['nip_normalized'],
                'nip_nik' => null,
                'nip_nik_normalized' => null,
                'position' => $data['position'] ?? null,
                'work_unit' => $workUnit->value,
                'institution_name' => $data['institution_name'] ?? null,
                'signature_disk' => $signatureMeta['disk'],
                'signature_path' => $signatureMeta['path'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'submitted_at' => now(),
            ]);

            $this->auditLog->log(
                'attendance_submitted',
                'attendance_response',
                $response->id,
                null,
                $response->auditSnapshot(),
                $request,
            );

            return $response;
        });
    }

    public function updateResponse(AttendanceResponse $response, array $data, ?Request $request = null): AttendanceResponse
    {
        $identity = $this->resolveIdentityFields($data);
        $form = $response->form;

        if ($form === null) {
            throw new RuntimeException('Form absensi tidak ditemukan.');
        }

        if ($message = $this->duplicateSubmissionMessage($identity['nik_normalized'], $identity['nip_normalized'], $form, $response->id)) {
            throw new RuntimeException($message);
        }

        $oldValues = $response->auditSnapshot();

        $response->fill([
            'name' => (string) $data['name'],
            'nik' => $identity['nik'],
            'nik_normalized' => $identity['nik_normalized'],
            'nip' => $identity['nip'],
            'nip_normalized' => $identity['nip_normalized'],
            'position' => $data['position'] ?? null,
            'work_unit' => (string) $data['work_unit'],
            'institution_name' => $data['institution_name'] ?? null,
        ])->save();

        $this->auditLog->logModelChange(
            'attendance_response_updated',
            $response,
            $oldValues,
            $response->auditSnapshot(),
            $request,
        );

        return $response->fresh();
    }

    public function updateSettings(AttendanceForm $form, array $data, ?Request $request = null): AttendanceForm
    {
        $oldValues = $form->only(['signature_required', 'opens_at', 'closes_at']);

        $form->fill([
            'signature_required' => (bool) ($data['signature_required'] ?? $form->signature_required),
            'opens_at' => $data['opens_at'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
        ])->save();

        $this->auditLog->logModelChange(
            'attendance_settings_updated',
            $form,
            $oldValues,
            $form->only(['signature_required', 'opens_at', 'closes_at']),
            $request,
        );

        return $form->fresh();
    }

    public function open(AttendanceForm $form, ?Request $request = null): AttendanceForm
    {
        $oldValues = $form->only(['is_open']);
        $form->is_open = true;
        $form->save();

        $this->auditLog->logModelChange('attendance_opened', $form, $oldValues, $form->only(['is_open']), $request);

        return $form;
    }

    public function close(AttendanceForm $form, ?Request $request = null): AttendanceForm
    {
        $oldValues = $form->only(['is_open']);
        $form->is_open = false;
        $form->save();

        $this->auditLog->logModelChange('attendance_closed', $form, $oldValues, $form->only(['is_open']), $request);

        return $form;
    }

    /** @param array{search?: ?string, work_unit?: ?string, date_from?: ?string, date_to?: ?string, sort?: string} $filters */
    public function responsesQuery(AttendanceForm $form, array $filters = []): Builder
    {
        $query = AttendanceResponse::query()
            ->where('attendance_form_id', $form->id);

        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('name', 'like', "%{$term}%")
                    ->orWhere('nik', 'like', "%{$term}%")
                    ->orWhere('nip', 'like', "%{$term}%")
                    ->orWhere('nip_nik', 'like', "%{$term}%")
                    ->orWhere('position', 'like', "%{$term}%");
            });
        }

        if (! empty($filters['work_unit'])) {
            $query->where('work_unit', $filters['work_unit']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('submitted_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('submitted_at', '<=', $filters['date_to']);
        }

        return $this->applyResponseSort($query, $filters['sort'] ?? 'newest');
    }

    /** @return array{total: int, today: int, last_submitted_at: ?\Illuminate\Support\Carbon} */
    public function responseStats(AttendanceForm $form): array
    {
        $timezone = config('sipreska.display_timezone', 'Asia/Jakarta');
        $todayStart = now($timezone)->startOfDay();
        $lastSubmittedRaw = $form->responses()->max('submitted_at');

        return [
            'total' => $form->responses()->count(),
            'today' => $form->responses()->where('submitted_at', '>=', $todayStart)->count(),
            'last_submitted_at' => $lastSubmittedRaw
                ? \Illuminate\Support\Carbon::parse($lastSubmittedRaw)
                : null,
        ];
    }

    /** @return array{total: int, today: int, last_submitted_at: ?string, form_status: string, version: ?string} */
    public function responsePollPayload(AttendanceForm $form): array
    {
        $stats = $this->responseStats($form);

        return [
            'total' => $stats['total'],
            'today' => $stats['today'],
            'last_submitted_at' => DateTimeFormatter::display($stats['last_submitted_at']),
            'form_status' => $form->isAcceptingSubmissions() ? 'Aktif' : 'Ditutup',
            'version' => $stats['last_submitted_at']?->toIso8601String(),
        ];
    }

    private function applyResponseSort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->orderBy('submitted_at')->orderBy('created_at'),
            'name_asc' => $query->orderBy('name')->orderByDesc('submitted_at'),
            'name_desc' => $query->orderByDesc('name')->orderByDesc('submitted_at'),
            default => $query->orderByDesc('submitted_at')->orderByDesc('created_at'),
        };
    }

    public function deleteResponse(AttendanceResponse $response, ?Request $request = null): void
    {
        DB::transaction(function () use ($response, $request) {
            $oldValues = $response->auditSnapshot();
            $signaturePath = $response->signature_path;

            $response->delete();
            $this->fileStorage->delete($signaturePath);

            $this->auditLog->log(
                'attendance_response_deleted',
                'attendance_response',
                $response->id,
                $oldValues,
                null,
                $request,
            );
        });
    }

    private function identityExists(AttendanceForm $form, string $column, string $normalized, ?int $excludeId): bool
    {
        return AttendanceResponse::query()
            ->where('attendance_form_id', $form->id)
            ->where($column, $normalized)
            ->when($excludeId !== null, fn (Builder $query) => $query->where('id', '!=', $excludeId))
            ->exists();
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function decodeSignaturePng(string $data): string
    {
        if ($data === '') {
            throw new RuntimeException('Tanda tangan wajib diisi.');
        }

        if (str_starts_with($data, 'data:image/png;base64,')) {
            $data = substr($data, strlen('data:image/png;base64,'));
        }

        $binary = base64_decode($data, true);

        if ($binary === false || strlen($binary) < 32) {
            throw new RuntimeException('Format tanda tangan tidak valid.');
        }

        return $binary;
    }

    private function placeholderPng(): string
    {
        $decoded = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);

        return $decoded !== false ? $decoded : '';
    }
}
