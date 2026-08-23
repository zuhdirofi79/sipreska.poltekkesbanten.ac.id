<?php

namespace App\Policies;

use App\Models\ExternalActivity;
use App\Models\User;

class ExternalActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasValidCustomRole();
    }

    public function view(User $user, ExternalActivity $externalActivity): bool
    {
        return $user->hasValidCustomRole();
    }

    public function create(User $user): bool
    {
        return $user->hasValidCustomRole();
    }

    public function update(User $user, ExternalActivity $externalActivity): bool
    {
        return $user->hasValidCustomRole();
    }

    public function delete(User $user, ExternalActivity $externalActivity): bool
    {
        return $user->hasValidCustomRole();
    }

    public function manageAttendance(User $user, ExternalActivity $externalActivity): bool
    {
        return $user->hasValidCustomRole();
    }
}
