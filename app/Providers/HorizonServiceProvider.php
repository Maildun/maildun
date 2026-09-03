<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
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
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null): bool {
            $allowedEmails = config('horizon.allowed_emails', []);

            return is_string(optional($user)->email)
                && in_array(strtolower(optional($user)->email), $allowedEmails, true);
        });
    }
}
