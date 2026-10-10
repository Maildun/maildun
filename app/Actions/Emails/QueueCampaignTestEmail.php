<?php

namespace App\Actions\Emails;

use App\Enums\TestSendStatus;
use App\Exceptions\EmailTransportException;
use App\Jobs\SendCampaignTestEmail;
use App\Models\Email;
use App\Services\TeamMailer;
use Illuminate\Validation\ValidationException;

class QueueCampaignTestEmail
{
    public function __construct(
        private BuildTrackedEmailHtml $trackedHtml,
        private RenderCampaignContent $renderer,
        private TeamMailer $teamMailer,
    ) {}

    /**
     * Queue a test copy of the draft to a single address, rendered with
     * sample merge data.
     */
    public function handle(Email $email, string $recipient): void
    {
        $this->assertProviderConnected($email);
        $mergeData = $this->renderer->testData($recipient, now());

        $email->forceFill([
            'last_test_status' => TestSendStatus::Queued,
            'last_test_recipient' => $recipient,
            'last_test_error' => null,
        ])->save();

        SendCampaignTestEmail::dispatch(
            $email->id,
            $recipient,
            $this->renderer->text($email->subject, $mergeData),
            $this->trackedHtml->prepareTestHtml(
                $email,
                $this->renderer->html($email->html ?? '', $mergeData),
            ),
            $this->renderer->plainText($email, $mergeData),
        )->afterCommit();
    }

    /**
     * Without a connected provider the job can only fail, so refuse up front
     * instead of reporting the copy as queued.
     */
    private function assertProviderConnected(Email $email): void
    {
        try {
            $transport = $this->teamMailer->resolve($email->team);
        } catch (EmailTransportException) {
            $transport = null;
        }

        if ($transport === null || ! $transport->provider->isSupported()) {
            throw ValidationException::withMessages([
                'to' => __('Connect and test an email provider for this workspace.'),
            ]);
        }
    }
}
