<?php

namespace App\Actions\Audiences;

use App\Actions\Transactional\QueueTransactionalEmail;
use App\Enums\TransactionalEmailStatus;
use App\Exceptions\EmailTransportException;
use App\Models\Audience;
use App\Models\Subscriber;
use App\Models\TransactionalEmailDelivery;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

class QueueAudienceConfirmationEmail
{
    /**
     * A pending subscriber who submits again gets a fresh confirmation link,
     * but at most once per this many seconds so a form cannot be used to
     * flood an inbox.
     */
    public const RESEND_COOLDOWN_SECONDS = 600;

    public function __construct(private QueueTransactionalEmail $queueTransactionalEmail) {}

    /**
     * Queue the double opt-in confirmation for a pending subscriber. This runs
     * inside the signup transaction, so a workspace that cannot send right
     * now (no verified provider, an unauthorized sender, or an unpublished
     * confirmation email) must not roll the signup back. The subscriber stays
     * pending and the operator gets a warning in the log instead.
     */
    public function handle(Audience $audience, Subscriber $subscriber): ?TransactionalEmailDelivery
    {
        $cooldownKey = 'audience-confirmation:'.$subscriber->id;

        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            return null;
        }

        $transactionalEmail = $audience->team->transactionalEmails()
            ->whereKey($audience->double_opt_in_email_id)
            ->where('status', TransactionalEmailStatus::Published->value)
            ->first();

        if ($transactionalEmail === null) {
            $this->warn($audience, $subscriber, __('The audience has no published confirmation email.'));

            return null;
        }

        $confirmationUrl = URL::temporarySignedRoute(
            'public.subscribe.confirm',
            now()->addDays(7),
            ['subscriber' => $subscriber],
        );

        try {
            $delivery = $this->queueTransactionalEmail->handleForTeam(
                $audience->team,
                $transactionalEmail,
                [
                    'to' => $subscriber->email,
                    'data' => [
                        'confirmation_url' => $confirmationUrl,
                        'email' => $subscriber->email,
                        'first_name' => $subscriber->first_name,
                        'last_name' => $subscriber->last_name,
                    ],
                ],
            );
        } catch (EmailTransportException $exception) {
            $this->warn($audience, $subscriber, $exception->getMessage());

            return null;
        }

        RateLimiter::hit($cooldownKey, self::RESEND_COOLDOWN_SECONDS);

        return $delivery;
    }

    private function warn(Audience $audience, Subscriber $subscriber, string $reason): void
    {
        Log::warning('A double opt-in confirmation email could not be queued.', [
            'team_id' => $audience->team_id,
            'audience_id' => $audience->id,
            'subscriber_id' => $subscriber->id,
            'reason' => $reason,
        ]);
    }
}
