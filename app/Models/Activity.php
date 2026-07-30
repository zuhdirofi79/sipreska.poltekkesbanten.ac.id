<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'activity_date',
        'start_time',
        'end_time',
        'location',
        'status',
        'category_id',
        'unit_id',
        'pic_name',
        'pic_contact',
        'description',
        'objective',
        'target_participants',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'status' => ActivityStatus::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function invitationDocument(): HasOne
    {
        return $this->hasOne(InvitationDocument::class);
    }

    public function minutesDocument(): HasOne
    {
        return $this->hasOne(MinutesDocument::class);
    }

    public function documentations(): HasMany
    {
        return $this->hasMany(Documentation::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function attendanceForm(): HasOne
    {
        return $this->hasOne(AttendanceForm::class);
    }

    public function offlineAttendanceImports(): HasMany
    {
        return $this->hasMany(OfflineAttendanceImport::class);
    }

    public function scopeForUser($query, User $user)
    {
        if ($user->isSuperadmin()) {
            return $query;
        }

        return $query->where('unit_id', $user->unit_id);
    }
}
