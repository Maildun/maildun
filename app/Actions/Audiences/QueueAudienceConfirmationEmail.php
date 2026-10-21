<?php

namespace App\Actions\Audiences;

use App\Actions\Transactional\QueueTransactionalEmail;
use App\Enums\TransactionalEmailStatus;
use App\Exceptions\EmailAddressSuppressedException;
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
     * now (no verified provider, an unauthorized sender, an unpublished
     * confirmation email, or an address the workspace suppressed) must not
     * roll the signup back. The subscriber stays
     * pending and the operator gets a warning in the log instead.
     *
     * A re-consent link also signs the subscriber's resubscribe_requested_at,
     * so it stops working once they opt out again or a newer request replaces it.
     */
    public function handle(Audience $audience, Subscriber $subscriber): ?TransactionalEmailDelivery
    {
        if ($this->isCoolingDown($subscriber)) {
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
            [
                'subscriber' => $subscriber,
                ...($subscriber->isPendingResubscription()
                    ? ['resubscribe' => $subscriber->resubscribe_requested_at?->getTimestamp()]
                    : []),
            ],
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
        } catch (EmailTransportException|EmailAddressSuppressedException $exception) {
            $this->warn($audience, $subscriber, $exception->getMessage());

            return null;
        }

        RateLimiter::hit($this->cooldownKey($subscriber), self::RESEND_COOLDOWN_SECONDS);

        return $delivery;
    }

    /**
     * Whether a confirmation went out to this subscriber too recently to send
     * another one.
     */
    public function isCoolingDown(Subscriber $subscriber): bool
    {
        return RateLimiter::tooManyAttempts($this->cooldownKey($subscriber), 1);
    }

    private function cooldownKey(Subscriber $subscriber): string
    {
        return 'audience-confirmation:'.$subscriber->id;
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
