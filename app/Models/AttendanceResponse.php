<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceResponse extends Model
{
    protected $fillable = [
        'attendance_form_id',
        'name',
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
}
