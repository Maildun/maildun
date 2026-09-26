<?php

namespace App\Jobs;

use App\Enums\TestSendStatus;
use App\Exceptions\EmailTransportException;
use App\Mail\ComposedEmailTest;
use App\Models\Email;
use App\Services\TeamMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendCampaignTestEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 30];

    public function __construct(
        public int $emailId,
        public string $recipient,
        public string $subject,
        public string $html,
        public string $plainText,
    ) {
        $this->onQueue((string) config('delivery.queues.transactional'));
    }

    public function handle(TeamMailer $teamMailer): void
    {
        $email = Email::query()->with(['team', 'audience', 'attachments'])->find($this->emailId);

        if (! $email instanceof Email) {
            return;
        }

        $teamMailer->send(
            $email->team,
            $this->recipient,
            new ComposedEmailTest($email, $this->subject, $this->html, $this->plainText),
            $email->resolvedFromAddress(),
        );

        $email->forceFill([
            'last_tested_at' => now(),
            'last_test_status' => TestSendStatus::Sent,
            'last_test_error' => null,
        ])->save();
    }

    /**
     * Record why the test copy never arrived so the editor can say so,
     * instead of the author only ever seeing "Test email queued".
     */
    public function failed(?Throwable $exception): void
    {
        Email::query()
            ->whereKey($this->emailId)
            ->where('last_test_recipient', $this->recipient)
            ->update([
                'last_test_status' => TestSendStatus::Failed,
                'last_test_error' => $exception instanceof EmailTransportException
                    ? $exception->getMessage()
                    : __('The test email could not be sent.'),
            ]);
    }
}
