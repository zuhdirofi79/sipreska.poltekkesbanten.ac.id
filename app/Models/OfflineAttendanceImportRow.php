<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineAttendanceImportRow extends Model
{
    protected $fillable = [
        'offline_attendance_import_id',
        'row_number',
        'detected_name',
        'detected_nip',
        'detected_position',
        'detected_signature_path',
        'confidence_score',
        'is_selected',
        'review_name',
        'review_nip',
        'review_position',
        'review_signature_disk',
        'review_signature_path',
        'review_notes',
        'imported_attendance_response_id',
        'raw_text',
    ];

    protected function casts(): array
    {
        return [
            'is_selected' => 'boolean',
            'confidence_score' => 'float',
            'row_number' => 'integer',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(OfflineAttendanceImport::class, 'offline_attendance_import_id');
    }

    public function importedResponse(): BelongsTo
    {
        return $this->belongsTo(AttendanceResponse::class, 'imported_attendance_response_id');
    }

    public function effectiveName(): ?string
    {
        $value = $this->review_name ?? $this->detected_name;

        return $value !== null && trim($value) !== '' ? trim($value) : null;
    }

    public function effectiveNip(): ?string
    {
        $value = $this->review_nip ?? $this->detected_nip;

        return $value !== null && trim($value) !== '' ? trim($value) : null;
    }

    public function effectivePosition(): ?string
    {
        $value = $this->review_position ?? $this->detected_position;

        return $value !== null && trim($value) !== '' ? trim($value) : null;
    }
}
