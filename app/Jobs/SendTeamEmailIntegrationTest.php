<?php

namespace App\Jobs;

use App\Enums\EmailProvider;
use App\Exceptions\EmailTransportException;
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
use Throwable;

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

        if ($integration->provider === EmailProvider::AmazonSes) {
            $feedbackFailure = $sesFeedbackVerifier->verify($integration);

            if ($feedbackFailure !== null) {
                $this->recordFailure($feedbackFailure);

                return;
            }
        }

        $this->updateTestedIntegration(fn (TeamEmailIntegration $integration) => $integration->forceFill([
            'last_tested_at' => now(),
            'test_from_address' => Str::lower($this->fromAddress),
            'test_requested_at' => null,
            'test_failed_at' => null,
            'test_failure' => null,
        ])->save());
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('A workspace email delivery test failed.', [
            'team_id' => $this->teamId,
            'email_integration_id' => $this->integrationId,
            'exception' => $exception ? $exception::class : null,
        ]);

        // Only the sanitized transport message is safe to show; anything else
        // may carry provider internals, so it gets the generic explanation.
        $this->recordFailure($exception instanceof EmailTransportException
            ? $exception->getMessage()
            : __(EmailTransportException::MESSAGE));
    }

    private function recordFailure(string $reason): void
    {
        $this->updateTestedIntegration(fn (TeamEmailIntegration $integration) => $integration->forceFill([
            'test_requested_at' => null,
            'test_failed_at' => now(),
            'test_failure' => Str::limit($reason, 500, ''),
        ])->save());
    }

    /**
     * Apply a result only while the connection still has the tested settings,
     * so a slow test never overwrites the state of newer credentials.
     *
     * @param  callable(TeamEmailIntegration): mixed  $update
     */
    private function updateTestedIntegration(callable $update): void
    {
        DB::transaction(function () use ($update): void {
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

            $update($integration);
        });
    }

    private function fingerprint(TeamEmailIntegration $integration): string
    {
        return hash('sha256', serialize([
            $integration->provider->value,
            $integration->settings,
        ]));
    }
}
