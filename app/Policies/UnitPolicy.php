<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function update(User $user, Unit $unit): bool
    {
        return $user->isSuperadmin();
    }

    public function toggle(User $user, Unit $unit): bool
    {
        return $user->isSuperadmin();
    }
}
