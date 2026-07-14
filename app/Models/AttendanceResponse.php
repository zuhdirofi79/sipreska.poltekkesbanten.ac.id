<?php

namespace App\Models;

use App\Support\AttendanceConfirmation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceResponse extends Model
{
    protected $fillable = [
        'attendance_form_id',
        'name',
        'nik',
        'nik_normalized',
        'nip',
        'nip_normalized',
        'nip_nik',
        'nip_nik_normalized',
        'position',
        'work_unit',
        'institution_name',
        'signature_disk',
        'signature_path',
        'ip_address',
        'user_agent',
        'submitted_at',
    ];

    protected $hidden = [
        'nik_normalized',
        'nip_normalized',
        'nip_nik_normalized',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(AttendanceForm::class, 'attendance_form_id');
    }

    public function hasLegacyIdentity(): bool
    {
        return ($this->nik === null || $this->nik === '')
            && ($this->nip === null || $this->nip === '')
            && ($this->nip_nik !== null && $this->nip_nik !== '');
    }

    public function legacyIdentity(): ?string
    {
        return $this->hasLegacyIdentity() ? $this->nip_nik : null;
    }

    public function getFormattedNikAttribute(): ?string
    {
        return $this->nik ?: null;
    }

    public function getFormattedNipAttribute(): ?string
    {
        return $this->nip ?: null;
    }

    public function getMaskedNikAttribute(): ?string
    {
        return $this->nik !== null && $this->nik !== ''
            ? AttendanceConfirmation::maskIdentity($this->nik)
            : null;
    }

    public function getMaskedNipAttribute(): ?string
    {
        return $this->nip !== null && $this->nip !== ''
            ? AttendanceConfirmation::maskIdentity($this->nip)
            : null;
    }

    /** @return array<string, mixed> */
    public function auditSnapshot(): array
    {
        return [
            'name' => $this->name,
            'has_nik' => $this->nik !== null && $this->nik !== '',
            'has_nip' => $this->nip !== null && $this->nip !== '',
            'has_legacy_identity' => $this->hasLegacyIdentity(),
            'work_unit' => $this->work_unit,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
        ];
    }
}
