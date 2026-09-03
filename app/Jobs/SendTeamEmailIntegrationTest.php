<?php

namespace App\Jobs;

use App\Enums\EmailProvider;
use App\Mail\TeamEmailIntegrationTest;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Services\SesFeedbackVerifier;
use App\Services\TeamMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendTeamEmailIntegrationTest implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 30];

    public function __construct(
        public int $teamId,
        public int $integrationId,
        public string $recipient,
        public string $fromAddress,
        public string $configurationFingerprint,
    ) {
        $this->onQueue((string) config('delivery.queues.transactional'));
    }

    public function handle(TeamMailer $teamMailer, SesFeedbackVerifier $sesFeedbackVerifier): void
    {
        $team = Team::query()->find($this->teamId);
        $integration = TeamEmailIntegration::query()
            ->whereKey($this->integrationId)
            ->where('team_id', $this->teamId)
            ->whereNotNull('connected_at')
            ->first();

        if (! $team instanceof Team
            || ! $integration instanceof TeamEmailIntegration
            || ! $integration->hasCompleteConfiguration()
            || ! hash_equals($this->configurationFingerprint, $this->fingerprint($integration))) {
            return;
        }

        $teamMailer->sendWithIntegration(
            $team,
            $integration,
            $this->recipient,
            new TeamEmailIntegrationTest($team, $integration->provider, $this->fromAddress),
            $this->fromAddress,
        );

        if ($integration->provider === EmailProvider::AmazonSes
            && $sesFeedbackVerifier->verify($integration) !== null) {
            return;
        }

        DB::transaction(function (): void {
            $team = Team::query()->whereKey($this->teamId)->lockForUpdate()->first();

            if (! $team instanceof Team) {
                return;
            }

            $integration = $team->emailIntegration()
                ->whereKey($this->integrationId)
                ->whereNotNull('connected_at')
                ->lockForUpdate()
                ->first();

            if (! $integration instanceof TeamEmailIntegration
                || ! hash_equals($this->configurationFingerprint, $this->fingerprint($integration))) {
                return;
            }

            $testedAt = now();
            $integration->forceFill([
                'last_tested_at' => $testedAt,
                'test_from_address' => Str::lower($this->fromAddress),
            ])->save();
        });
    }

    public function failed(?\Throwable $exception): void
    {
        Log::warning('A workspace email delivery test failed.', [
            'team_id' => $this->teamId,
            'email_integration_id' => $this->integrationId,
            'exception' => $exception ? $exception::class : null,
        ]);
    }

    private function fingerprint(TeamEmailIntegration $integration): string
    {
        return hash('sha256', serialize([
            $integration->provider->value,
            $integration->settings,
        ]));
    }
}
