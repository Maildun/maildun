<?php

namespace App\Http\Controllers;

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailProvider;
use App\Enums\EmailStatus;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** @var list<int> */
    private const PERIODS = [7, 30, 90];

    public function __invoke(Request $request, Team $currentTeam): Response
    {
        $period = $this->period($request);

        return Inertia::render('dashboard', [
            'pendingInvitations' => $this->pendingInvitations($request->user()),
            'canManageCampaigns' => Gate::allows('create', [Email::class, $currentTeam]),
            'dashboard' => $this->dashboardData($currentTeam, $period),
        ]);
    }

    /**
     * Invitations are matched on the address alone, so they are only listed,
     * codes included, once the user has proven they own it.
     *
     * @return Collection<int, array{code: string, inviterName: string, team: array{name: string, slug: string}}>
     */
    private function pendingInvitations(User $user): Collection
    {
        if (! $user->hasVerifiedEmail()) {
            return collect();
        }

        return TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
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
    }

    /**
     * @return array{
     *     overview: array{
     *         subscribers: int,
     *         newSubscribers: int,
     *         deliveryRate: float|null,
     *         openRate: float|null,
     *         clickRate: float|null
     *     },
     *     period: int,
     *     performance: list<array{date: string, subscribers: int, sent: int, opens: int, clicks: int}>,
     *     deliveryIssues: array{failed: int, bounced: int, complained: int},
     *     draftCampaigns: list<array{uuid: string, name: string, updatedAt: string|null}>,
     *     recentCampaigns: list<array{
     *         uuid: string,
     *         name: string,
     *         status: string,
     *         recipients: int,
     *         delivered: int,
     *         deliveryReported: bool,
     *         opened: int,
     *         clicked: int,
     *         deliveryRate: float|null,
     *         openRate: float|null,
     *         clickRate: float|null,
     *         sentAt: string|null
     *     }>
     * }
     */
    private function dashboardData(Team $team, int $period): array
    {
        $today = now()->startOfDay();
        $periodStart = $today->copy()->subDays($period - 1);
        $subscribers = $this->subscribersFor($team);
        $deliveries = EmailDelivery::query()
            ->whereHas('email', fn (Builder $query): Builder => $query->where('team_id', $team->id));
        $sesProviders = array_map(
            fn (EmailProvider $provider): string => $provider->value,
            EmailProvider::withSesFeedback(),
        );

        $deliveryStats = (clone $deliveries)
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', $periodStart)
            ->toBase()
            ->selectRaw('count(*) as sent')
            ->selectRaw('count(case when provider in ('.implode(', ', array_fill(0, count($sesProviders), '?')).') then 1 end) as ses_sent', $sesProviders)
            ->selectRaw('count(case when delivered_at is not null then 1 end) as delivered')
            ->selectRaw('count(case when first_opened_at is not null then 1 end) as opened')
            ->selectRaw('count(case when first_clicked_at is not null then 1 end) as clicked')
            ->selectRaw('count(case when bounced_at is not null then 1 end) as bounced')
            ->selectRaw('count(case when complained_at is not null then 1 end) as complained')
            ->first();

        $sent = (int) ($deliveryStats->sent ?? 0);
        // Only Amazon SES reports delivery, so a rate over SMTP sends would
        // read as 0% rather than unknown.
        $sesSent = (int) ($deliveryStats->ses_sent ?? 0);
        $delivered = (int) ($deliveryStats->delivered ?? 0);
        $opened = (int) ($deliveryStats->opened ?? 0);
        $clicked = (int) ($deliveryStats->clicked ?? 0);

        $failed = (clone $deliveries)
            ->where('send_attempted_at', '>=', $periodStart)
            ->whereIn('status', EmailDeliveryStatus::retryable())
            ->count();

        return [
            'period' => $period,
            'overview' => [
                // The people a campaign could mail today: confirmed, not
                // suppressed, and counted once however many audiences hold them.
                'subscribers' => (clone $subscribers)
                    ->sendableFor($team)
                    ->distinct()
                    ->count('subscribers.email'),
                'newSubscribers' => (clone $subscribers)
                    ->whereNotNull('subscribed_at')
                    ->where('subscribed_at', '>=', $periodStart)
                    ->count(),
                'deliveryRate' => $sesSent === 0
                    ? null
                    : round(($delivered / $sesSent) * 100, 1),
                'openRate' => $sent === 0
                    ? null
                    : round(($opened / $sent) * 100, 1),
                'clickRate' => $sent === 0
                    ? null
                    : round(($clicked / $sent) * 100, 1),
            ],
            'deliveryIssues' => [
                'failed' => $failed,
                'bounced' => (int) ($deliveryStats->bounced ?? 0),
                'complained' => (int) ($deliveryStats->complained ?? 0),
            ],
            'performance' => $this->performance($subscribers, $deliveries, $periodStart, $today),
            'draftCampaigns' => array_values($team->emails()
                ->select(['uuid', 'name', 'updated_at'])
                ->where('status', EmailStatus::Draft)
                ->whereNull('sent_at')
                ->latest('updated_at')
                ->limit(4)
                ->get()
                ->map(fn (Email $campaign): array => [
                    'uuid' => $campaign->uuid,
                    'name' => $campaign->name,
                    'updatedAt' => $campaign->updated_at?->toIso8601String(),
                ])
                ->all()),
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
                    'deliveries as ses_delivery_count' => fn (Builder $query): Builder => $query->whereIn('provider', $sesProviders),
                    'deliveries as opened_count' => fn (Builder $query): Builder => $query->whereNotNull('first_opened_at'),
                    'deliveries as clicked_count' => fn (Builder $query): Builder => $query->whereNotNull('first_clicked_at'),
                ])
                ->whereNotNull('send_started_at')
                ->latest('send_started_at')
                ->limit(5)
                ->get()
                ->map(function (Email $campaign): array {
                    $recipients = max($campaign->recipient_count, 0);
                    $delivered = (int) $campaign->getAttribute('delivered_count');
                    $deliveryReported = (int) $campaign->getAttribute('ses_delivery_count') > 0;
                    $opened = (int) $campaign->getAttribute('opened_count');
                    $clicked = (int) $campaign->getAttribute('clicked_count');

                    return [
                        'uuid' => $campaign->uuid,
                        'name' => $campaign->name,
                        'status' => $campaign->status->value,
                        'recipients' => $recipients,
                        'delivered' => $delivered,
                        'deliveryReported' => $deliveryReported,
                        'opened' => $opened,
                        'clicked' => $clicked,
                        'deliveryRate' => $recipients === 0 || ! $deliveryReported ? null : round(($delivered / $recipients) * 100, 1),
                        'openRate' => $recipients === 0 ? null : round(($opened / $recipients) * 100, 1),
                        'clickRate' => $recipients === 0 ? null : round(($clicked / $recipients) * 100, 1),
                        'sentAt' => ($campaign->sent_at ?? $campaign->send_started_at)?->toIso8601String(),
                    ];
                })
                ->all()),
        ];
    }

    private function period(Request $request): int
    {
        $period = $request->integer('period', 30);

        return in_array($period, self::PERIODS, true) ? $period : 30;
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
     * @param  Builder<EmailDelivery>  $deliveries
     * @return list<array{date: string, subscribers: int, sent: int, opens: int, clicks: int}>
     */
    private function performance(Builder $subscribers, Builder $deliveries, CarbonInterface $from, CarbonInterface $to): array
    {
        $subscribedByDay = $this->dailyCounts($subscribers, 'subscribed_at', $from);
        $sentByDay = $this->dailyCounts($deliveries, 'sent_at', $from);
        $openedByDay = $this->dailyCounts($deliveries, 'first_opened_at', $from);
        $clickedByDay = $this->dailyCounts($deliveries, 'first_clicked_at', $from);

        $performance = [];

        foreach (CarbonPeriod::create($from, $to) as $date) {
            $day = $date->toDateString();
            $performance[] = [
                'date' => $day,
                'subscribers' => $subscribedByDay->get($day, 0),
                'sent' => $sentByDay->get($day, 0),
                'opens' => $openedByDay->get($day, 0),
                'clicks' => $clickedByDay->get($day, 0),
            ];
        }

        return $performance;
    }

    /**
     * The column name is interpolated straight into raw SQL, so it must be a
     * literal from this class and never request input.
     *
     * @param  Builder<*>  $query
     * @param  literal-string  $column
     * @return Collection<string, int>
     */
    private function dailyCounts(Builder $query, string $column, CarbonInterface $from): Collection
    {
        return (clone $query)
            ->toBase()
            ->selectRaw("DATE({$column}) as day, count(*) as aggregate")
            ->whereNotNull($column)
            ->where($column, '>=', $from)
            ->groupByRaw("DATE({$column})")
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                Carbon::parse($row->day)->toDateString() => (int) $row->aggregate,
            ]);
    }
}
