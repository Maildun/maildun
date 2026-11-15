<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the previous address that the account now signs in with another one.
 *
 * It is the owner's only signal when someone else made the change: the new
 * address receives the verification link, and the old one can no longer
 * request a password reset.
 */
class EmailAddressChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $newEmail) {}

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
            ->subject(__('Your :app email address was changed', ['app' => config('app.name')]))
            ->line(__('The email address on your :app account was changed to :email.', [
                'app' => config('app.name'),
                'email' => $this->newEmail,
            ]))
            ->line(__('If you made this change, no further action is needed.'))
            ->line(__('If you did not, someone else has access to your account. Contact the administrator of your :app instance or workspace straight away.', [
                'app' => config('app.name'),
            ]));
    }
}
