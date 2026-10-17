<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an audience's notification address that someone joined or left.
 *
 * It carries plain values rather than models so a subscriber deleted before
 * the queue runs still produces the alert they triggered.
 */
class AudienceSubscriptionChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $audienceName,
        public string $email,
        public string $name,
        public bool $subscribed,
        public string $profileUrl,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replace = ['audience' => $this->audienceName, 'email' => $this->email];

        $message = (new MailMessage)
            ->subject($this->subscribed
                ? __('New subscriber to :audience', $replace)
                : __('Someone unsubscribed from :audience', $replace))
            ->line($this->subscribed
                ? __(':email subscribed to :audience.', $replace)
                : __(':email unsubscribed from :audience.', $replace));

        if ($this->name !== '') {
            $message->line(__('Name: :name', ['name' => $this->name]));
        }

        return $message
            ->action(__('View subscriber'), $this->profileUrl)
            ->line(__('You get this email because it is set as the notification address for this audience.'));
    }
}
