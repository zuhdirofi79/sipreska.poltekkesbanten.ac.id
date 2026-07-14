<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasValidCustomRole();
    }

    public function view(User $user, Activity $activity): bool
    {
        return $this->sameUnitOrSuperadmin($user, $activity);
    }

    public function create(User $user): bool
    {
        return $user->hasValidCustomRole();
    }

    public function update(User $user, Activity $activity): bool
    {
        return $this->sameUnitOrSuperadmin($user, $activity);
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $this->sameUnitOrSuperadmin($user, $activity);
    }

    public function manageDocuments(User $user, Activity $activity): bool
    {
        return $this->sameUnitOrSuperadmin($user, $activity);
    }

    public function manageAttendance(User $user, Activity $activity): bool
    {
        return $this->sameUnitOrSuperadmin($user, $activity);
    }

    private function sameUnitOrSuperadmin(User $user, Activity $activity): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $user->isAdminUnit() && (int) $user->unit_id === (int) $activity->unit_id;
    }
}
