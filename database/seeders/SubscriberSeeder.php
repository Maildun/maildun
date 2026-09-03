<?php

namespace Database\Seeders;

use App\Actions\Audiences\SyncSegmentSubscribers;
use App\Models\Audience;
use App\Models\Segment;
use App\Models\SubscribeForm;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class SubscriberSeeder extends Seeder
{
    public function __construct(private SyncSegmentSubscribers $syncSegmentSubscribers) {}

    public function run(): void
    {
        $team = $this->team();

        if ($team === null) {
            return;
        }

        $tags = $team->tags()->whereIn('name', ['Newsletter', 'Customer', 'VIP', 'Beta'])->get()->keyBy('name');
        $newsletter = $this->audience($team, 'Product newsletter');
        $customers = $this->audience($team, 'Customer updates');
        $waitlist = $this->audience($team, 'Launch waitlist');
        $vip = $this->audience($team, 'VIP customers');

        if ($newsletter !== null) {
            $this->seedNewsletter($newsletter, $tags);
        }

        if ($customers !== null) {
            $this->seedList($customers, 12, daysAgo: 20, tag: $tags->get('Customer'));
        }

        if ($waitlist !== null) {
            $form = $waitlist->subscribeForms()->where('name', 'Launch landing page')->first();
            $this->seedList($waitlist, 8, daysAgo: 14, form: $form, tag: $tags->get('Beta'));
        }

        if ($vip !== null) {
            $this->seedList($vip, 5, daysAgo: 10, tag: $tags->get('VIP'));
        }

        Segment::query()
            ->whereIn('audience_id', $team->audiences()->select('id'))
            ->get()
            ->each(fn (Segment $segment) => $this->syncSegmentSubscribers->handle($segment));
    }

    private function team(): ?Team
    {
        return User::query()
            ->oldest('id')
            ->first()
            ?->personalTeam();
    }

    private function audience(Team $team, string $name): ?Audience
    {
        return $team->audiences()->where('name', $name)->first();
    }

    /**
     * @param  Collection<string, Tag>  $tags
     */
    private function seedNewsletter(Audience $audience, Collection $tags): void
    {
        if ($audience->subscribers()->exists()) {
            return;
        }

        $form = $audience->subscribeForms()->where('name', 'Website footer')->first();

        $this->createJoins($audience, 18, startDay: 55, endDay: 28, form: $form, tag: $tags->get('Newsletter'), formEvery: 3);
        $this->createJoins($audience, 30, startDay: 27, endDay: 0, form: $form, tag: $tags->get('Newsletter'), formEvery: 3);
        $this->createUnsubscribed($audience, 6, startDay: 50, endDay: 8, form: $form, tag: $tags->get('Customer'));
        $this->createUnsubscribed($audience, 6, startDay: 20, endDay: 2, form: $form, tag: $tags->get('Customer'));
    }

    private function seedList(
        Audience $audience,
        int $count,
        int $daysAgo,
        ?SubscribeForm $form = null,
        ?Tag $tag = null,
    ): void {
        if ($audience->subscribers()->exists()) {
            return;
        }

        $this->createJoins($audience, $count, startDay: $daysAgo, endDay: 0, form: $form, tag: $tag, formEvery: $form ? 1 : 0);
    }

    private function createJoins(
        Audience $audience,
        int $count,
        int $startDay,
        int $endDay,
        ?SubscribeForm $form,
        ?Tag $tag,
        int $formEvery,
    ): void {
        foreach (range(0, $count - 1) as $index) {
            $joinedAt = $this->joinedAt($startDay, $endDay, $index, $count);

            $factory = Subscriber::factory()
                ->for($audience)
                ->joinedAt($joinedAt);

            if ($form !== null && $formEvery > 0 && $index % $formEvery === 0) {
                $factory = $factory->fromForm($form);
            }

            $subscriber = $factory->create();

            if ($tag !== null && $index % 2 === 0) {
                $subscriber->tags()->attach($tag);
            }
        }
    }

    private function createUnsubscribed(
        Audience $audience,
        int $count,
        int $startDay,
        int $endDay,
        ?SubscribeForm $form,
        ?Tag $tag,
    ): void {
        foreach (range(0, $count - 1) as $index) {
            $joinedAt = $this->joinedAt($startDay, $endDay, $index, $count);
            $unsubscribedAt = $joinedAt->copy()->addDays(min(4, max($endDay, 1)));

            if ($unsubscribedAt->greaterThan(now())) {
                $unsubscribedAt = now();
            }

            $factory = Subscriber::factory()
                ->for($audience)
                ->joinedAt($joinedAt)
                ->unsubscribed($unsubscribedAt);

            if ($form !== null && $index % 2 === 0) {
                $factory = $factory->fromForm($form);
            }

            $subscriber = $factory->create();

            if ($tag !== null) {
                $subscriber->tags()->attach($tag);
            }
        }
    }

    private function joinedAt(int $startDay, int $endDay, int $index, int $count): CarbonInterface
    {
        $span = max($startDay - $endDay, 1);
        $offset = (int) floor($index * $span / max($count - 1, 1));

        return now()->subDays($startDay - $offset)->subHours($index % 12);
    }
}
