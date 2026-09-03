<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\StoreTeamSenderDomainRequest;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSender;
use App\Models\TeamSenderDomain;
use App\Services\SenderDomainVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TeamSenderDomainController extends Controller
{
    public function store(StoreTeamSenderDomainRequest $request, Team $team): RedirectResponse
    {
        $senderDomain = DB::transaction(function () use ($request, $team): TeamSenderDomain {
            $lockedTeam = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $integration = $lockedTeam->emailIntegration()->lockForUpdate()->first();

            if (! $integration instanceof TeamEmailIntegration || ! $integration->isVerified()) {
                throw ValidationException::withMessages([
                    'domain' => __('Verify email delivery before adding a sender domain.'),
                ]);
            }

            return $lockedTeam->senderDomains()->create($request->validated());
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Add the TXT record shown for :domain, then check DNS.', ['domain' => $senderDomain->domain]),
        ]);

        return back();
    }

    public function verify(
        Request $request,
        Team $team,
        TeamSenderDomain $teamSenderDomain,
        SenderDomainVerifier $verifier,
    ): RedirectResponse {
        $this->authorizeManagement($request, $team, $teamSenderDomain);
        $integration = $team->emailIntegration()->first();

        if (! $integration instanceof TeamEmailIntegration || ! $integration->isVerified()) {
            throw ValidationException::withMessages([
                'domain' => __('Verify email delivery before checking this sender domain.'),
            ]);
        }

        if (! $teamSenderDomain->matchesAddress((string) $integration->test_from_address)) {
            throw ValidationException::withMessages([
                'domain' => __('Test email delivery using a From address on :domain before verifying its DNS record.', [
                    'domain' => $teamSenderDomain->domain,
                ]),
            ]);
        }

        if (! $verifier->hasVerificationRecord($teamSenderDomain)) {
            $teamSenderDomain->forceFill(['verification_checked_at' => now()])->save();

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('The required TXT record was not found yet. DNS changes can take time to propagate.'),
            ]);

            return back();
        }

        $authorizedSenderCount = DB::transaction(function () use ($team, $teamSenderDomain): int {
            $lockedTeam = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $senderDomain = $lockedTeam->senderDomains()
                ->whereKey($teamSenderDomain->id)
                ->lockForUpdate()
                ->firstOrFail();
            $integration = $lockedTeam->emailIntegration()->lockForUpdate()->first();

            if (! $integration instanceof TeamEmailIntegration || ! $integration->isVerified()) {
                throw ValidationException::withMessages([
                    'domain' => __('The email delivery connection changed. Test it again before verifying this domain.'),
                ]);
            }

            if (! $senderDomain->matchesAddress((string) $integration->test_from_address)) {
                throw ValidationException::withMessages([
                    'domain' => __('The tested From address no longer matches this sender domain.'),
                ]);
            }

            $senderDomain->forceFill([
                'verified_at' => now(),
                'verification_checked_at' => now(),
                'verified_email_integration_id' => $integration->id,
                'verified_email_integration_version' => $integration->verification_version,
            ])->save();

            $authorizedSenderCount = 0;

            $lockedTeam->senders()->get()->each(function (TeamSender $sender) use (
                $integration,
                $senderDomain,
                &$authorizedSenderCount,
            ): void {
                if ($sender->isVerifiedFor($integration) || ! $senderDomain->matchesAddress($sender->email)) {
                    return;
                }

                $sender->authorizeFor($integration, $senderDomain);
                $authorizedSenderCount++;
            });

            if ($lockedTeam->active_sender_id === null) {
                $defaultSender = $lockedTeam->senders()
                    ->authorizedForIntegration($integration)
                    ->oldest('id')
                    ->first();

                if ($defaultSender instanceof TeamSender) {
                    $lockedTeam->forceFill([
                        'active_sender_id' => $defaultSender->id,
                        'email_from_name' => $defaultSender->name,
                        'email_from_address' => $defaultSender->email,
                        'email_reply_to' => $defaultSender->reply_to,
                    ])->save();
                }
            }

            return $authorizedSenderCount;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice(
                'Domain verified. :count existing sender was authorized.|Domain verified. :count existing senders were authorized.',
                $authorizedSenderCount,
                ['count' => $authorizedSenderCount],
            ),
        ]);

        return back();
    }

    public function destroy(
        Request $request,
        Team $team,
        TeamSenderDomain $teamSenderDomain,
    ): RedirectResponse {
        $this->authorizeManagement($request, $team, $teamSenderDomain);

        DB::transaction(function () use ($team, $teamSenderDomain): void {
            $lockedTeam = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $senderDomain = $lockedTeam->senderDomains()
                ->whereKey($teamSenderDomain->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedTeam->senders()
                ->where('verified_sender_domain_id', $senderDomain->id)
                ->update([
                    'email_verified_at' => null,
                    'verification_sent_at' => null,
                    'verified_email_integration_id' => null,
                    'verified_email_integration_version' => null,
                    'verified_sender_domain_id' => null,
                ]);

            $senderDomain->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sender domain removed.')]);

        return back();
    }

    private function authorizeManagement(
        Request $request,
        Team $team,
        TeamSenderDomain $senderDomain,
    ): void {
        abort_unless($senderDomain->team_id === $team->id, 404);
        abort_unless($request->user()?->hasTeamPermission($team, TeamPermission::UpdateTeam), 403);
    }
}
