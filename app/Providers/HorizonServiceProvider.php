<?php

namespace App\Providers;

use App\Models\User;
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
     * Register the Horizon gate and the instance operator gate built on it.
     *
     * An operator is a signed-in account whose address is in
     * HORIZON_ALLOWED_EMAILS and has been verified. Verification matters
     * because anyone can register, or change their profile to, an address
     * they do not own; only a verified address proves the mailbox.
     *
     * viewSystemCheck reuses the same test so there is one definition of who
     * runs the instance: it guards the deployment diagnostics and the update
     * status, which describe the whole installation rather than a workspace.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user = null): bool {
            $allowedEmails = config('horizon.allowed_emails', []);

            return $user instanceof User
                && $user->hasVerifiedEmail()
                && is_array($allowedEmails)
                && in_array(Str::lower($user->email), $allowedEmails, true);
        });

        Gate::define('viewSystemCheck', fn (User $user): bool => Gate::forUser($user)->allows('viewHorizon'));
    }
}
