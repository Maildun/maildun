<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent straight from app:install rather than queued, because a fresh
 * deployment usually has no worker running yet to pick it up.
 */
class InstallationSetupLink extends Notification
{
    public function __construct(public string $url) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Finish setting up :appName', ['appName' => config('app.name')]))
            ->line(__(':appName has been installed and is waiting for its first administrator.', ['appName' => config('app.name')]))
            ->action(__('Create the administrator'), $this->url)
            ->line(__('This link expires in 24 hours. Run app:install again for a fresh one.'))
            ->line(__('If you were not expecting this, you can ignore this email.'));
    }
}
