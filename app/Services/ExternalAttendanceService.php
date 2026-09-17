<?php

namespace App\Services;

use App\Models\ExternalActivity;
use App\Models\ExternalActivityDate;
use App\Models\ExternalAttendanceParticipant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ExternalAttendanceService
{
    public const SIGNATURE_FOLDER = 'tanda_tangan_eksternal';

    public const DUPLICATE_MESSAGE = 'Data kehadiran Anda sudah tercatat untuk kegiatan ini.';

    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly ExternalActivityQuestionService $questionService,
    ) {}

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function isDuplicateEmail(ExternalActivity $activity, string $emailNormalized, ?int $excludeId = null): bool
    {
        if ($emailNormalized === '') {
            return false;
        }

        return ExternalAttendanceParticipant::query()
            ->where('external_activity_id', $activity->id)
            ->where('email_normalized', $emailNormalized)
            ->when($excludeId !== null, fn (Builder $query) => $query->where('id', '!=', $excludeId))
            ->exists();
    }

    /**
     * @param  array{full_name: string, institution: string, email: string, attendance_dates: list<string>, signature_data: string, custom_answers?: array<int|string, mixed>}  $data
     */
    public function submitResponse(ExternalActivity $activity, array $data, Request $request): ExternalAttendanceParticipant
    {
        if (! $activity->isAcceptingSubmissions()) {
            throw new RuntimeException('Formulir absensi sedang ditutup.');
        }

        $emailNormalized = self::normalizeEmail((string) $data['email']);

        if ($this->isDuplicateEmail($activity, $emailNormalized)) {
            throw new RuntimeException(self::DUPLICATE_MESSAGE);
        }

        $dateIds = $this->resolveAttendanceDateIds($activity, $data['attendance_dates'] ?? []);
        $signatureBinary = $this->decodeSignaturePng((string) ($data['signature_data'] ?? ''));

        return DB::transaction(function () use ($activity, $data, $request, $emailNormalized, $dateIds, $signatureBinary) {
            if ($this->isDuplicateEmail($activity, $emailNormalized)) {
                throw new RuntimeException(self::DUPLICATE_MESSAGE);
            }

            $signatureMeta = $this->storeSignature($activity->id, $signatureBinary);

            $participant = ExternalAttendanceParticipant::query()->create([
                'external_activity_id' => $activity->id,
                'full_name' => trim((string) $data['full_name']),
                'institution' => trim((string) $data['institution']),
                'email' => trim((string) $data['email']),
                'email_normalized' => $emailNormalized,
                'signature_disk' => $signatureMeta['disk'],
                'signature_path' => $signatureMeta['path'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'submitted_at' => now(),
            ]);

            $participant->attendanceDates()->sync($dateIds);
            $this->questionService->storeAnswers(
                $participant,
                $activity,
                $data['custom_answers'] ?? [],
            );
            $participant->load(['attendanceDates', 'answers.question']);

            $this->auditLog->log(
                'external_attendance_submitted',
                'external_attendance_participant',
                $participant->id,
                null,
                $participant->auditSnapshot(),
                $request,
            );

            return $participant;
        });
    }

    /**
     * @param  array{full_name: string, institution: string, email: string, attendance_dates: list<string>}  $data
     */
    public function updateParticipant(
        ExternalAttendanceParticipant $participant,
        array $data,
        ?Request $request = null,
    ): ExternalAttendanceParticipant {
        $activity = $participant->activity;

        if ($activity === null) {
            throw new RuntimeException('Kegiatan eksternal tidak ditemukan.');
        }

        $emailNormalized = self::normalizeEmail((string) $data['email']);

        if ($this->isDuplicateEmail($activity, $emailNormalized, $participant->id)) {
            throw new RuntimeException(self::DUPLICATE_MESSAGE);
        }

        $dateIds = $this->resolveAttendanceDateIds($activity, $data['attendance_dates'] ?? []);
        $oldValues = $participant->loadMissing('attendanceDates')->auditSnapshot();

        $participant->fill([
            'full_name' => trim((string) $data['full_name']),
            'institution' => trim((string) $data['institution']),
            'email' => trim((string) $data['email']),
            'email_normalized' => $emailNormalized,
        ])->save();

        $participant->attendanceDates()->sync($dateIds);
        $participant->load('attendanceDates');

        $this->auditLog->logModelChange(
            'external_attendance_updated',
            $participant,
            $oldValues,
            $participant->auditSnapshot(),
            $request,
        );

        return $participant->fresh(['attendanceDates']) ?? $participant;
    }

    public function deleteParticipant(ExternalAttendanceParticipant $participant, ?Request $request = null): void
    {
        DB::transaction(function () use ($participant, $request) {
            $oldValues = $participant->loadMissing('attendanceDates')->auditSnapshot();
            $signaturePath = $participant->signature_path;
            $disk = $participant->signature_disk ?: $this->disk();

            $participant->attendanceDates()->detach();
            $participant->answers()->delete();
            $participant->delete();

            if ($signaturePath !== '' && Storage::disk($disk)->exists($signaturePath)) {
                Storage::disk($disk)->delete($signaturePath);
            }

            $this->auditLog->log(
                'external_attendance_deleted',
                'external_attendance_participant',
                $participant->id,
                $oldValues,
                null,
                $request,
            );
        });
    }

    public function open(ExternalActivity $activity, ?Request $request = null): ExternalActivity
    {
        $oldValues = $activity->only(['is_open']);
        $activity->is_open = true;
        $activity->save();

        $this->auditLog->logModelChange(
            'external_attendance_opened',
            $activity,
            $oldValues,
            $activity->only(['is_open']),
            $request,
        );

        return $activity;
    }

    public function close(ExternalActivity $activity, ?Request $request = null): ExternalActivity
    {
        $oldValues = $activity->only(['is_open']);
        $activity->is_open = false;
        $activity->save();

        $this->auditLog->logModelChange(
            'external_attendance_closed',
            $activity,
            $oldValues,
            $activity->only(['is_open']),
            $request,
        );

        return $activity;
    }

    /**
     * @param  array{search?: ?string, institution?: ?string, email?: ?string, attendance_date?: ?string, sort?: string}  $filters
     */
    public function participantsQuery(ExternalActivity $activity, array $filters = []): Builder
    {
        $query = ExternalAttendanceParticipant::query()
            ->where('external_activity_id', $activity->id)
            ->with(['attendanceDates', 'answers.question']);

        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('full_name', 'like', "%{$term}%")
                    ->orWhere('institution', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        if (! empty($filters['institution'])) {
            $query->where('institution', 'like', '%'.$filters['institution'].'%');
        }

        if (! empty($filters['email'])) {
            $query->where('email', 'like', '%'.$filters['email'].'%');
        }

        if (! empty($filters['attendance_date'])) {
            $query->whereHas('attendanceDates', function (Builder $builder) use ($filters): void {
                $builder->whereDate('event_date', $filters['attendance_date']);
            });
        }

        return $this->applyParticipantSort($query, $filters['sort'] ?? 'oldest');
    }

    /** @return array{total: int, today: int, last_submitted_at: ?\Illuminate\Support\Carbon} */
    public function participantStats(ExternalActivity $activity): array
    {
        $timezone = config('sipreska.display_timezone', 'Asia/Jakarta');
        $todayStart = now($timezone)->startOfDay();
        $lastSubmittedRaw = $activity->participants()->max('submitted_at');

        return [
            'total' => $activity->participants()->count(),
            'today' => $activity->participants()->where('submitted_at', '>=', $todayStart)->count(),
            'last_submitted_at' => $lastSubmittedRaw
                ? \Illuminate\Support\Carbon::parse($lastSubmittedRaw)
                : null,
        ];
    }

    /**
     * @param  list<string>  $incomingDates
     */
    public function syncDates(ExternalActivity $activity, array $incomingDates): void
    {
        $normalized = collect($incomingDates)
            ->map(fn (string $date) => trim($date))
            ->filter()
            ->unique()
            ->values();

        if ($normalized->isEmpty()) {
            throw new RuntimeException('Minimal satu tanggal kegiatan wajib dipilih.');
        }

        $existing = $activity->dates()->get()->keyBy(
            fn (ExternalActivityDate $date) => $date->event_date?->format('Y-m-d')
        );

        foreach ($normalized as $date) {
            if (! $existing->has($date)) {
                $activity->dates()->create(['event_date' => $date]);
            }
        }

        foreach ($existing as $dateKey => $model) {
            if ($normalized->contains($dateKey)) {
                continue;
            }

            if ($model->hasParticipants()) {
                throw new RuntimeException(
                    'Tanggal '.$model->event_date?->translatedFormat('d F Y').' tidak dapat dihapus karena sudah memiliki data kehadiran.'
                );
            }

            $model->delete();
        }
    }

    /**
     * @param  list<string>  $selectedDates
     * @return list<int>
     */
    private function resolveAttendanceDateIds(ExternalActivity $activity, array $selectedDates): array
    {
        $selected = collect($selectedDates)
            ->map(fn (mixed $date) => trim((string) $date))
            ->filter()
            ->unique()
            ->values();

        if ($selected->isEmpty()) {
            throw new RuntimeException('Minimal satu tanggal kehadiran wajib dipilih.');
        }

        $allowed = $activity->dates()->get()->keyBy(
            fn (ExternalActivityDate $date) => $date->event_date?->format('Y-m-d')
        );

        $ids = [];

        foreach ($selected as $date) {
            $match = $allowed->get($date);

            if ($match === null) {
                throw new RuntimeException('Tanggal kehadiran tidak termasuk dalam kegiatan ini.');
            }

            $ids[] = $match->id;
        }

        return $ids;
    }

    private function applyParticipantSort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'newest' => $query->orderByDesc('submitted_at')->orderByDesc('id'),
            'name_asc' => $query->orderBy('full_name')->orderBy('submitted_at')->orderBy('id'),
            'name_desc' => $query->orderByDesc('full_name')->orderBy('submitted_at')->orderBy('id'),
            default => $query->orderBy('submitted_at')->orderBy('id'),
        };
    }

    /** @return array{disk: string, path: string} */
    private function storeSignature(int $activityId, string $binary): array
    {
        $prefix = trim((string) config('sipreska.document_path_prefix', 'docs'), '/');
        $directory = "{$prefix}/".self::SIGNATURE_FOLDER."/{$activityId}";
        $filename = Str::uuid()->toString().'.png';
        $path = "{$directory}/{$filename}";
        $disk = $this->disk();

        Storage::disk($disk)->put($path, $binary);

        return [
            'disk' => $disk,
            'path' => $path,
        ];
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

    private function disk(): string
    {
        return (string) config('sipreska.document_disk', 'sipreska');
    }
}
