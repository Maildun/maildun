<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\StoreTeamSenderRequest;
use App\Http\Requests\Teams\UpdateTeamSenderRequest;
use App\Jobs\SendTeamSenderVerification;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSender;
use App\Models\TeamSenderDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TeamSenderSettingsController extends Controller
{
    public function edit(Request $request, Team $team): Response
    {
        Gate::authorize('update', $team);
        $integration = $team->emailIntegration()->first();

        return Inertia::render('teams/sender', [
            'team' => [
                'id' => $team->id,
                'uuid' => $team->uuid,
                'name' => $team->name,
                'slug' => $team->slug,
                'logo' => $team->logo,
                'isPersonal' => $team->is_personal,
            ],
            'senders' => $team->senders()
                ->orderByDesc('email_verified_at')
                ->oldest('id')
                ->get()
                ->map(fn (TeamSender $sender): array => [
                    'uuid' => $sender->uuid,
                    'name' => $sender->name,
                    'email' => $sender->email,
                    'reply_to' => $sender->reply_to,
                    'is_verified' => $integration !== null && $sender->isVerifiedFor($integration),
                    'was_verified' => $sender->email_verified_at !== null,
                    'verification_sent_at' => $sender->verification_sent_at?->toISOString(),
                    'is_default' => $team->active_sender_id === $sender->id,
                ])
                ->values()
                ->all(),
            'domains' => $team->senderDomains()
                ->oldest('id')
                ->get()
                ->map(fn (TeamSenderDomain $senderDomain): array => [
                    'uuid' => $senderDomain->uuid,
                    'domain' => $senderDomain->domain,
                    'dns_record_name' => $senderDomain->dnsRecordName(),
                    'dns_record_value' => $senderDomain->dnsRecordValue(),
                    'is_verified' => $integration !== null && $senderDomain->isVerifiedFor($integration),
                    'was_verified' => $senderDomain->verified_at !== null,
                    'verified_at' => $senderDomain->verified_at?->toISOString(),
                    'verification_checked_at' => $senderDomain->verification_checked_at?->toISOString(),
                ])
                ->values()
                ->all(),
            'delivery' => [
                'configured' => $integration !== null,
                'verified' => $integration?->isVerified() === true,
                'trust_provider_senders' => $integration?->trust_provider_senders === true,
            ],
            'canManage' => $request->user()->hasTeamPermission($team, TeamPermission::UpdateTeam),
        ]);
    }

    public function store(StoreTeamSenderRequest $request, Team $team): RedirectResponse
    {
        [$sender, $integration, $senderDomain, $providerTrusted] = DB::transaction(function () use ($request, $team): array {
            $lockedTeam = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $integration = $lockedTeam->emailIntegration()->lockForUpdate()->first();

            if ($integration?->isVerified() !== true) {
                throw ValidationException::withMessages([
                    'email' => __('Verify email delivery before adding a workspace sender.'),
                ]);
            }

            $sender = $lockedTeam->senders()->create($request->validated());
            $senderDomain = $this->verifiedDomainForAddress($lockedTeam, $integration, $sender->email);

            if ($senderDomain instanceof TeamSenderDomain) {
                $sender->authorizeFor($integration, $senderDomain);
            } elseif ($integration->trustsProviderForSender($sender->email)) {
                $sender->authorizeFor($integration, trustedProvider: true);
            }

            if ($lockedTeam->active_sender_id === null && $sender->email_verified_at !== null) {
                $this->applySender($lockedTeam, $sender);
            }

            return [$sender, $integration, $senderDomain, $sender->verified_by_provider];
        });

        if (! $senderDomain instanceof TeamSenderDomain && ! $providerTrusted) {
            SendTeamSenderVerification::dispatch(
                $sender->id,
                $integration->id,
                $integration->verification_version,
            )->afterCommit();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => match (true) {
                $senderDomain instanceof TeamSenderDomain => __('Sender authorized through the verified :domain domain.', ['domain' => $senderDomain->domain]),
                $providerTrusted => __('Sender authorized by the trusted provider for :address.', ['address' => $sender->email]),
                default => __('Verification email queued for :address.', ['address' => $sender->email]),
            },
        ]);

        return back();
    }

    public function update(UpdateTeamSenderRequest $request, Team $team, TeamSender $teamSender): RedirectResponse
    {
        DB::transaction(function () use ($request, $team, $teamSender): void {
            $lockedTeam = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $sender = $lockedTeam->senders()->whereKey($teamSender->id)->lockForUpdate()->firstOrFail();
            $sender->update($request->validated());

            if ($lockedTeam->active_sender_id === $sender->id) {
                $this->applySender($lockedTeam, $sender);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sender details updated.')]);

        return back();
    }

    public function resend(Request $request, Team $team, TeamSender $teamSender): RedirectResponse
    {
        $this->authorizeManagement($request, $team, $teamSender);

        $integration = $team->emailIntegration()->first();

        if ($integration?->isVerified() !== true) {
            throw ValidationException::withMessages([
                'sender' => __('Verify email delivery before testing this sender.'),
            ]);
        }

        if ($teamSender->isVerifiedFor($integration)) {
            throw ValidationException::withMessages(['sender' => __('This sender is already verified.')]);
        }

        $senderDomain = $this->verifiedDomainForAddress($team, $integration, $teamSender->email);

        if ($senderDomain instanceof TeamSenderDomain) {
            DB::transaction(function () use ($team, $teamSender, $integration, $senderDomain): void {
                $sender = $team->senders()->whereKey($teamSender->id)->lockForUpdate()->firstOrFail();
                $sender->authorizeFor($integration, $senderDomain);
            });

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Sender authorized through the verified :domain domain.', ['domain' => $senderDomain->domain]),
            ]);

            return back();
        }

        if ($integration->trustsProviderForSender($teamSender->email)) {
            DB::transaction(function () use ($team, $teamSender, $integration): void {
                $sender = $team->senders()->whereKey($teamSender->id)->lockForUpdate()->firstOrFail();
                $sender->authorizeFor($integration, trustedProvider: true);
            });

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Sender authorized by the trusted provider for :address.', ['address' => $teamSender->email]),
            ]);

            return back();
        }

        SendTeamSenderVerification::dispatch(
            $teamSender->id,
            $integration->id,
            $integration->verification_version,
        )->afterCommit();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Verification email queued for :address.', ['address' => $teamSender->email]),
        ]);

        return back();
    }

    public function makeDefault(Request $request, Team $team, TeamSender $teamSender): RedirectResponse
    {
        $this->authorizeManagement($request, $team, $teamSender);

        $integration = $team->emailIntegration()->first();

        if ($integration === null || ! $teamSender->isVerifiedFor($integration)) {
            throw ValidationException::withMessages(['sender' => __('Verify this sender before making it the default.')]);
        }

        DB::transaction(function () use ($team, $teamSender): void {
            $lockedTeam = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $sender = $lockedTeam->senders()->whereKey($teamSender->id)->lockForUpdate()->firstOrFail();
            $this->applySender($lockedTeam, $sender);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Default sender updated.')]);

        return back();
    }

    public function destroy(Request $request, Team $team, TeamSender $teamSender): RedirectResponse
    {
        $this->authorizeManagement($request, $team, $teamSender);

        DB::transaction(function () use ($team, $teamSender): void {
            $lockedTeam = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $sender = $lockedTeam->senders()->whereKey($teamSender->id)->lockForUpdate()->firstOrFail();

            if ($lockedTeam->active_sender_id === $sender->id) {
                $lockedTeam->forceFill([
                    'active_sender_id' => null,
                    'email_from_name' => null,
                    'email_from_address' => null,
                    'email_reply_to' => null,
                ])->save();
            }

            $sender->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sender removed.')]);

        return back();
    }

    private function authorizeManagement(Request $request, Team $team, TeamSender $sender): void
    {
        abort_unless($sender->team_id === $team->id, 404);
        abort_unless($request->user()?->hasTeamPermission($team, TeamPermission::UpdateTeam), 403);
    }

    private function applySender(Team $team, TeamSender $sender): void
    {
        $team->forceFill([
            'active_sender_id' => $sender->id,
            'email_from_name' => $sender->name,
            'email_from_address' => $sender->email,
            'email_reply_to' => $sender->reply_to,
        ])->save();
    }

    private function verifiedDomainForAddress(
        Team $team,
        TeamEmailIntegration $integration,
        string $address,
    ): ?TeamSenderDomain {
        $domain = TeamSenderDomain::fromEmail($address);

        if ($domain === null) {
            return null;
        }

        $senderDomain = $team->senderDomains()->where('domain', $domain)->first();

        return $senderDomain instanceof TeamSenderDomain && $senderDomain->isVerifiedFor($integration)
            ? $senderDomain
            : null;
    }
}
