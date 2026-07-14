<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function update(User $user, Category $category): bool
    {
        return $user->isSuperadmin();
    }

    public function toggle(User $user, Category $category): bool
    {
        return $user->isSuperadmin();
    }
}
