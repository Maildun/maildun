<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\TeamEmailIntegration;
use Illuminate\Contracts\Database\Eloquent\Builder;

class BuildOnboardingChecklist
{
    /**
     * The checklist step keys, in the order they are shown in the sidebar.
     *
     * @var list<string>
     */
    public const STEPS = [
        'delivery',
        'sender',
        'audience',
        'subscribers',
        'campaign',
        'automation',
    ];

    /**
     * Build the getting started checklist for a team.
     *
     * @return array{
     *     completed: int,
     *     total: int,
     *     steps: list<array{key: string, completed: bool}>
     * }
     */
    public function handle(Team $team): array
    {
        $flags = Team::query()
            ->whereKey($team->getKey())
            ->with('emailIntegration')
            ->withExists([
                'audiences as has_audience',
                'subscribers as has_subscribers',
                'emails as has_campaign' => fn (Builder $query): Builder => $query->whereNotNull('sent_at'),
                'automations as has_automation',
            ])
            ->firstOrFail();

        $integration = $flags->emailIntegration;
        $deliveryIsReady = $integration instanceof TeamEmailIntegration
            && $integration->isVerified();

        $completedByKey = [
            'delivery' => $deliveryIsReady,
            'sender' => $deliveryIsReady
                && $flags->active_sender_id !== null
                && $integration->isSenderAuthorizedFor($flags->resolvedEmailFromAddress()),
            'audience' => (bool) $flags->getAttribute('has_audience'),
            'subscribers' => (bool) $flags->getAttribute('has_subscribers'),
            'campaign' => (bool) $flags->getAttribute('has_campaign'),
            'automation' => (bool) $flags->getAttribute('has_automation'),
        ];

        $steps = array_map(
            fn (string $key): array => ['key' => $key, 'completed' => $completedByKey[$key]],
            self::STEPS,
        );

        return [
            'completed' => count(array_filter($completedByKey)),
            'total' => count(self::STEPS),
            'steps' => $steps,
        ];
    }
}
