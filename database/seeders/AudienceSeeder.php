<?php

namespace Database\Seeders;

use App\Enums\SegmentMatchType;
use App\Models\Audience;
use App\Models\Segment;
use App\Models\SubscribeForm;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class AudienceSeeder extends Seeder
{
    public function run(): void
    {
        $team = $this->team();

        if ($team === null) {
            return;
        }

        $this->tags($team);

        $newsletter = $this->audience(
            $team,
            'Product newsletter',
            'Product releases, tips, and company news.',
        );
        $this->audience(
            $team,
            'Customer updates',
            'Account and billing notices for paying customers.',
        );
        $waitlist = $this->audience(
            $team,
            'Launch waitlist',
            'People waiting for the next product launch.',
        );
        $vip = $this->audience(
            $team,
            'VIP customers',
            'High-touch accounts and partners.',
        );
        $this->audience(
            $team,
            'Internal testers',
            'Empty list reserved for staff QA.',
        );

        $this->form($newsletter, 'Website footer', published: true);
        $this->form($waitlist, 'Launch landing page', published: true);
        $this->form($waitlist, 'Draft popup', published: false);

        $this->segment(
            $newsletter,
            'Subscribed contacts',
            'Everyone currently opted in.',
            [['field' => 'status', 'operator' => 'equals', 'value' => 'subscribed']],
        );
        $this->segment(
            $vip,
            'Recently joined',
            'VIP contacts added in the last month.',
            [['field' => 'subscribed_at', 'operator' => 'after', 'value' => now()->subMonth()->toDateString()]],
        );
    }

    private function team(): ?Team
    {
        return User::query()
            ->oldest('id')
            ->first()
            ?->personalTeam();
    }

    /**
     * @return array<string, Tag>
     */
    private function tags(Team $team): array
    {
        $colors = [
            'Newsletter' => '#0ea5e9',
            'Customer' => '#22c55e',
            'VIP' => '#a855f7',
            'Beta' => '#f97316',
        ];

        return collect($colors)
            ->mapWithKeys(fn (string $color, string $name): array => [
                $name => $team->tags()->firstOrCreate(
                    ['name' => $name],
                    ['color' => $color],
                ),
            ])
            ->all();
    }

    private function audience(Team $team, string $name, string $description): Audience
    {
        return $team->audiences()->firstOrCreate(
            ['name' => $name],
            ['description' => $description],
        );
    }

    private function form(Audience $audience, string $name, bool $published): SubscribeForm
    {
        return $audience->subscribeForms()->firstOrCreate(
            ['name' => $name],
            [
                'headline' => $name === 'Launch landing page'
                    ? 'Get launch updates'
                    : 'Join our newsletter',
                'description' => 'Subscribe to hear from Maildun.',
                'consent_text' => 'I agree to receive marketing emails.',
                'published_at' => $published ? now()->subWeeks(6) : null,
            ],
        );
    }

    /**
     * @param  list<array{field: string, operator: string, value: string}>  $rules
     */
    private function segment(Audience $audience, string $name, string $description, array $rules): Segment
    {
        return $audience->segments()->firstOrCreate(
            ['name' => $name],
            [
                'description' => $description,
                'match_type' => SegmentMatchType::All,
                'rules' => $rules,
            ],
        );
    }
}
