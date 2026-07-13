<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'unit_id',
    'is_active',
    'must_change_password',
    'last_login_at',
    'last_login_ip',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function isSuperadmin(): bool
    {
        return $this->role === UserRole::Superadmin;
    }

    public function isAdminUnit(): bool
    {
        return $this->role === UserRole::AdminUnit;
    }

    public function hasValidCustomRole(): bool
    {
        return $this->role instanceof UserRole;
    }

    /**
     * Temporary Filament rollback access only — not used for custom SIPRESKA routes.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        $legacyEmail = strtolower((string) config('sipreska.legacy_filament_email'));

        return $legacyEmail !== '' && strtolower($this->email) === $legacyEmail;
    }
}
