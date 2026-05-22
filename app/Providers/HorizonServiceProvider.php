<?php

namespace App\Providers;

use App\Http\Middleware\EnsureAdmin;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Slack/SMS/email alerting can be wired here once channels are
        // configured in services.php — kept off by default to avoid noise.
    }

    /**
     * Register the Horizon dashboard gate.
     *
     * Admin-tier roles can view Horizon in any environment; in local the
     * gate is permissive so devs can iterate without a logged-in user.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', static function (?User $user) {
            if (app()->environment('local', 'testing')) {
                return true;
            }

            return $user !== null && $user->hasAnyRole(EnsureAdmin::ADMIN_ROLES);
        });
    }
}
