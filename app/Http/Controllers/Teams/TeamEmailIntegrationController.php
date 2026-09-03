<?php

namespace App\Http\Controllers\Teams;

use App\Enums\EmailProvider;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\DestroyTeamEmailIntegrationRequest;
use App\Http\Requests\Teams\SaveTeamEmailIntegrationRequest;
use App\Http\Requests\Teams\SendTestTeamEmailIntegrationRequest;
use App\Jobs\SendTeamEmailIntegrationTest;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class TeamEmailIntegrationController extends Controller
{
    public function edit(Request $request, Team $team): Response
    {
        Gate::authorize('update', $team);

        $integration = $team->emailIntegration()
            ->whereIn('provider', array_map(
                fn (EmailProvider $provider): string => $provider->value,
                EmailProvider::supported(),
            ))
            ->first();

        return Inertia::render('teams/email-provider', [
            'team' => $this->teamData($team),
            'providers' => EmailProvider::options(),
            'integration' => $integration instanceof TeamEmailIntegration
                ? $this->integrationData($integration)
                : null,
            'sender' => $this->senderData($team),
            'canManage' => $request->user()->hasTeamPermission($team, TeamPermission::UpdateTeam),
        ]);
    }

    public function create(Request $request, Team $team, EmailProvider $provider): Response|RedirectResponse
    {
        Gate::authorize('update', $team);
        abort_unless($provider->isSupported(), 404);

        $integration = $team->emailIntegration()->first();

        if ($integration instanceof TeamEmailIntegration) {
            return to_route('teams.email-provider.show', [$team, $integration]);
        }

        return $this->detailResponse($request, $team, $provider);
    }

    public function show(
        Request $request,
        Team $team,
        string $emailIntegration,
    ): Response|RedirectResponse {
        Gate::authorize('update', $team);

        $legacyProvider = EmailProvider::tryFrom($emailIntegration);

        if ($legacyProvider instanceof EmailProvider) {
            abort_unless($legacyProvider->isSupported(), 404);

            return to_route('teams.email-provider.create', [$team, $legacyProvider]);
        }

        abort_unless(Str::isUuid($emailIntegration), 404);

        $integration = $team->emailIntegration()
            ->where('uuid', $emailIntegration)
            ->firstOrFail();
        abort_unless($integration->provider->isSupported(), 404);

        return $this->detailResponse($request, $team, $integration->provider, $integration);
    }

    public function store(SaveTeamEmailIntegrationRequest $request, Team $team): RedirectResponse
    {
        $provider = $request->emailProvider();

        $integration = DB::transaction(function () use ($request, $team, $provider): TeamEmailIntegration {
            $lockedTeam = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();

            if ($lockedTeam->emailIntegration()->exists()) {
                throw ValidationException::withMessages([
                    'provider' => __('This workspace already has an email delivery connection.'),
                ]);
            }

            $integration = $lockedTeam->emailIntegration()->create([
                'name' => $request->connectionName(),
                'provider' => $provider,
                'settings' => $request->integrationSettings(),
                'trust_provider_senders' => $request->trustsProviderForSenders(),
                'connected_at' => now(),
            ]);

            return $integration;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email provider connected.')]);

        return to_route('teams.email-provider.show', [$team, $integration]);
    }

    public function update(
        SaveTeamEmailIntegrationRequest $request,
        Team $team,
        TeamEmailIntegration $emailIntegration,
    ): RedirectResponse {
        abort_unless($emailIntegration->provider->isSupported(), 404);

        DB::transaction(function () use ($request, $team, $emailIntegration): void {
            $lockedTeam = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $integration = $lockedTeam->emailIntegration()
                ->whereKey($emailIntegration->id)
                ->lockForUpdate()
                ->firstOrFail();
            $settings = $request->integrationSettings($integration);
            $settingsChanged = $integration->settings !== $settings || $integration->connected_at === null;
            $trustProviderSenders = $request->trustsProviderForSenders();
            $providerTrustDisabled = $integration->trust_provider_senders && ! $trustProviderSenders;

            $integration->name = $request->connectionName();
            $integration->settings = $settings;
            $integration->trust_provider_senders = $trustProviderSenders;

            if ($settingsChanged) {
                $integration->connected_at = Carbon::now();
                $integration->last_tested_at = null;
                $integration->test_from_address = null;
                $integration->verification_version++;
            }

            $integration->save();

            if ($providerTrustDisabled) {
                $providerTrustedSenders = $lockedTeam->senders()
                    ->where('verified_by_provider', true)
                    ->get(['id']);

                $lockedTeam->senders()
                    ->where('verified_by_provider', true)
                    ->update([
                        'email_verified_at' => null,
                        'verification_sent_at' => null,
                        'verified_email_integration_id' => null,
                        'verified_email_integration_version' => null,
                        'verified_sender_domain_id' => null,
                        'verified_by_provider' => false,
                    ]);

                if ($providerTrustedSenders->contains('id', $lockedTeam->active_sender_id)) {
                    $lockedTeam->forceFill([
                        'active_sender_id' => null,
                        'email_from_name' => null,
                        'email_from_address' => null,
                        'email_reply_to' => null,
                    ])->save();
                }
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email delivery settings updated.')]);

        return back();
    }

    public function test(
        SendTestTeamEmailIntegrationRequest $request,
        Team $team,
        TeamEmailIntegration $emailIntegration,
    ): RedirectResponse {
        abort_unless($emailIntegration->provider->isSupported(), 404);

        if (! $emailIntegration->hasCompleteConfiguration()) {
            return back()->withErrors([
                'to' => __('Complete the SES feedback settings before testing this connection.'),
            ], 'emailProviderTest');
        }

        if ($emailIntegration->connected_at === null) {
            return back()->withErrors([
                'to' => __('Connect an email provider before sending a test.'),
            ], 'emailProviderTest');
        }

        $configurationFingerprint = $this->configurationFingerprint($emailIntegration);

        SendTeamEmailIntegrationTest::dispatch(
            $team->id,
            $emailIntegration->id,
            $request->string('to')->value(),
            $request->string('from')->trim()->lower()->value(),
            $configurationFingerprint,
        )->afterCommit();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Test email queued for :address.', ['address' => $request->string('to')->value()]),
        ]);

        return back();
    }

    public function destroy(
        DestroyTeamEmailIntegrationRequest $request,
        Team $team,
        TeamEmailIntegration $emailIntegration,
    ): RedirectResponse {
        abort_unless($emailIntegration->provider->isSupported(), 404);

        DB::transaction(function () use ($team, $emailIntegration): void {
            $lockedTeam = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $integration = $lockedTeam->emailIntegration()
                ->whereKey($emailIntegration->id)
                ->lockForUpdate()
                ->firstOrFail();

            $integration->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email provider disconnected.')]);

        return to_route('teams.email-provider.edit', $team);
    }

    private function detailResponse(
        Request $request,
        Team $team,
        EmailProvider $provider,
        ?TeamEmailIntegration $integration = null,
    ): Response {
        abort_unless($provider->isSupported(), 404);

        return Inertia::render('teams/email-provider-show', [
            'team' => $this->teamData($team),
            'provider' => [
                'value' => $provider->value,
                'label' => $provider->label(),
                'description' => $provider->description(),
            ],
            'integration' => $integration instanceof TeamEmailIntegration
                ? $this->integrationData($integration)
                : null,
            'sender' => $this->senderData($team),
            'canManage' => $request->user()->hasTeamPermission($team, TeamPermission::UpdateTeam),
            'webhookUrl' => route('webhooks.aws.ses'),
        ]);
    }

    /**
     * @return array{id: int, uuid: string, name: string, slug: string, logo: string|null, isPersonal: bool}
     */
    private function teamData(Team $team): array
    {
        return [
            'id' => $team->id,
            'uuid' => $team->uuid,
            'name' => $team->name,
            'slug' => $team->slug,
            'logo' => $team->logo,
            'isPersonal' => $team->is_personal,
        ];
    }

    /** @return array{name: string|null, address: string|null, is_verified: bool} */
    private function senderData(Team $team): array
    {
        $integration = $team->emailIntegration()->first();
        $sender = $team->activeSender()->first();

        return [
            'name' => $team->email_from_name,
            'address' => $team->resolvedEmailFromAddress(),
            'is_verified' => $integration instanceof TeamEmailIntegration
                && $sender instanceof TeamSender
                && $sender->isVerifiedFor($integration),
        ];
    }

    /**
     * @return array{uuid: string, name: string, provider: string, provider_label: string, settings: array<string, int|string|null>, has_secret: bool, connected_at: string|null, last_tested_at: string|null, test_from_address: string|null, trust_provider_senders: bool, delivery_is_verified: bool, verified_sender_count: int, configuration_is_complete: bool}
     */
    private function integrationData(TeamEmailIntegration $integration): array
    {
        $settings = match ($integration->provider) {
            EmailProvider::Smtp => [
                'host' => $integration->settings['host'] ?? null,
                'port' => $integration->settings['port'] ?? 587,
                'username' => $integration->settings['username'] ?? null,
                'encryption' => $integration->settings['encryption'] ?? 'tls',
            ],
            EmailProvider::AmazonSes => [
                'region' => $integration->settings['region'] ?? null,
                'access_key_id' => $integration->settings['access_key_id'] ?? null,
                'configuration_set' => $integration->settings['configuration_set'] ?? null,
                'sns_topic_arn' => $integration->settings['sns_topic_arn'] ?? null,
            ],
            default => throw new LogicException('Unsupported email provider.'),
        };
        $secretKey = $integration->provider === EmailProvider::AmazonSes
            ? 'secret_access_key'
            : 'password';

        return [
            'uuid' => $integration->uuid,
            'name' => $this->integrationName($integration),
            'provider' => $integration->provider->value,
            'provider_label' => $integration->provider->label(),
            'settings' => $settings,
            'has_secret' => filled($integration->settings[$secretKey] ?? null),
            'connected_at' => $integration->connected_at?->toISOString(),
            'last_tested_at' => $integration->last_tested_at?->toISOString(),
            'test_from_address' => $integration->test_from_address,
            'trust_provider_senders' => $integration->trust_provider_senders,
            'delivery_is_verified' => $integration->isVerified(),
            'verified_sender_count' => $integration->verifiedSenders()->count(),
            'configuration_is_complete' => $integration->hasCompleteConfiguration(),
        ];
    }

    private function integrationName(TeamEmailIntegration $integration): string
    {
        return $integration->name ?: $integration->provider->label().' connection';
    }

    private function configurationFingerprint(TeamEmailIntegration $integration): string
    {
        return hash('sha256', serialize([
            $integration->provider->value,
            $integration->settings,
        ]));
    }
}
