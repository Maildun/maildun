<?php

namespace App\Jobs;

use App\Mail\SenderVerificationEmail;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSender;
use App\Services\TeamMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\URL;

class SendTeamSenderVerification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 30];

    public function __construct(
        public int $senderId,
        public int $integrationId,
        public int $verificationVersion,
    ) {
        $this->onQueue((string) config('delivery.queues.transactional'));
    }

    public function handle(TeamMailer $teamMailer): void
    {
        $sender = TeamSender::query()->with('team')->find($this->senderId);
        $integration = TeamEmailIntegration::query()
            ->whereKey($this->integrationId)
            ->where('verification_version', $this->verificationVersion)
            ->first();

        if (! $sender instanceof TeamSender
            || ! $integration instanceof TeamEmailIntegration
            || $integration->team_id !== $sender->team_id
            || ! $integration->isVerified()
            || $sender->isVerifiedFor($integration)) {
            return;
        }

        $verificationUrl = URL::temporarySignedRoute(
            'team-senders.verify',
            now()->addDay(),
            [
                'teamSender' => $sender,
                'integration' => $integration->id,
                'version' => $integration->verification_version,
            ],
        );

        $teamMailer->sendSenderVerification(
            $sender->team,
            $integration,
            $sender,
            new SenderVerificationEmail($sender, $verificationUrl),
        );

        $sender->forceFill(['verification_sent_at' => now()])->save();
    }
}
