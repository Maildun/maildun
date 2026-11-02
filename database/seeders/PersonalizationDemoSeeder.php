<?php

namespace Database\Seeders;

use App\Actions\Emails\ResolveConditionalSections;
use App\Enums\AudienceAttributeType;
use App\Enums\EmailEditor;
use App\Enums\EmailStatus;
use App\Models\Audience;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use App\Services\EmailBuilderAgent;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local test data for personalized sections: subscribers of the "Customer
 * updates" audience spread across plans, each plan stored in a Plan field
 * and as a "… plan" tag, and a draft block campaign whose sections target
 * those tags. Idempotent; reruns rebuild the campaign while it is a draft.
 */
class PersonalizationDemoSeeder extends Seeder
{
    /**
     * Plans handed out in turn; null leaves a subscriber without one.
     *
     * @var list<string|null>
     */
    private const array PLANS = ['Pro', 'Free', 'Team', null];

    private const string CAMPAIGN = 'Personalized sections demo';

    public function __construct(private EmailBuilderAgent $builder) {}

    public function run(): void
    {
        $this->call([AudienceSeeder::class, SubscriberSeeder::class]);

        $team = User::query()->oldest('id')->first()?->personalTeam();
        $audience = $team?->audiences()->where('name', 'Customer updates')->first();

        if ($team === null || $audience === null) {
            return;
        }

        $audience->audienceAttributes()->firstOrCreate(
            ['key' => 'plan'],
            ['name' => 'Plan', 'type' => AudienceAttributeType::Text],
        );

        $tags = collect(['Pro', 'Free', 'Team'])->mapWithKeys(fn (string $plan): array => [
            $plan => $team->tags()->firstOrCreate(['name' => "{$plan} plan"], ['color' => '#6366f1']),
        ]);

        $this->assignPlans($audience, $tags->all());
        $this->campaign($team, $audience, $tags->all());
    }

    /**
     * @param  array<string, Tag>  $tags
     */
    private function assignPlans(Audience $audience, array $tags): void
    {
        $audience->subscribers()
            ->oldest('id')
            ->get()
            ->each(function (Subscriber $subscriber, int $index) use ($tags): void {
                $values = $subscriber->attribute_values ?? [];
                $stored = $values['plan'] ?? null;
                $plan = is_string($stored) ? $stored : self::PLANS[$index % count(self::PLANS)];

                if ($plan === null) {
                    return;
                }

                if (! is_string($stored)) {
                    $subscriber->update(['attribute_values' => [...$values, 'plan' => $plan]]);
                }

                if (isset($tags[$plan])) {
                    $subscriber->tags()->syncWithoutDetaching([$tags[$plan]->id]);
                }
            });
    }

    /**
     * @param  array<string, Tag>  $tags
     */
    private function campaign(Team $team, Audience $audience, array $tags): void
    {
        $existing = $team->emails()->where('name', self::CAMPAIGN)->first();

        if ($existing !== null && $existing->status !== EmailStatus::Draft) {
            return;
        }

        $tag = fn (string $plan, bool $not = false): array => array_filter([
            'id' => ResolveConditionalSections::tagId($tags[$plan]->uuid),
            'label' => $tags[$plan]->name,
            'not' => $not ?: null,
        ]);

        $rendered = $this->builder->run(null, null, 'replace_document', [
            'blocks' => [
                ['type' => 'heading', 'props' => ['text' => 'Hi {{ first_name }}, here is your update', 'level' => 1]],
                ['type' => 'text', 'props' => ['markdown' => 'Everyone sees this paragraph.']],
                $this->section('Pro perks', 'Thanks for being on **Pro**. Your priority support line is open.', [$tag('Pro')]),
                $this->section('Upgrade offer', 'You are on the **{{ plan }}** plan. Upgrade to Pro for priority support.', [
                    $tag('Free'),
                    $tag('Team'),
                ], match: 'any'),
                $this->section('Pick a plan', 'You have not picked a plan yet. Choose one to get started.', [
                    $tag('Pro', not: true),
                    $tag('Free', not: true),
                    $tag('Team', not: true),
                ]),
                ['type' => 'text', 'props' => ['markdown' => '[Unsubscribe]({{ unsubscribe_url }})']],
            ],
        ]);

        if (($rendered['ok'] ?? false) !== true) {
            throw new RuntimeException('The email builder could not render the demo campaign: '.json_encode($rendered['result'] ?? $rendered));
        }

        $team->emails()->updateOrCreate(['name' => self::CAMPAIGN], [
            'subject' => 'Your plan update',
            'audience_id' => $audience->id,
            'editor' => EmailEditor::Builder,
            'design' => $rendered['document'],
            'html' => $rendered['html'],
            'plain_text' => $rendered['text'],
        ]);
    }

    /**
     * A container only readers who pass the conditions see.
     *
     * @param  list<array<string, mixed>>  $conditions
     * @return array<string, mixed>
     */
    private function section(string $title, string $markdown, array $conditions, string $match = 'all'): array
    {
        return [
            'type' => 'container',
            'props' => ['card' => true, 'visibleIf' => ['match' => $match, 'conditions' => $conditions]],
            'style' => ['backgroundColor' => '$surface', 'padding' => ['top' => 16, 'right' => 0, 'bottom' => 16, 'left' => 0]],
            'children' => [
                ['type' => 'heading', 'props' => ['text' => $title, 'level' => 3]],
                ['type' => 'text', 'props' => ['markdown' => $markdown]],
            ],
        ];
    }
}
