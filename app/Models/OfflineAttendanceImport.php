<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfflineAttendanceImport extends Model
{
    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_IMPORTED = 'imported';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'activity_id',
        'attendance_form_id',
        'uploaded_by',
        'original_filename',
        'stored_disk',
        'stored_path',
        'mime_type',
        'file_size',
        'status',
        'detected_rows_count',
        'imported_rows_count',
        'skipped_duplicate_count',
        'default_work_unit',
        'notes',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'detected_rows_count' => 'integer',
            'imported_rows_count' => 'integer',
            'skipped_duplicate_count' => 'integer',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function attendanceForm(): BelongsTo
    {
        return $this->belongsTo(AttendanceForm::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(OfflineAttendanceImportRow::class)->orderBy('row_number')->orderBy('id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_NEEDS_REVIEW, self::STATUS_UPLOADED, self::STATUS_FAILED], true);
    }
}
