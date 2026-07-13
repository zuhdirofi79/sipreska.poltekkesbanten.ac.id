<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperadmin();
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->isSuperadmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperadmin();
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->isSuperadmin();
    }

    public function toggleStatus(User $actor, User $target): bool
    {
        if (! $actor->isSuperadmin()) {
            return false;
        }

        if ($actor->id === $target->id) {
            return false;
        }

        if ($target->isSuperadmin() && $target->is_active) {
            return $this->hasAnotherActiveSuperadmin($target);
        }

        return true;
    }

    public function resetPassword(User $actor, User $target): bool
    {
        return $actor->isSuperadmin();
    }

    public function demoteOrDeactivate(User $actor, User $target, ?UserRole $newRole, bool $newActive): bool
    {
        if (! $actor->isSuperadmin()) {
            return false;
        }

        if ($actor->id === $target->id && ! $newActive) {
            return false;
        }

        if ($target->isSuperadmin() && $target->is_active) {
            $losingSuperadmin = ($newRole !== null && $newRole !== UserRole::Superadmin) || ! $newActive;

            if ($losingSuperadmin && ! $this->hasAnotherActiveSuperadmin($target)) {
                return false;
            }
        }

        return true;
    }

    private function hasAnotherActiveSuperadmin(User $except): bool
    {
        return User::query()
            ->where('role', UserRole::Superadmin)
            ->where('is_active', true)
            ->where('id', '!=', $except->id)
            ->exists();
    }
}
