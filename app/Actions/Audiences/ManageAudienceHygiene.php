<?php

namespace App\Actions\Audiences;

use App\Enums\SubscriberStatus;
use App\Models\Audience;
use App\Models\Subscriber;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ManageAudienceHygiene
{
    /** @return array{unconfirmed: int, inactive: int} */
    public function handle(Audience $audience): array
    {
        return [
            'unconfirmed' => $this->unconfirmedQuery($audience)->count(),
            'inactive' => $this->inactiveQuery($audience)->count(),
        ];
    }

    public function deleteUnconfirmed(Audience $audience): int
    {
        return $this->unconfirmedQuery($audience)->delete();
    }

    public function deleteInactive(Audience $audience): int
    {
        return $this->inactiveQuery($audience)->delete();
    }

    /**
     * @return list<array{
     *     uuid: string,
     *     name: string,
     *     avatar: string,
     *     subscribers_count: int,
     *     unconfirmed_count: int,
     *     inactive_count: int
     * }>
     */
    public function forTeam(Team $team): array
    {
        return array_values($team->audiences()
            ->select(['id', 'uuid', 'name'])
            ->withCount([
                'subscribers',
                'subscribers as unconfirmed_count' => fn (Builder $query): Builder => $this->applyUnconfirmedCriteria($query),
                'subscribers as inactive_count' => fn (Builder $query): Builder => $this->applyInactiveCriteria($query),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Audience $audience): array => [
                'uuid' => $audience->uuid,
                'name' => $audience->name,
                'avatar' => $audience->avatar,
                'subscribers_count' => (int) $audience->getAttribute('subscribers_count'),
                'unconfirmed_count' => (int) $audience->getAttribute('unconfirmed_count'),
                'inactive_count' => (int) $audience->getAttribute('inactive_count'),
            ])
            ->all());
    }

    /**
     * @return LengthAwarePaginator<int, covariant array{
     *     uuid: string,
     *     avatar: string,
     *     email: string,
     *     first_name: string|null,
     *     last_name: string|null,
     *     audience: array{uuid: string, name: string, avatar: string},
     *     created_at: string|null,
     *     last_sent_at: string|null
     * }>
     */
    public function paginateForTeam(
        Team $team,
        string $kind,
        string $audienceUuid,
        string $search,
    ): LengthAwarePaginator {
        $query = Subscriber::query()
            ->select([
                'id',
                'uuid',
                'audience_id',
                'email',
                'first_name',
                'last_name',
                'created_at',
            ])
            ->with('audience:id,uuid,name')
            ->withMax([
                'deliveries as last_sent_at' => fn (Builder $query): Builder => $query
                    ->whereNotNull('sent_at'),
            ], 'sent_at')
            ->whereIn(
                'audience_id',
                $team->audiences()
                    ->when(
                        $audienceUuid !== 'all',
                        fn (Builder $query): Builder => $query->where('uuid', $audienceUuid),
                    )
                    ->select('id'),
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $search = '%'.mb_strtolower($search).'%';
                $query->where(fn (Builder $subscriberQuery): Builder => $subscriberQuery
                    ->whereRaw('LOWER(email) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$search]));
            });

        $query = $kind === 'inactive'
            ? $this->applyInactiveCriteria($query)
            : $this->applyUnconfirmedCriteria($query);

        return $query
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Subscriber $subscriber) => $this->subscriberPayload($subscriber));
    }

    /** @param list<string> $subscriberUuids */
    public function deleteTeamUnconfirmed(Team $team, array $subscriberUuids): int
    {
        return $this->applyUnconfirmedCriteria(
            $this->teamSubscribersQuery($team, $subscriberUuids),
        )->delete();
    }

    /** @param list<string> $subscriberUuids */
    public function deleteTeamInactive(Team $team, array $subscriberUuids): int
    {
        return $this->applyInactiveCriteria(
            $this->teamSubscribersQuery($team, $subscriberUuids),
        )->delete();
    }

    /** @return Builder<Subscriber> */
    private function unconfirmedQuery(Audience $audience): Builder
    {
        return $this->applyUnconfirmedCriteria(
            Subscriber::query()->whereBelongsTo($audience),
        );
    }

    /** @return Builder<Subscriber> */
    private function inactiveQuery(Audience $audience): Builder
    {
        return $this->applyInactiveCriteria(
            Subscriber::query()->whereBelongsTo($audience),
        );
    }

    /**
     * @param  Builder<Subscriber>  $query
     * @return Builder<Subscriber>
     */
    private function applyUnconfirmedCriteria(Builder $query): Builder
    {
        return $query
            ->where('status', SubscriberStatus::Subscribed)
            ->whereNull('subscribed_at');
    }

    /**
     * @param  Builder<Subscriber>  $query
     * @return Builder<Subscriber>
     */
    private function applyInactiveCriteria(Builder $query): Builder
    {
        return $query
            ->where('status', SubscriberStatus::Subscribed)
            ->whereNotNull('subscribed_at')
            ->whereHas('deliveries', fn (Builder $query): Builder => $query->whereNotNull('sent_at'))
            ->whereDoesntHave('deliveries', fn (Builder $query): Builder => $query
                ->where(fn (Builder $engagementQuery): Builder => $engagementQuery
                    ->where('opens_count', '>', 0)
                    ->orWhere('clicks_count', '>', 0)));
    }

    /**
     * @param  list<string>  $subscriberUuids
     * @return Builder<Subscriber>
     */
    private function teamSubscribersQuery(Team $team, array $subscriberUuids): Builder
    {
        return Subscriber::query()
            ->whereIn(
                'audience_id',
                $team->audiences()->select('id'),
            )
            ->whereIn('uuid', $subscriberUuids);
    }

    private function dateToIsoString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->toISOString();
    }

    /**
     * @return array{
     *     uuid: string,
     *     avatar: string,
     *     email: string,
     *     first_name: string|null,
     *     last_name: string|null,
     *     audience: array{uuid: string, name: string, avatar: string},
     *     created_at: string|null,
     *     last_sent_at: string|null
     * }
     */
    private function subscriberPayload(Subscriber $subscriber): array
    {
        return [
            'uuid' => $subscriber->uuid,
            'avatar' => $subscriber->avatar,
            'email' => $subscriber->email,
            'first_name' => $subscriber->first_name,
            'last_name' => $subscriber->last_name,
            'audience' => [
                'uuid' => $subscriber->audience->uuid,
                'name' => $subscriber->audience->name,
                'avatar' => $subscriber->audience->avatar,
            ],
            'created_at' => $subscriber->created_at?->toISOString(),
            'last_sent_at' => $this->dateToIsoString($subscriber->getAttribute('last_sent_at')),
        ];
    }
}
