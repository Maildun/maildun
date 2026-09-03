<?php

namespace Database\Seeders;

use App\Actions\Audiences\SyncSegmentSubscribers;
use App\Actions\Emails\ClassifyEmailTrackingEvent;
use App\Actions\Emails\RebuildEmailTrackingAggregates;
use App\Enums\AudienceAttributeType;
use App\Enums\AutomationAction;
use App\Enums\AutomationRunStatus;
use App\Enums\AutomationStatus;
use App\Enums\AutomationTrigger;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailEditor;
use App\Enums\EmailProvider;
use App\Enums\EmailStatus;
use App\Enums\EmailTrackingEventType;
use App\Enums\MediaStatus;
use App\Enums\SegmentMatchType;
use App\Enums\StorageBackend;
use App\Enums\SubscribeFormFieldMode;
use App\Enums\SubscribeFormImageSide;
use App\Enums\SubscribeFormStyle;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Enums\TeamBrandColor;
use App\Enums\TeamBrandFont;
use App\Enums\TeamBrandInputStyle;
use App\Enums\TransactionalEmailStatus;
use App\Jobs\ProcessEmailTrackingEvent;
use App\Models\Audience;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\EmailLink;
use App\Models\EmailLinkClick;
use App\Models\EmailProviderEvent;
use App\Models\EmailTemplate;
use App\Models\EmailTrackingEvent;
use App\Models\Media;
use App\Models\MediaCategory;
use App\Models\MediaTag;
use App\Models\Segment;
use App\Models\SubscribeForm;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\Team;
use App\Models\TeamApiKey;
use App\Models\TeamEmailIntegration;
use App\Models\TransactionalEmail;
use App\Models\User;
use App\Services\ManageContact;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

/**
 * @phpstan-type TrackingProfile array{
 *     user_agent: string,
 *     ip_address: string,
 *     country_code: string,
 *     subdivision_code: string,
 *     subdivision_name: string,
 *     city_name: string,
 *     latitude: float,
 *     longitude: float,
 *     network_asn: int,
 *     network_name: string
 * }
 */
class FullDemoSeeder extends Seeder
{
    private const string TEAM_SLUG = 'maildun-studio';

    private const string EMAIL_INTEGRATION_NAME = 'Production SES';

    private const string LEGACY_EMAIL_INTEGRATION_NAME = 'Demo Amazon SES (configure before sending)';

    public function __construct(
        private SyncSegmentSubscribers $syncSegmentSubscribers,
        private RebuildEmailTrackingAggregates $rebuildEmailTrackingAggregates,
        private ClassifyEmailTrackingEvent $classifyEmailTrackingEvent,
        private ManageContact $manageContact,
    ) {}

    public function run(): void
    {
        [$owner, $team] = $this->demoTeam();

        $this->configureTeam($team);
        $this->call(TeamSenderSeeder::class);

        $integration = $this->emailIntegration($team);
        $apiKeys = $this->apiKeys($team);
        $this->media($team, $owner);
        $tags = $this->tags($team);
        $audiences = $this->audiences($team);
        $this->audienceAttributes($audiences['newsletter']);
        $forms = $this->subscribeForms($audiences);
        $subscribers = $this->subscribers($audiences, $forms, $tags);
        $this->segments($audiences, $forms);
        $templates = $this->templates($team);
        $campaigns = $this->campaigns($team, $audiences, $templates);

        $this->campaignDeliveries(
            $campaigns['product_update'],
            $subscribers['newsletter'],
            $integration,
            [
                ...array_fill(0, 30, EmailDeliveryStatus::Delivered),
                ...array_fill(0, 2, EmailDeliveryStatus::Bounced),
                EmailDeliveryStatus::Complained,
                EmailDeliveryStatus::Delayed,
                EmailDeliveryStatus::Failed,
                EmailDeliveryStatus::Rejected,
            ],
            now()->subDays(7),
            withClicks: true,
        );

        $this->campaignDeliveries(
            $campaigns['customer_stories'],
            $subscribers['customers'],
            $integration,
            [
                ...array_fill(0, 14, EmailDeliveryStatus::Delivered),
                EmailDeliveryStatus::Bounced,
                EmailDeliveryStatus::Delayed,
                EmailDeliveryStatus::Failed,
                EmailDeliveryStatus::Rejected,
            ],
            now()->subDays(3),
        );

        $transactionalEmails = $this->transactionalEmails($team);
        $this->transactionalDeliveries($team, $transactionalEmails, $apiKeys['website']);
        $this->automations($team, $audiences['newsletter'], $tags, $subscribers['newsletter'], $transactionalEmails['welcome']);

        $owner->switchTeam($team);
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function demoTeam(): array
    {
        $owner = User::query()->oldest('id')->first();
        $team = Team::query()->withTrashed()->where('slug', self::TEAM_SLUG)->first();

        if (! $owner instanceof User || ! $team instanceof Team) {
            $this->call(WorkspaceMembersDemoSeeder::class);

            $owner = User::query()->oldest('id')->firstOrFail();
            $team = Team::query()->withTrashed()->where('slug', self::TEAM_SLUG)->sole();
        }

        if ($team->trashed()) {
            $team->restore();
        }

        return [$owner, $team];
    }

    private function configureTeam(Team $team): void
    {
        $team->fill([
            'email_editor' => EmailEditor::Builder,
            'email_from_name' => 'Maildun Studio',
            'email_from_address' => 'hello@maildun.test',
            'email_reply_to' => 'support@maildun.test',
            'brand_color' => TeamBrandColor::Indigo,
            'brand_font' => TeamBrandFont::InstrumentSans,
            'brand_input_style' => TeamBrandInputStyle::Soft,
            'convert_uploads_to_webp' => true,
        ])->save();
    }

    private function emailIntegration(Team $team): TeamEmailIntegration
    {
        $legacyIntegration = $team->emailIntegration()->first();

        if ($legacyIntegration instanceof TeamEmailIntegration) {
            if ($legacyIntegration->name === self::LEGACY_EMAIL_INTEGRATION_NAME) {
                $legacyIntegration->update(['name' => self::EMAIL_INTEGRATION_NAME]);
            }

            return $legacyIntegration;
        }

        return $team->emailIntegration()->create([
            'name' => self::EMAIL_INTEGRATION_NAME,
            'provider' => EmailProvider::AmazonSes,
            'settings' => [
                'region' => 'us-east-1',
                'username' => '',
                'password' => '',
                'configuration_set' => 'maildun-demo-feedback',
                'sns_topic_arn' => 'arn:aws:sns:us-east-1:123456789012:maildun-demo-feedback',
            ],
            'connected_at' => now()->subDays(14),
            'last_tested_at' => null,
            'test_from_address' => null,
        ]);
    }

    /**
     * @return array{website: TeamApiKey, worker: TeamApiKey}
     */
    private function apiKeys(Team $team): array
    {
        return [
            'website' => $team->apiKeys()->firstOrCreate(
                ['name' => 'Website integration (display only)'],
                [
                    'prefix' => 'demoapi00001',
                    'token_hash' => hash('sha256', "display-only:{$team->uuid}:website"),
                    'last_used_at' => now()->subHours(3),
                ],
            ),
            'worker' => $team->apiKeys()->firstOrCreate(
                ['name' => 'Background worker (display only)'],
                [
                    'prefix' => 'demoapi00002',
                    'token_hash' => hash('sha256', "display-only:{$team->uuid}:worker"),
                    'last_used_at' => now()->subDays(2),
                ],
            ),
        ];
    }

    /**
     * @return array<string, Tag>
     */
    private function tags(Team $team): array
    {
        $definitions = [
            'Newsletter' => '#0ea5e9',
            'Customer' => '#22c55e',
            'VIP' => '#a855f7',
            'Beta' => '#f97316',
            'Engaged' => '#ec4899',
        ];
        $tags = [];

        foreach ($definitions as $name => $color) {
            $tags[$name] = $team->tags()->firstOrCreate(['name' => $name], ['color' => $color]);
        }

        return $tags;
    }

    /**
     * @return array{newsletter: Audience, customers: Audience, waitlist: Audience, vip: Audience, testers: Audience}
     */
    private function audiences(Team $team): array
    {
        return [
            'newsletter' => $this->audience($team, 'Product newsletter', [
                'description' => 'Product releases, practical tips, and studio news.',
                'first_name_mode' => SubscribeFormFieldMode::Required,
                'last_name_mode' => SubscribeFormFieldMode::Optional,
                'from_name' => 'Maildun Studio',
                'from_address' => 'hello@maildun.test',
                'reply_to' => 'support@maildun.test',
                'notification_email' => 'notifications@maildun.test',
                'subscribed_url' => 'https://maildun.test/welcome',
                'already_subscribed_url' => 'https://maildun.test/already-subscribed',
                'unsubscribed_url' => 'https://maildun.test/unsubscribed',
            ]),
            'customers' => $this->audience($team, 'Customer updates', [
                'description' => 'Account, billing, and customer success updates.',
                'from_name' => 'Maildun Studio',
                'from_address' => 'hello@maildun.test',
            ]),
            'waitlist' => $this->audience($team, 'Launch waitlist', [
                'description' => 'People waiting for the next Maildun Studio launch.',
            ]),
            'vip' => $this->audience($team, 'VIP customers', [
                'description' => 'High-touch accounts and trusted partners.',
            ]),
            'testers' => $this->audience($team, 'Internal testers', [
                'description' => 'An empty list reserved for staff QA.',
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function audience(Team $team, string $name, array $attributes): Audience
    {
        return $team->audiences()->firstOrCreate(['name' => $name], $attributes);
    }

    private function audienceAttributes(Audience $audience): void
    {
        $attributes = [
            ['name' => 'Company', 'key' => 'company', 'type' => AudienceAttributeType::Text, 'required' => false],
            ['name' => 'Plan', 'key' => 'plan', 'type' => AudienceAttributeType::Text, 'required' => false],
            ['name' => 'Company size', 'key' => 'company_size', 'type' => AudienceAttributeType::Number, 'required' => false],
            ['name' => 'Joined on', 'key' => 'joined_on', 'type' => AudienceAttributeType::Date, 'required' => false],
        ];

        foreach ($attributes as $position => $attribute) {
            $audience->audienceAttributes()->firstOrCreate(
                ['key' => $attribute['key']],
                [...$attribute, 'position' => $position + 1],
            );
        }
    }

    /**
     * @param  array{newsletter: Audience, customers: Audience, waitlist: Audience, vip: Audience, testers: Audience}  $audiences
     * @return array{newsletter: SubscribeForm, launch: SubscribeForm, draft: SubscribeForm}
     */
    private function subscribeForms(array $audiences): array
    {
        return [
            'newsletter' => $this->subscribeForm($audiences['newsletter'], 'Website footer', [
                'headline' => 'Join the Maildun Studio newsletter',
                'description' => 'A focused monthly digest for email teams.',
                'button_label' => 'Get the digest',
                'success_message' => 'You are on the list. Check your inbox for the next issue.',
                'consent_text' => 'I agree to receive product news and practical email tips.',
                'style' => SubscribeFormStyle::Card,
                'published_at' => now()->subWeeks(8),
            ]),
            'launch' => $this->subscribeForm($audiences['waitlist'], 'Launch landing page', [
                'headline' => 'Be first to see what is next',
                'description' => 'Join the waitlist for the next Maildun Studio release.',
                'button_label' => 'Join the waitlist',
                'success_message' => 'You are on the waitlist. We will be in touch soon.',
                'consent_text' => 'I agree to receive launch updates from Maildun Studio.',
                'style' => SubscribeFormStyle::Split,
                'image_side' => SubscribeFormImageSide::Right,
                'published_at' => now()->subWeeks(4),
            ]),
            'draft' => $this->subscribeForm($audiences['newsletter'], 'Quarterly webinar popup', [
                'headline' => 'Get the next webinar invitation',
                'description' => 'A draft signup form for a customer education webinar.',
                'button_label' => 'Notify me',
                'success_message' => 'Thanks. Your webinar invite is on its way.',
                'consent_text' => 'I agree to receive webinar updates.',
                'style' => SubscribeFormStyle::Minimal,
                'published_at' => null,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function subscribeForm(Audience $audience, string $name, array $attributes): SubscribeForm
    {
        $form = $audience->subscribeForms()->withTrashed()->where('name', $name)->first();

        if ($form instanceof SubscribeForm) {
            if ($form->trashed()) {
                $form->restore();
            }

            return $form;
        }

        return $audience->subscribeForms()->create(['name' => $name, ...$attributes]);
    }

    /**
     * @param  array{newsletter: Audience, customers: Audience, waitlist: Audience, vip: Audience, testers: Audience}  $audiences
     * @param  array{newsletter: SubscribeForm, launch: SubscribeForm, draft: SubscribeForm}  $forms
     * @param  array<string, Tag>  $tags
     * @return array{newsletter: Collection<int, Subscriber>, customers: Collection<int, Subscriber>, waitlist: Collection<int, Subscriber>, vip: Collection<int, Subscriber>}
     */
    private function subscribers(array $audiences, array $forms, array $tags): array
    {
        return [
            'newsletter' => $this->seedSubscribers($audiences['newsletter'], 'newsletter', 72, 59, $forms['newsletter'], $tags, 9),
            'customers' => $this->seedSubscribers($audiences['customers'], 'customer', 24, 45, null, $tags, 11),
            'waitlist' => $this->seedSubscribers($audiences['waitlist'], 'waitlist', 18, 30, $forms['launch'], $tags),
            'vip' => $this->seedSubscribers($audiences['vip'], 'vip', 9, 21, null, $tags),
        ];
    }

    /**
     * @param  array<string, Tag>  $tags
     * @return Collection<int, Subscriber>
     */
    private function seedSubscribers(
        Audience $audience,
        string $prefix,
        int $count,
        int $startDaysAgo,
        ?SubscribeForm $form,
        array $tags,
        ?int $unsubscribeEvery = null,
    ): Collection {
        $names = [
            ['Avery', 'Morgan'], ['Jordan', 'Kim'], ['Riley', 'Patel'], ['Casey', 'Nguyen'],
            ['Quinn', 'Garcia'], ['Taylor', 'Smith'], ['Parker', 'Jones'], ['Rowan', 'Brown'],
            ['Sage', 'Wilson'], ['Cameron', 'Lee'], ['Reese', 'Davis'], ['Emerson', 'Clark'],
        ];
        $subscribers = new Collection;

        foreach (range(1, $count) as $index) {
            [$firstName, $lastName] = $names[($index - 1) % count($names)];
            $joinedAt = $this->joinedAt($startDaysAgo, $index, $count);
            $unsubscribed = $unsubscribeEvery !== null && $index % $unsubscribeEvery === 0;
            $source = $form !== null && $index % 3 === 0
                ? SubscriberSource::Form
                : ($index % 7 === 0 ? SubscriberSource::Api : SubscriberSource::Manual);
            $contact = $this->manageContact->findOrCreate($audience->team, [
                'email' => sprintf('%s-%02d@demo.maildun.test', $prefix, $index),
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]);
            $subscriber = $audience->subscribers()->firstOrCreate(
                ['email' => $contact->email],
                [
                    'contact_id' => $contact->id,
                    'subscribe_form_id' => $source === SubscriberSource::Form ? $form->id : null,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'attribute_values' => $audience->name === 'Product newsletter'
                        ? [
                            'company' => "{$firstName} {$lastName} Studio",
                            'plan' => $index % 3 === 0 ? 'Growth' : 'Starter',
                            'company_size' => 5 + ($index % 8) * 5,
                            'joined_on' => $joinedAt->toDateString(),
                        ]
                        : [],
                    'status' => $unsubscribed ? SubscriberStatus::Unsubscribed : SubscriberStatus::Subscribed,
                    'source' => $source,
                    'consent_text' => 'Demo marketing consent confirmed.',
                    'consented_at' => $joinedAt,
                    'consent_ip' => '203.0.113.'.($index % 200 + 1),
                    'subscribed_at' => $joinedAt,
                    'unsubscribed_at' => $unsubscribed ? min($joinedAt->copy()->addDays(3), now()) : null,
                ],
            );

            if ($subscriber->wasRecentlyCreated) {
                $subscriber->forceFill([
                    'created_at' => $joinedAt,
                    'updated_at' => $joinedAt,
                ])->save();
            }

            if ($subscriber->contact_id !== $contact->id) {
                $subscriber->updateQuietly(['contact_id' => $contact->id]);
            }

            $this->attachSubscriberTags($subscriber, $prefix, $index, $tags);
            $subscribers->push($subscriber);
        }

        return $subscribers;
    }

    private function joinedAt(int $startDaysAgo, int $index, int $count): CarbonInterface
    {
        $span = max($startDaysAgo, 1);
        $offset = (int) floor(($index - 1) * $span / max($count - 1, 1));

        return now()->startOfDay()->subDays($span - $offset)->subHours($index % 12);
    }

    /**
     * @param  array<string, Tag>  $tags
     */
    private function attachSubscriberTags(Subscriber $subscriber, string $prefix, int $index, array $tags): void
    {
        $names = match ($prefix) {
            'newsletter' => array_filter([
                'Newsletter',
                $index % 4 === 0 ? 'Engaged' : null,
                $index % 11 === 0 ? 'Beta' : null,
            ]),
            'customer' => array_filter([
                'Customer',
                $index % 3 === 0 ? 'Engaged' : null,
            ]),
            'waitlist' => ['Beta'],
            'vip' => ['VIP', 'Customer'],
            default => [],
        };

        $subscriber->tags()->syncWithoutDetaching(
            collect($names)
                ->map(fn (string $name): ?int => $tags[$name]->id ?? null)
                ->filter()
                ->all(),
        );
    }

    /**
     * @param  array{newsletter: Audience, customers: Audience, waitlist: Audience, vip: Audience, testers: Audience}  $audiences
     * @param  array{newsletter: SubscribeForm, launch: SubscribeForm, draft: SubscribeForm}  $forms
     */
    private function segments(array $audiences, array $forms): void
    {
        $segments = [
            $this->segment($audiences['newsletter'], 'Subscribed contacts', 'Everyone currently opted in.', [
                ['field' => 'status', 'operator' => 'equals', 'value' => SubscriberStatus::Subscribed->value],
            ]),
            $this->segment($audiences['newsletter'], 'Website footer signups', 'Subscribers who joined through the website footer.', [
                ['field' => 'subscribe_form', 'operator' => 'equals', 'value' => $forms['newsletter']->uuid],
            ]),
            $this->segment($audiences['newsletter'], 'Recent subscribers', 'People who joined during the last three weeks.', [
                ['field' => 'subscribed_at', 'operator' => 'after', 'value' => now()->subDays(21)->toDateString()],
            ]),
            $this->segment($audiences['customers'], 'API customers', 'Customer records created through the API.', [
                ['field' => 'source', 'operator' => 'equals', 'value' => SubscriberSource::Api->value],
            ]),
        ];

        foreach ($segments as $segment) {
            $this->syncSegmentSubscribers->handle($segment);
        }
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

    /**
     * @return array{newsletter: EmailTemplate, announcement: EmailTemplate}
     */
    private function templates(Team $team): array
    {
        return [
            'newsletter' => $team->emailTemplates()->firstOrCreate(
                ['name' => 'Studio newsletter'],
                [
                    'description' => 'A polished, reusable newsletter for Maildun Studio updates.',
                    'subject' => 'What is new at Maildun Studio',
                    'preheader' => 'A quick look at product progress, customers, and resources.',
                    'editor' => EmailEditor::Builder,
                    'html' => $this->emailHtml('What is new at Maildun Studio', 'A focused update for people building better email programs.'),
                    'design' => $this->emailDesign('What is new at Maildun Studio', 'Read the studio update'),
                    'position' => 100,
                ],
            ),
            'announcement' => $team->emailTemplates()->firstOrCreate(
                ['name' => 'Feature announcement'],
                [
                    'description' => 'A direct HTML template for a single important product release.',
                    'subject' => 'A new feature is ready to try',
                    'preheader' => 'See what changed and why it matters.',
                    'editor' => EmailEditor::Html,
                    'html' => $this->emailHtml('A new feature is ready to try', 'Here is the short version, with one clear next step.'),
                    'design' => null,
                    'position' => 110,
                ],
            ),
        ];
    }

    /**
     * @param  array{newsletter: Audience, customers: Audience, waitlist: Audience, vip: Audience, testers: Audience}  $audiences
     * @param  array{newsletter: EmailTemplate, announcement: EmailTemplate}  $templates
     * @return array{product_update: Email, customer_stories: Email, webinar: Email, september: Email, failed: Email}
     */
    private function campaigns(Team $team, array $audiences, array $templates): array
    {
        return [
            'product_update' => $this->campaign($team, 'Campaign Insights v2 launch', [
                'audience_id' => $audiences['newsletter']->id,
                'email_template_id' => $templates['newsletter']->id,
                'subject' => 'Campaign Insights v2: human engagement you can trust',
                'preheader' => 'Separate people from scanners and privacy proxies, then explore where real engagement happens.',
                'editor' => EmailEditor::Builder,
                'html' => $this->emailHtml('Campaign Insights v2 is here', 'See human-only engagement, traffic quality, locations, clients, devices, and networks in one privacy-safe report.'),
                'design' => $this->emailDesign('Campaign Insights v2 is here', 'Explore campaign insights'),
                'status' => EmailStatus::Sent,
                'recipient_count' => 36,
                'send_started_at' => now()->subDays(7)->subMinutes(30),
                'sent_at' => now()->subDays(7),
            ], legacyName: 'July product update'),
            'customer_stories' => $this->campaign($team, 'Customer stories: the August edition', [
                'audience_id' => $audiences['customers']->id,
                'email_template_id' => $templates['announcement']->id,
                'subject' => 'How customer teams are turning feedback into momentum',
                'preheader' => 'Three practical stories from this month.',
                'editor' => EmailEditor::Html,
                'html' => $this->emailHtml('Customer stories', 'A collection of thoughtful approaches to onboarding, feedback, and retention.'),
                'design' => null,
                'status' => EmailStatus::PartiallyFailed,
                'recipient_count' => 18,
                'send_started_at' => now()->subDays(3)->subMinutes(25),
                'sent_at' => now()->subDays(3),
            ]),
            'webinar' => $this->campaign($team, 'September webinar reminder', [
                'audience_id' => $audiences['newsletter']->id,
                'subject' => 'Your September email strategy workshop starts tomorrow',
                'preheader' => 'Save your seat for the live session.',
                'editor' => EmailEditor::Builder,
                'html' => $this->emailHtml('Email strategy workshop', 'Bring your next campaign question and leave with a practical plan.'),
                'design' => $this->emailDesign('Email strategy workshop', 'Save your seat'),
                'status' => EmailStatus::Queued,
                'recipient_count' => 62,
                'send_started_at' => now()->subMinutes(15),
                'sent_at' => null,
            ]),
            'september' => $this->campaign($team, 'September newsletter draft', [
                'audience_id' => $audiences['newsletter']->id,
                'email_template_id' => $templates['newsletter']->id,
                'subject' => 'September at Maildun Studio',
                'preheader' => 'A draft of next month’s studio dispatch.',
                'editor' => EmailEditor::Builder,
                'html' => $this->emailHtml('September at Maildun Studio', 'A draft that is ready for a final edit and a send date.'),
                'design' => $this->emailDesign('September at Maildun Studio', 'Read the draft'),
                'status' => EmailStatus::Draft,
                'recipient_count' => 0,
                'send_started_at' => null,
                'sent_at' => null,
                'last_tested_at' => now()->subHours(4),
            ]),
            'failed' => $this->campaign($team, 'Partner announcement', [
                'audience_id' => $audiences['vip']->id,
                'subject' => 'A note for Maildun Studio partners',
                'preheader' => 'A campaign retained here to demonstrate a failed send.',
                'editor' => EmailEditor::Html,
                'html' => $this->emailHtml('A note for Maildun Studio partners', 'This campaign intentionally shows a completed failure state in development.'),
                'design' => null,
                'status' => EmailStatus::Failed,
                'recipient_count' => 9,
                'send_started_at' => now()->subDays(12),
                'sent_at' => null,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function campaign(
        Team $team,
        string $name,
        array $attributes,
        ?string $legacyName = null,
    ): Email {
        $email = $team->emails()->withTrashed()->where('name', $name)->first();

        if (! $email instanceof Email && $legacyName !== null) {
            $email = $team->emails()->withTrashed()->where('name', $legacyName)->first();
        }

        if ($email instanceof Email) {
            if ($email->trashed()) {
                $email->restore();
            }

            $email->fill(['name' => $name, ...$attributes])->save();

            return $email;
        }

        return $team->emails()->create(['name' => $name, ...$attributes]);
    }

    /**
     * @param  Collection<int, Subscriber>  $subscribers
     * @param  list<EmailDeliveryStatus>  $statuses
     */
    private function campaignDeliveries(
        Email $email,
        Collection $subscribers,
        TeamEmailIntegration $integration,
        array $statuses,
        CarbonInterface $sentAt,
        bool $withClicks = false,
    ): void {
        if ($withClicks) {
            $this->resetCampaignTrackingActivity($email);
        }

        $links = $this->emailLinks($email, campaignInsights: $withClicks);

        foreach ($statuses as $index => $status) {
            $subscriber = $subscribers->get($index);

            if (! $subscriber instanceof Subscriber) {
                break;
            }

            $deliveryTime = $sentAt->copy()->addMinutes($index * 3);
            $clicked = $withClicks && $status === EmailDeliveryStatus::Delivered && $index % 5 === 0;
            $clicks = $clicked ? ($index % 2 === 0 ? 2 : 1) : 0;
            $opened = $clicked ? $clicks + 1 : ($status === EmailDeliveryStatus::Delivered && $index % 3 === 0 ? 1 : 0);
            $timestamps = $this->deliveryTimestamps(
                $status,
                $deliveryTime,
                $withClicks ? 0 : $opened,
                $withClicks ? false : $clicked,
            );
            $delivery = $email->deliveries()->firstOrCreate(
                ['subscriber_id' => $subscriber->id],
                [
                    'email_address' => $subscriber->email,
                    'first_name' => $subscriber->first_name,
                    'last_name' => $subscriber->last_name,
                    'status' => $status,
                    'provider' => EmailProvider::AmazonSes->value,
                    'uses_team_email_integration' => true,
                    'provider_message_id' => "maildun-demo-{$email->id}-{$index}",
                    'failure_reason' => $this->failureReason($status),
                    'opens_count' => $withClicks ? 0 : $opened,
                    'clicks_count' => $withClicks ? 0 : $clicks,
                    ...$timestamps,
                ],
            );

            $attempt = $delivery->attempts()->firstOrCreate(
                ['provider_message_id' => "maildun-demo-attempt-{$email->id}-{$index}"],
                [
                    'team_email_integration_id' => $integration->id,
                    'integration_uuid' => $integration->uuid,
                    'integration_name' => $integration->name,
                    'provider' => EmailProvider::AmazonSes,
                    'status' => $status,
                    'failure_reason' => $this->failureReason($status),
                    'ses_configuration_set' => 'maildun-demo-feedback',
                    'ses_sns_topic_arn_hash' => $integration->ses_sns_topic_arn_hash,
                    ...Arr::only($timestamps, [
                        'send_attempted_at',
                        'sent_at',
                        'delivered_at',
                        'delayed_at',
                        'bounced_at',
                        'complained_at',
                    ]),
                ],
            );

            EmailProviderEvent::query()->firstOrCreate(
                ['event_id' => "maildun-demo-event-{$email->id}-{$index}"],
                [
                    'provider' => EmailProvider::AmazonSes->value,
                    'ses_sns_topic_arn_hash' => $integration->ses_sns_topic_arn_hash,
                    'email_delivery_id' => $delivery->id,
                    'email_delivery_attempt_id' => $attempt->id,
                    'type' => $status->value,
                    'payload' => ['demo' => true, 'status' => $status->value],
                    'occurred_at' => $deliveryTime,
                    'processed_at' => $deliveryTime->copy()->addMinute(),
                ],
            );

            if ($withClicks && ($opened > 0 || $clicks > 0)) {
                $this->seedCampaignTrackingEvents(
                    delivery: $delivery,
                    links: $links,
                    profileIndex: $index,
                    opens: $opened,
                    clicks: $clicks,
                    deliveryTime: $deliveryTime,
                );
            }
        }

        $this->rebuildEmailTrackingAggregates->handle($email);
    }

    private function resetCampaignTrackingActivity(Email $email): void
    {
        $deliveryIds = $email->deliveries()->pluck('id');

        if ($deliveryIds->isNotEmpty()) {
            EmailTrackingEvent::query()
                ->whereIn('email_delivery_id', $deliveryIds)
                ->delete();

            EmailLinkClick::query()
                ->whereIn('email_delivery_id', $deliveryIds)
                ->delete();

            $email->deliveries()->whereIn('id', $deliveryIds)->update([
                'opens_count' => 0,
                'clicks_count' => 0,
                'first_opened_at' => null,
                'last_opened_at' => null,
                'first_clicked_at' => null,
                'last_clicked_at' => null,
            ]);
        }

        $email->insightAggregates()->delete();
        $this->rebuildEmailTrackingAggregates->handle($email);
    }

    /**
     * @param  list<EmailLink>  $links
     */
    private function seedCampaignTrackingEvents(
        EmailDelivery $delivery,
        array $links,
        int $profileIndex,
        int $opens,
        int $clicks,
        CarbonInterface $deliveryTime,
    ): void {
        $profile = $this->trackingProfile($profileIndex);

        if ($profileIndex === 0) {
            $this->processCampaignTrackingEvent(
                delivery: $delivery,
                type: EmailTrackingEventType::Open,
                occurredAt: $deliveryTime->copy()->addMinutes(3),
                profile: $this->unknownTrackingProfile(),
            );
        }

        for ($open = 0; $open < $opens; $open++) {
            $this->processCampaignTrackingEvent(
                delivery: $delivery,
                type: EmailTrackingEventType::Open,
                occurredAt: $deliveryTime->copy()->addMinutes(4 + $open),
                profile: $profile,
            );
        }

        if ($links === []) {
            return;
        }

        $link = $links[$profileIndex % count($links)];

        for ($click = 0; $click < $clicks; $click++) {
            $this->processCampaignTrackingEvent(
                delivery: $delivery,
                type: EmailTrackingEventType::Click,
                occurredAt: $deliveryTime->copy()->addMinutes(6 + $click),
                profile: $profile,
                link: $link,
            );
        }
    }

    /**
     * @param  TrackingProfile  $profile
     */
    private function processCampaignTrackingEvent(
        EmailDelivery $delivery,
        EmailTrackingEventType $type,
        CarbonInterface $occurredAt,
        array $profile,
        ?EmailLink $link = null,
    ): void {
        $userAgent = $profile['user_agent'];
        $classification = $this->classifyEmailTrackingEvent->handle($userAgent);
        $event = EmailTrackingEvent::query()->create([
            'email_delivery_id' => $delivery->id,
            'email_link_id' => $link?->id,
            'type' => $type,
            'occurred_at' => $occurredAt,
            'user_agent' => $userAgent,
            'ip_hash' => hash_hmac('sha256', $profile['ip_address'], (string) config('app.key')),
            ...$classification,
            'country_code' => $profile['country_code'],
            'subdivision_code' => $profile['subdivision_code'],
            'subdivision_name' => $profile['subdivision_name'],
            'city_name' => $profile['city_name'],
            'latitude' => $profile['latitude'],
            'longitude' => $profile['longitude'],
            'network_asn' => $profile['network_asn'],
            'network_name' => $profile['network_name'],
            'geolocation_source' => 'db-ip-lite',
            'geolocated_at' => $occurredAt,
        ]);

        app()->call([(new ProcessEmailTrackingEvent($event->id)), 'handle']);
    }

    /** @return TrackingProfile */
    private function trackingProfile(int $index): array
    {
        return match ($index % 6) {
            0 => [
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36',
                'ip_address' => '198.51.100.10',
                'country_code' => 'US',
                'subdivision_code' => 'CA',
                'subdivision_name' => 'California',
                'city_name' => 'San Francisco',
                'latitude' => 37.7749,
                'longitude' => -122.4194,
                'network_asn' => 7922,
                'network_name' => 'Comcast Cable Communications, LLC',
            ],
            1 => [
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 Version/17.6 Mobile/15E148 Safari/604.1',
                'ip_address' => '198.51.100.20',
                'country_code' => 'SG',
                'subdivision_code' => '01',
                'subdivision_name' => 'Central Singapore',
                'city_name' => 'Singapore',
                'latitude' => 1.3521,
                'longitude' => 103.8198,
                'network_asn' => 9506,
                'network_name' => 'Singtel Fibre Broadband',
            ],
            2 => [
                'user_agent' => 'Proofpoint URL Defense Scanner/2.0',
                'ip_address' => '198.51.100.30',
                'country_code' => 'US',
                'subdivision_code' => 'VA',
                'subdivision_name' => 'Virginia',
                'city_name' => 'Ashburn',
                'latitude' => 39.0438,
                'longitude' => -77.4874,
                'network_asn' => 14618,
                'network_name' => 'Amazon.com, Inc.',
            ],
            3 => [
                'user_agent' => 'Microsoft Outlook 16.0 (Windows NT 10.0; Win64; x64)',
                'ip_address' => '198.51.100.40',
                'country_code' => 'GB',
                'subdivision_code' => 'ENG',
                'subdivision_name' => 'England',
                'city_name' => 'London',
                'latitude' => 51.5072,
                'longitude' => -0.1276,
                'network_asn' => 2856,
                'network_name' => 'British Telecommunications PLC',
            ],
            4 => [
                'user_agent' => 'Mozilla/5.0 (compatible; GoogleImageProxy)',
                'ip_address' => '198.51.100.50',
                'country_code' => 'US',
                'subdivision_code' => 'IA',
                'subdivision_name' => 'Iowa',
                'city_name' => 'Council Bluffs',
                'latitude' => 41.2619,
                'longitude' => -95.8608,
                'network_asn' => 15169,
                'network_name' => 'Google LLC',
            ],
            default => [
                'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Thunderbird/128.0',
                'ip_address' => '198.51.100.60',
                'country_code' => 'DE',
                'subdivision_code' => 'BE',
                'subdivision_name' => 'Berlin',
                'city_name' => 'Berlin',
                'latitude' => 52.5200,
                'longitude' => 13.4050,
                'network_asn' => 3320,
                'network_name' => 'Deutsche Telekom AG',
            ],
        };
    }

    /** @return TrackingProfile */
    private function unknownTrackingProfile(): array
    {
        return [
            'user_agent' => 'Maildun Preview Client/1.0',
            'ip_address' => '198.51.100.70',
            'country_code' => 'ID',
            'subdivision_code' => 'JK',
            'subdivision_name' => 'Jakarta',
            'city_name' => 'Jakarta',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'network_asn' => 7713,
            'network_name' => 'PT Telekomunikasi Indonesia',
        ];
    }

    /**
     * @return list<EmailLink>
     */
    private function emailLinks(Email $email, bool $campaignInsights = false): array
    {
        $urls = $campaignInsights
            ? [
                'https://maildun.test/releases/campaign-insights-v2',
                'https://maildun.test/guides/human-engagement',
                'https://maildun.test/privacy/email-tracking',
            ]
            : [
                'https://maildun.test/customer-stories',
                'https://maildun.test/guides/campaign-reporting',
                'https://maildun.test/community',
            ];
        $links = [];

        foreach ($urls as $position => $url) {
            $link = $email->links()->where('position', $position + 1)->first();

            if ($link instanceof EmailLink) {
                $link->fill([
                    'url' => $url,
                    'url_hash' => hash('sha256', $url),
                ])->save();
            } else {
                $link = $email->links()->create([
                    'url' => $url,
                    'url_hash' => hash('sha256', $url),
                    'position' => $position + 1,
                ]);
            }

            $links[] = $link;
        }

        $email->links()->where('position', '>', count($urls))->delete();

        return $links;
    }

    /**
     * @return array<string, CarbonInterface|null>
     */
    private function deliveryTimestamps(
        EmailDeliveryStatus $status,
        CarbonInterface $deliveryTime,
        int $opens,
        bool $clicked,
    ): array {
        $sent = in_array($status, [EmailDeliveryStatus::Failed, EmailDeliveryStatus::Rejected], true)
            ? null
            : $deliveryTime;

        return [
            'send_attempted_at' => $deliveryTime->copy()->subMinute(),
            'sent_at' => $sent,
            'delivered_at' => $status === EmailDeliveryStatus::Delivered ? $deliveryTime->copy()->addMinute() : null,
            'delayed_at' => $status === EmailDeliveryStatus::Delayed ? $deliveryTime->copy()->addMinute() : null,
            'bounced_at' => $status === EmailDeliveryStatus::Bounced ? $deliveryTime->copy()->addMinute() : null,
            'complained_at' => $status === EmailDeliveryStatus::Complained ? $deliveryTime->copy()->addMinute() : null,
            'first_opened_at' => $opens > 0 ? $deliveryTime->copy()->addMinutes(4) : null,
            'last_opened_at' => $opens > 0 ? $deliveryTime->copy()->addMinutes(4 + $opens) : null,
            'first_clicked_at' => $clicked ? $deliveryTime->copy()->addMinutes(6) : null,
            'last_clicked_at' => $clicked ? $deliveryTime->copy()->addMinutes(6 + $opens) : null,
        ];
    }

    private function failureReason(EmailDeliveryStatus $status): ?string
    {
        return match ($status) {
            EmailDeliveryStatus::Bounced => 'The recipient domain rejected this mailbox.',
            EmailDeliveryStatus::Complained => 'The recipient reported this message as unwanted.',
            EmailDeliveryStatus::Delayed => 'The provider delayed delivery and will retry.',
            EmailDeliveryStatus::Failed => 'The provider could not accept this message.',
            EmailDeliveryStatus::Rejected => 'The provider rejected this message before sending.',
            default => null,
        };
    }

    /**
     * @return array{welcome: TransactionalEmail, reset: TransactionalEmail, invoice: TransactionalEmail}
     */
    private function transactionalEmails(Team $team): array
    {
        return [
            'welcome' => $this->transactionalEmail($team, 'Welcome to Maildun Studio', [
                'slug' => 'welcome-to-maildun-studio',
                'description' => 'Sent when a new user joins the studio.',
                'subject' => 'Welcome, {{ first_name }}',
                'preheader' => 'Your Maildun Studio workspace is ready.',
                'editor' => EmailEditor::Builder,
                'status' => TransactionalEmailStatus::Published,
                'html' => $this->emailHtml('Welcome, {{ first_name }}', 'Your Maildun Studio workspace is ready. Start with your first audience and campaign.'),
                'design' => $this->emailDesign('Welcome, {{ first_name }}', 'Open your workspace'),
                'variables' => [
                    ['key' => 'first_name', 'example' => 'Avery'],
                    ['key' => 'workspace_url', 'example' => 'https://maildun.test/studio'],
                ],
                'published_at' => now()->subWeeks(6),
            ]),
            'reset' => $this->transactionalEmail($team, 'Password reset', [
                'slug' => 'password-reset',
                'description' => 'A secure password reset message.',
                'subject' => 'Reset your Maildun Studio password',
                'preheader' => 'Use your one-time link to choose a new password.',
                'editor' => EmailEditor::Html,
                'status' => TransactionalEmailStatus::Published,
                'html' => $this->emailHtml('Reset your password', 'Use your secure link within 30 minutes: {{ reset_url }}'),
                'design' => null,
                'variables' => [
                    ['key' => 'first_name', 'example' => 'Avery'],
                    ['key' => 'reset_url', 'example' => 'https://maildun.test/reset/demo-token'],
                ],
                'published_at' => now()->subWeeks(5),
            ]),
            'invoice' => $this->transactionalEmail($team, 'Invoice ready', [
                'slug' => 'invoice-ready',
                'description' => 'A draft billing notification awaiting final copy.',
                'subject' => 'Your {{ invoice_month }} invoice is ready',
                'preheader' => 'View your invoice and payment details.',
                'editor' => EmailEditor::Builder,
                'status' => TransactionalEmailStatus::Draft,
                'html' => $this->emailHtml('Your invoice is ready', 'Your {{ invoice_month }} invoice is ready to review.'),
                'design' => $this->emailDesign('Your invoice is ready', 'View invoice'),
                'variables' => [
                    ['key' => 'invoice_month', 'example' => 'August'],
                    ['key' => 'invoice_url', 'example' => 'https://maildun.test/invoices/demo'],
                ],
                'published_at' => null,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transactionalEmail(Team $team, string $name, array $attributes): TransactionalEmail
    {
        $email = $team->transactionalEmails()->withTrashed()->where('name', $name)->first();

        if ($email instanceof TransactionalEmail) {
            if ($email->trashed()) {
                $email->restore();
            }

            return $email;
        }

        return $team->transactionalEmails()->create(['name' => $name, ...$attributes]);
    }

    /**
     * @param  array{welcome: TransactionalEmail, reset: TransactionalEmail, invoice: TransactionalEmail}  $emails
     */
    private function transactionalDeliveries(Team $team, array $emails, TeamApiKey $apiKey): void
    {
        $deliveries = [
            [
                'key' => 'full-demo-welcome-001',
                'email' => $emails['welcome'],
                'to' => 'avery@demo.maildun.test',
                'status' => EmailDeliveryStatus::Sent,
                'subject' => 'Welcome, Avery',
                'sent_at' => now()->subDays(2),
                'failure_reason' => null,
            ],
            [
                'key' => 'full-demo-reset-001',
                'email' => $emails['reset'],
                'to' => 'jordan@demo.maildun.test',
                'status' => EmailDeliveryStatus::Sent,
                'subject' => 'Reset your Maildun Studio password',
                'sent_at' => now()->subDay(),
                'failure_reason' => null,
            ],
            [
                'key' => 'full-demo-welcome-002',
                'email' => $emails['welcome'],
                'to' => 'riley@demo.maildun.test',
                'status' => EmailDeliveryStatus::Failed,
                'subject' => 'Welcome, Riley',
                'sent_at' => null,
                'failure_reason' => 'The recipient address could not be accepted by the provider.',
            ],
            [
                'key' => 'full-demo-reset-002',
                'email' => $emails['reset'],
                'to' => 'casey@demo.maildun.test',
                'status' => EmailDeliveryStatus::Queued,
                'subject' => 'Reset your Maildun Studio password',
                'sent_at' => null,
                'failure_reason' => null,
            ],
        ];

        foreach ($deliveries as $delivery) {
            $sentAt = $delivery['sent_at'];
            $team->transactionalEmailDeliveries()->firstOrCreate(
                ['idempotency_key' => $delivery['key']],
                [
                    'transactional_email_id' => $delivery['email']->id,
                    'team_api_key_id' => $apiKey->id,
                    'request_hash' => hash('sha256', $delivery['key']),
                    'to_address' => $delivery['to'],
                    'subject' => $delivery['subject'],
                    'html' => $delivery['email']->html ?? '',
                    'from_name' => $team->email_from_name ?? 'Maildun Studio',
                    'from_address' => $team->email_from_address ?? 'hello@maildun.test',
                    'reply_to' => $team->email_reply_to,
                    'status' => $delivery['status'],
                    'provider' => EmailProvider::AmazonSes->value,
                    'uses_team_email_integration' => true,
                    'provider_message_id' => "maildun-demo-transactional-{$delivery['key']}",
                    'failure_reason' => $delivery['failure_reason'],
                    'send_attempted_at' => $sentAt instanceof CarbonInterface ? $sentAt->copy()->subMinute() : now()->subHours(2),
                    'sent_at' => $sentAt,
                ],
            );
        }
    }

    /**
     * @param  array<string, Tag>  $tags
     * @param  Collection<int, Subscriber>  $subscribers
     */
    private function automations(
        Team $team,
        Audience $newsletter,
        array $tags,
        Collection $subscribers,
        TransactionalEmail $welcomeEmail,
    ): void {
        $welcomeGraph = [
            'nodes' => [
                [
                    'id' => 'trigger',
                    'type' => 'trigger',
                    'position' => ['x' => 320, 'y' => 40],
                    'deletable' => false,
                    'data' => [
                        'kind' => AutomationTrigger::Subscribed->value,
                        'audience_uuid' => $newsletter->uuid,
                        'tag_uuid' => null,
                    ],
                ],
                [
                    'id' => 'wait-one-day',
                    'type' => 'delay',
                    'position' => ['x' => 320, 'y' => 180],
                    'data' => ['amount' => 1, 'unit' => 'days'],
                ],
                [
                    'id' => 'send-welcome',
                    'type' => 'action',
                    'position' => ['x' => 320, 'y' => 320],
                    'data' => [
                        'kind' => AutomationAction::SendEmail->value,
                        'transactional_email_uuid' => $welcomeEmail->uuid,
                    ],
                ],
            ],
            'edges' => [
                ['id' => 'trigger-wait', 'source' => 'trigger', 'target' => 'wait-one-day'],
                ['id' => 'wait-send', 'source' => 'wait-one-day', 'target' => 'send-welcome'],
            ],
        ];
        $engagementGraph = [
            'nodes' => [
                [
                    'id' => 'trigger',
                    'type' => 'trigger',
                    'position' => ['x' => 320, 'y' => 40],
                    'deletable' => false,
                    'data' => [
                        'kind' => AutomationTrigger::Tagged->value,
                        'audience_uuid' => $newsletter->uuid,
                        'tag_uuid' => $tags['Newsletter']->uuid,
                    ],
                ],
                [
                    'id' => 'add-engaged',
                    'type' => 'action',
                    'position' => ['x' => 320, 'y' => 180],
                    'data' => [
                        'kind' => AutomationAction::AddTag->value,
                        'tag_uuid' => $tags['Engaged']->uuid,
                    ],
                ],
            ],
            'edges' => [
                ['id' => 'trigger-engaged', 'source' => 'trigger', 'target' => 'add-engaged'],
            ],
        ];
        $welcome = $this->automation($team, 'Welcome series', [
            'description' => 'Welcome new newsletter subscribers after a one-day delay.',
            'status' => AutomationStatus::Active,
            'trigger' => AutomationTrigger::Subscribed,
            'trigger_config' => ['audience_uuid' => $newsletter->uuid, 'tag_uuid' => null],
            'graph' => $welcomeGraph,
        ]);
        $engagement = $this->automation($team, 'Newsletter engagement tag', [
            'description' => 'Apply the Engaged tag after a newsletter interaction.',
            'status' => AutomationStatus::Paused,
            'trigger' => AutomationTrigger::Tagged,
            'trigger_config' => ['audience_uuid' => $newsletter->uuid, 'tag_uuid' => $tags['Newsletter']->uuid],
            'graph' => $engagementGraph,
        ]);
        $draft = $this->automation($team, 'Win-back follow-up', [
            'description' => 'A draft workflow for a future re-engagement campaign.',
            'status' => AutomationStatus::Draft,
            'trigger' => AutomationTrigger::Unsubscribed,
            'trigger_config' => ['audience_uuid' => $newsletter->uuid, 'tag_uuid' => null],
            'graph' => $this->draftAutomationGraph($newsletter),
        ]);

        $this->automationRuns($welcome, $subscribers->take(6), ['completed', 'completed', 'completed', 'completed', 'failed', 'cancelled']);
        $this->automationRuns($engagement, $subscribers->slice(6, 3), ['completed', 'failed', 'cancelled']);
        $this->automationRuns($draft, $subscribers->slice(9, 2), ['cancelled', 'completed']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function automation(Team $team, string $name, array $attributes): Automation
    {
        $automation = $team->automations()->withTrashed()->where('name', $name)->first();

        if ($automation instanceof Automation) {
            if ($automation->trashed()) {
                $automation->restore();
            }

            return $automation;
        }

        return $team->automations()->create(['name' => $name, ...$attributes]);
    }

    /**
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    private function draftAutomationGraph(Audience $newsletter): array
    {
        return [
            'nodes' => [
                [
                    'id' => 'trigger',
                    'type' => 'trigger',
                    'position' => ['x' => 320, 'y' => 40],
                    'deletable' => false,
                    'data' => [
                        'kind' => AutomationTrigger::Unsubscribed->value,
                        'audience_uuid' => $newsletter->uuid,
                        'tag_uuid' => null,
                    ],
                ],
            ],
            'edges' => [],
        ];
    }

    /**
     * @param  Collection<int, Subscriber>  $subscribers
     * @param  list<'completed'|'failed'|'cancelled'>  $statuses
     */
    private function automationRuns(Automation $automation, Collection $subscribers, array $statuses): void
    {
        foreach ($subscribers->values() as $index => $subscriber) {
            $status = AutomationRunStatus::from($statuses[$index] ?? 'completed');
            $startedAt = now()->subDays(10 - $index)->subHours($index);
            $run = $automation->runs()->firstOrCreate(
                ['subscriber_id' => $subscriber->id],
                [
                    'status' => $status,
                    'graph' => $automation->graph,
                    'context' => ['source' => 'full-demo', 'automation' => $automation->name],
                    'current_node_id' => $status === AutomationRunStatus::Failed ? $this->lastNodeId($automation) : null,
                    'scheduled_at' => $startedAt,
                    'started_at' => $startedAt,
                    'completed_at' => $status === AutomationRunStatus::Completed || $status === AutomationRunStatus::Cancelled
                        ? $startedAt->copy()->addHours(2)
                        : null,
                    'failed_at' => $status === AutomationRunStatus::Failed ? $startedAt->copy()->addHours(2) : null,
                    'failure_reason' => $status === AutomationRunStatus::Failed
                        ? 'The demo provider rejected this message.'
                        : ($status === AutomationRunStatus::Cancelled ? 'The subscriber left the workflow.' : null),
                ],
            );

            $this->automationRunSteps($run, $status, $startedAt);
        }
    }

    private function automationRunSteps(AutomationRun $run, AutomationRunStatus $status, CarbonInterface $startedAt): void
    {
        $nodes = $run->graph['nodes'] ?? [];

        foreach ($nodes as $index => $node) {
            $nodeId = $node['id'] ?? null;

            if (! is_string($nodeId)) {
                continue;
            }

            $isLastNode = $index === array_key_last($nodes);
            $stepStatus = $status === AutomationRunStatus::Failed && $isLastNode
                ? 'failed'
                : ($status === AutomationRunStatus::Cancelled && $isLastNode ? 'cancelled' : 'completed');
            $result = $stepStatus === 'failed'
                ? ['error' => 'The demo provider rejected this message.']
                : ($stepStatus === 'cancelled' ? ['reason' => 'The subscriber left the workflow.'] : ['demo' => true]);

            $run->steps()->firstOrCreate(
                ['node_id' => $nodeId],
                [
                    'status' => $stepStatus,
                    'result' => $result,
                    'processed_at' => $startedAt->copy()->addMinutes($index * 15),
                ],
            );
        }
    }

    private function lastNodeId(Automation $automation): ?string
    {
        $nodes = $automation->graph['nodes'] ?? [];
        $lastNode = $nodes[array_key_last($nodes)] ?? null;

        return is_array($lastNode) && is_string($lastNode['id'] ?? null) ? $lastNode['id'] : null;
    }

    /**
     * @return array<string, Media>
     */
    private function media(Team $team, User $owner): array
    {
        $categories = $this->mediaCategories($team);
        $tags = $this->mediaTags($team);
        $items = [
            ['name' => 'summer-launch.svg', 'title' => 'Summer launch', 'subtitle' => 'A brighter campaign start', 'color' => '#4f46e5', 'category' => 'Product', 'tags' => ['Launch', 'Newsletter']],
            ['name' => 'reporting-overview.svg', 'title' => 'Reporting overview', 'subtitle' => 'See delivery performance clearly', 'color' => '#0f766e', 'category' => 'Product', 'tags' => ['Product', 'Newsletter']],
            ['name' => 'customer-story.svg', 'title' => 'Customer story', 'subtitle' => 'A practical growth story', 'color' => '#be123c', 'category' => 'Social', 'tags' => ['Social', 'Customer']],
            ['name' => 'workshop-banner.svg', 'title' => 'Strategy workshop', 'subtitle' => 'September 2026', 'color' => '#b45309', 'category' => 'Social', 'tags' => ['Launch', 'Social']],
            ['name' => 'studio-mark.svg', 'title' => 'Maildun Studio mark', 'subtitle' => 'Core brand asset', 'color' => '#4338ca', 'category' => 'Brand', 'tags' => ['Brand']],
            ['name' => 'newsletter-cover.svg', 'title' => 'Newsletter cover', 'subtitle' => 'Monthly product dispatch', 'color' => '#0369a1', 'category' => 'Brand', 'tags' => ['Brand', 'Newsletter']],
            ['name' => 'community-card.svg', 'title' => 'Community card', 'subtitle' => 'Meet the people building email', 'color' => '#7c3aed', 'category' => 'Social', 'tags' => ['Social']],
            ['name' => 'customer-onboarding.svg', 'title' => 'Customer onboarding', 'subtitle' => 'A focused first-week journey', 'color' => '#15803d', 'category' => 'Product', 'tags' => ['Customer', 'Product']],
        ];
        $media = [];

        foreach ($items as $item) {
            $path = "media/{$team->uuid}/demo/{$item['name']}";
            $svg = $this->svg($item['title'], $item['subtitle'], $item['color']);
            Storage::disk('public')->put($path, $svg);

            $record = $team->media()->firstOrCreate(
                ['name' => $item['name']],
                [
                    'media_category_id' => $categories[$item['category']]->id,
                    'uploaded_by' => $owner->id,
                    'alt' => $item['title'],
                    'disk' => 'public',
                    'path' => $path,
                    'mime_type' => 'image/svg+xml',
                    'extension' => 'svg',
                    'size' => strlen($svg),
                    'width' => 1600,
                    'height' => 900,
                    'status' => MediaStatus::Ready,
                ],
            );
            $record->tags()->syncWithoutDetaching(
                collect($item['tags'])->map(fn (string $name): int => $tags[$name]->id)->all(),
            );
            $media[$item['name']] = $record;
        }

        $team->media()->firstOrCreate(
            ['name' => 'processing-upload.png'],
            [
                'media_category_id' => $categories['Product']->id,
                'uploaded_by' => $owner->id,
                'disk' => StorageBackend::current()->privateDisk(),
                'upload_path' => "media/{$team->uuid}/pending/processing-upload.png",
                'mime_type' => 'image/png',
                'extension' => 'png',
                'size' => 1_843_200,
                'width' => 1920,
                'height' => 1080,
                'status' => MediaStatus::Processing,
            ],
        );
        $team->media()->firstOrCreate(
            ['name' => 'failed-import.jpg'],
            [
                'media_category_id' => $categories['Product']->id,
                'uploaded_by' => $owner->id,
                'mime_type' => 'image/jpeg',
                'extension' => 'jpg',
                'size' => 928_100,
                'status' => MediaStatus::Failed,
                'failed_reason' => 'Unable to convert this image to a web-safe format.',
            ],
        );

        return $media;
    }

    /**
     * @return array<string, MediaCategory>
     */
    private function mediaCategories(Team $team): array
    {
        $categories = [];

        foreach (['Brand', 'Product', 'Social'] as $name) {
            $categories[$name] = $team->mediaCategories()->firstOrCreate(['name' => $name]);
        }

        return $categories;
    }

    /**
     * @return array<string, MediaTag>
     */
    private function mediaTags(Team $team): array
    {
        $tags = [];

        foreach (['Brand', 'Product', 'Launch', 'Newsletter', 'Social', 'Customer'] as $name) {
            $tags[$name] = $team->mediaTags()->firstOrCreate(['name' => $name]);
        }

        return $tags;
    }

    /**
     * @return array<string, mixed>
     */
    private function emailDesign(string $heading, string $button): array
    {
        return [
            'root' => [
                'type' => 'EmailLayout',
                'data' => [
                    'backdropColor' => '#f8fafc',
                    'canvasColor' => '#ffffff',
                    'textColor' => '#172554',
                    'fontFamily' => 'MODERN_SANS',
                    'childrenIds' => ['heading', 'copy', 'button'],
                ],
            ],
            'heading' => [
                'type' => 'Heading',
                'data' => [
                    'style' => ['padding' => ['top' => 36, 'bottom' => 12, 'left' => 28, 'right' => 28]],
                    'props' => ['text' => $heading, 'level' => 'h1'],
                ],
            ],
            'copy' => [
                'type' => 'Text',
                'data' => [
                    'style' => ['padding' => ['top' => 0, 'bottom' => 20, 'left' => 28, 'right' => 28]],
                    'props' => ['text' => 'A clear, friendly message that helps the reader take the next step.'],
                ],
            ],
            'button' => [
                'type' => 'Button',
                'data' => [
                    'style' => ['padding' => ['top' => 0, 'bottom' => 36, 'left' => 28, 'right' => 28]],
                    'props' => [
                        'text' => $button,
                        'url' => 'https://maildun.test',
                        'buttonBackgroundColor' => '#4f46e5',
                        'buttonTextColor' => '#ffffff',
                        'buttonStyle' => 'rounded',
                        'size' => 'medium',
                    ],
                ],
            ],
        ];
    }

    private function emailHtml(string $heading, string $copy): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
            <body style="margin:0;padding:32px 0;background:#f8fafc;color:#172554;font-family:Arial,sans-serif;">
                <table align="center" role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:16px;">
                    <tr>
                        <td style="padding:36px 28px;">
                            <h1 style="margin:0 0 16px;font-size:28px;line-height:1.2;">{$heading}</h1>
                            <p style="margin:0;font-size:16px;line-height:1.6;">{$copy}</p>
                        </td>
                    </tr>
                </table>
            </body>
        </html>
        HTML;
    }

    private function svg(string $title, string $subtitle, string $color): string
    {
        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="1600" height="900" viewBox="0 0 1600 900" role="img" aria-labelledby="title description">
            <title id="title">{$title}</title>
            <desc id="description">{$subtitle}</desc>
            <defs>
                <linearGradient id="background" x1="0" x2="1" y1="0" y2="1">
                    <stop offset="0" stop-color="{$color}" />
                    <stop offset="1" stop-color="#0f172a" />
                </linearGradient>
            </defs>
            <rect width="1600" height="900" fill="url(#background)" rx="48" />
            <circle cx="1320" cy="160" r="260" fill="#ffffff" fill-opacity="0.12" />
            <circle cx="190" cy="760" r="300" fill="#ffffff" fill-opacity="0.08" />
            <text x="120" y="390" fill="#ffffff" font-family="Arial, sans-serif" font-size="88" font-weight="700">{$title}</text>
            <text x="124" y="475" fill="#ffffff" fill-opacity="0.84" font-family="Arial, sans-serif" font-size="38">{$subtitle}</text>
            <rect x="124" y="570" width="240" height="72" rx="36" fill="#ffffff" fill-opacity="0.2" />
            <text x="168" y="617" fill="#ffffff" font-family="Arial, sans-serif" font-size="28" font-weight="700">MAILDUN STUDIO</text>
        </svg>
        SVG;
    }
}
