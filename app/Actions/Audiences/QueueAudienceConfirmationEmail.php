<?php

namespace App\Actions\Audiences;

use App\Actions\Transactional\QueueTransactionalEmail;
use App\Enums\TransactionalEmailStatus;
use App\Models\Audience;
use App\Models\Subscriber;
use App\Models\TransactionalEmailDelivery;
use Illuminate\Support\Facades\URL;

class QueueAudienceConfirmationEmail
{
    public function __construct(private QueueTransactionalEmail $queueTransactionalEmail) {}

    public function handle(Audience $audience, Subscriber $subscriber): TransactionalEmailDelivery
    {
        $transactionalEmail = $audience->team->transactionalEmails()
            ->whereKey($audience->double_opt_in_email_id)
            ->where('status', TransactionalEmailStatus::Published->value)
            ->firstOrFail();

        $confirmationUrl = URL::temporarySignedRoute(
            'public.subscribe.confirm',
            now()->addDays(7),
            ['subscriber' => $subscriber],
        );

        return $this->queueTransactionalEmail->handleForTeam(
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
    }
}
