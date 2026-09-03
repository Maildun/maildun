<?php

namespace App\Http\Controllers;

use App\Enums\AutomationStatus;
use App\Enums\SubscriberStatus;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\TeamInvitation;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Team $currentTeam): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        return Inertia::render('dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'dashboard' => $this->dashboardData($currentTeam),
        ]);
    }

    /**
     * @return array{
     *     overview: array{
     *         subscribers: int,
     *         newSubscribers: int,
     *         deliveryRate: float|null,
     *         activeAutomations: int
     *     },
     *     subscriberGrowth: list<array{date: string, subscribers: int}>,
     *     recentCampaigns: list<array{
     *         uuid: string,
     *         name: string,
     *         status: string,
     *         recipients: int,
     *         delivered: int,
     *         sentAt: string|null
     *     }>
     * }
     */
    private function dashboardData(Team $team): array
    {
        $today = now()->startOfDay();
        $periodStart = $today->copy()->subDays(29);
        $subscribers = $this->subscribersFor($team);

        $deliveryStats = EmailDelivery::query()
            ->whereHas('email', fn (Builder $query): Builder => $query->where('team_id', $team->id))
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', $periodStart)
            ->toBase()
            ->selectRaw('count(*) as sent')
            ->selectRaw('count(case when delivered_at is not null then 1 end) as delivered')
            ->first();

        $sent = (int) ($deliveryStats->sent ?? 0);
        $delivered = (int) ($deliveryStats->delivered ?? 0);

        return [
            'overview' => [
                'subscribers' => (clone $subscribers)
                    ->where('status', SubscriberStatus::Subscribed)
                    ->count(),
                'newSubscribers' => (clone $subscribers)
                    ->whereNotNull('subscribed_at')
                    ->where('subscribed_at', '>=', $periodStart)
                    ->count(),
                'deliveryRate' => $sent === 0
                    ? null
                    : round(($delivered / $sent) * 100, 1),
                'activeAutomations' => $team->automations()
                    ->where('status', AutomationStatus::Active)
                    ->count(),
            ],
            'subscriberGrowth' => $this->subscriberGrowth($subscribers, $periodStart, $today),
            'recentCampaigns' => array_values($team->emails()
                ->select([
                    'id',
                    'uuid',
                    'name',
                    'status',
                    'recipient_count',
                    'sent_at',
                    'send_started_at',
                ])
                ->withCount([
                    'deliveries as delivered_count' => fn (Builder $query): Builder => $query->whereNotNull('delivered_at'),
                ])
                ->whereNotNull('send_started_at')
                ->latest('send_started_at')
                ->limit(5)
                ->get()
                ->map(fn (Email $campaign): array => [
                    'uuid' => $campaign->uuid,
                    'name' => $campaign->name,
                    'status' => $campaign->status->value,
                    'recipients' => $campaign->recipient_count,
                    'delivered' => (int) $campaign->getAttribute('delivered_count'),
                    'sentAt' => ($campaign->sent_at ?? $campaign->send_started_at)?->toIso8601String(),
                ])
                ->all()),
        ];
    }

    /**
     * @return Builder<Subscriber>
     */
    private function subscribersFor(Team $team): Builder
    {
        return Subscriber::query()
            ->whereIn('audience_id', $team->audiences()->select('id'));
    }

    /**
     * @param  Builder<Subscriber>  $subscribers
     * @return list<array{date: string, subscribers: int}>
     */
    private function subscriberGrowth(Builder $subscribers, CarbonInterface $from, CarbonInterface $to): array
    {
        $subscribedByDay = (clone $subscribers)
            ->toBase()
            ->selectRaw('DATE(subscribed_at) as day, count(*) as aggregate')
            ->whereNotNull('subscribed_at')
            ->where('subscribed_at', '>=', $from)
            ->groupByRaw('DATE(subscribed_at)')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                Carbon::parse($row->day)->toDateString() => (int) $row->aggregate,
            ]);

        $growth = [];

        foreach (CarbonPeriod::create($from, $to) as $date) {
            $growth[] = [
                'date' => $date->toDateString(),
                'subscribers' => $subscribedByDay->get($date->toDateString(), 0),
            ];
        }

        return $growth;
    }
}
