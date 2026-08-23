<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\ExternalActivity;
use App\Models\Unit;
use App\Models\User;
use App\Policies\ActivityLogPolicy;
use App\Policies\ActivityPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\ExternalActivityPolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(ExternalActivity::class, ExternalActivityPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(ActivityLog::class, ActivityLogPolicy::class);
    }
}
