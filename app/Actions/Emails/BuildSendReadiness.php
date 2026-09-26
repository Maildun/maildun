<?php

namespace App\Actions\Emails;

use App\Exceptions\EmailTransportException;
use App\Models\Email;
use App\Services\ResolvedEmailTransport;
use App\Services\TeamMailer;

class BuildSendReadiness
{
    public function __construct(private TeamMailer $teamMailer) {}

    /**
     * The server-side conditions StartEmailSend enforces, checked ahead of
     * time so the hub and Preview and Send can say what is missing before
     * anyone presses Send. StartEmailSend stays the authority: this only
     * reports, it never replaces those checks.
     *
     * @return array{ready: bool, checks: list<array{key: string, passed: bool, message: string, action_url: string|null}>}
     */
    public function handle(Email $email): array
    {
        $email->loadMissing(['team', 'audience', 'segment']);
        $transport = $this->transportFor($email);
        $fromAddress = $email->resolvedFromAddress();
        $recipientCount = $this->recipientCount($email);

        $checks = [
            $this->check(
                'provider',
                $transport !== null,
                $transport !== null
                    ? __('Email delivery is connected and tested.')
                    : __('Connect and test an email provider for this workspace.'),
                $transport !== null ? null : route('teams.email-provider.edit', $email->team),
            ),
            $this->check(
                'sender',
                $transport?->allowsSender($fromAddress) ?? false,
                match (true) {
                    $transport === null => __('The sender is checked once email delivery is connected.'),
                    $transport->allowsSender($fromAddress) => __(':address is verified for this connection.', ['address' => $fromAddress]),
                    default => __(':address is not verified for the current delivery connection.', ['address' => $fromAddress]),
                },
                $transport?->allowsSender($fromAddress) ? null : route('teams.sender.edit', $email->team),
            ),
            $this->check(
                'recipients',
                $recipientCount > 0,
                match (true) {
                    $email->audience === null => __('Choose an audience or segment.'),
                    $recipientCount === 0 => __('Nobody in the selected recipients can be emailed.'),
                    default => trans_choice(':count recipient will receive it.|:count recipients will receive it.', $recipientCount),
                },
                null,
            ),
            $this->check(
                'content',
                filled($email->subject) && filled($email->html),
                filled($email->subject) && filled($email->html)
                    ? __('Subject and content are ready.')
                    : __('Add a subject and content.'),
                null,
            ),
        ];

        return [
            'ready' => collect($checks)->every(fn (array $check): bool => $check['passed']),
            'checks' => $checks,
        ];
    }

    private function transportFor(Email $email): ?ResolvedEmailTransport
    {
        try {
            return $this->teamMailer->resolve($email->team);
        } catch (EmailTransportException) {
            return null;
        }
    }

    private function recipientCount(Email $email): int
    {
        if ($email->audience === null) {
            return 0;
        }

        $subscribers = $email->segment !== null
            ? $email->segment->subscribers()
            : $email->audience->subscribers();

        return $subscribers->sendableFor($email->team)->count();
    }

    /** @return array{key: string, passed: bool, message: string, action_url: string|null} */
    private function check(string $key, bool $passed, string $message, ?string $actionUrl): array
    {
        return ['key' => $key, 'passed' => $passed, 'message' => $message, 'action_url' => $actionUrl];
    }
}
