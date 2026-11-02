<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        $notificationEmail = config('horizon.notifications.mail');

        if (is_string($notificationEmail) && filled($notificationEmail)) {
            Horizon::routeMailNotificationsTo($notificationEmail);
        }
    }

    /**
     * Configure the Horizon authorization services.
     *
     * Horizon's default also admits everyone while APP_ENV is "local". A
     * self-hosted install that keeps the example environment would then
     * publish every tenant's job payloads to the internet, so the gate is
     * the only way in, in every environment.
     */
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(fn (Request $request): bool => Gate::check('viewHorizon', [$request->user()]));
    }

    /**
     * Register the Horizon gate.
     *
     * Only an account whose address is on the allowlist and has been
     * verified may open Horizon. Without verification anyone could claim an
     * allowlisted address that has no account yet, or switch their own
     * account to it, and read queued payloads.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user = null): bool {
            if ($user === null || ! $user->hasVerifiedEmail()) {
                return false;
            }

            return in_array(Str::lower($user->email), config('horizon.allowed_emails', []), true);
        });
    }
}
