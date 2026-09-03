<?php

namespace App\Jobs;

use App\Mail\ComposedEmailTest;
use App\Models\Email;
use App\Services\TeamMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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

        $email->forceFill(['last_tested_at' => now()])->save();
    }
}
