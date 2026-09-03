<?php

namespace App\Jobs;

use App\Mail\TransactionalEmailTest;
use App\Models\TransactionalEmail;
use App\Services\TeamMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendTransactionalEmailTest implements ShouldQueue
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
    ) {
        $this->onQueue((string) config('delivery.queues.transactional'));
    }

    public function handle(TeamMailer $teamMailer): void
    {
        $email = TransactionalEmail::query()->with('team')->find($this->emailId);

        if (! $email instanceof TransactionalEmail) {
            return;
        }

        $teamMailer->send(
            $email->team,
            $this->recipient,
            new TransactionalEmailTest($email, $this->subject, $this->html),
            $email->resolvedFromAddress(),
        );

        $email->forceFill(['last_tested_at' => now()])->save();
    }
}
