<?php

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailStatus;
use App\Enums\EmailTrackingClassification;
use App\Enums\EmailTrackingInsightDimension;
use App\Enums\TeamRole;
use App\Models\Email;
use App\Models\EmailTrackingEvent;
use App\Models\EmailTrackingInsightAggregate;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('the full demo seeder creates one idempotent, populated workspace', function () {
    Storage::fake('public');

    $this->seed(DatabaseSeeder::class);

    $owner = User::query()->where('email', AdminUserSeeder::DEMO_EMAIL)->sole();
    $team = Team::query()->where('slug', 'maildun-studio')->sole();
    $counts = [
        'audiences' => $team->audiences()->count(),
        'subscribers' => $team->subscribers()->count(),
        'campaigns' => $team->emails()->count(),
        'deliveries' => Email::query()->where('team_id', $team->id)->withCount('deliveries')->get()->sum('deliveries_count'),
        'transactional_emails' => $team->transactionalEmails()->count(),
        'transactional_deliveries' => $team->transactionalEmailDeliveries()->count(),
        'automations' => $team->automations()->count(),
        'runs' => $team->automations()->withCount('runs')->get()->sum('runs_count'),
        'media' => $team->media()->count(),
    ];
    $team->emailIntegration()->sole()->update([
        'name' => 'Demo Amazon SES (configure before sending)',
    ]);
    $team->emails()
        ->where('name', 'Campaign Insights v2 launch')
        ->sole()
        ->update([
            'name' => 'July product update',
            'subject' => 'Legacy campaign subject',
        ]);

    $this->seed(DatabaseSeeder::class);

    $team->refresh();
    $campaign = $team->emails()->where('name', 'Campaign Insights v2 launch')->sole();
    $humanInsights = $campaign->insightAggregates()
        ->where('classification', EmailTrackingClassification::Human)
        ->where('dimension', EmailTrackingInsightDimension::Classification)
        ->sole();
    $trafficInsights = $campaign->insightAggregates()
        ->where('dimension', EmailTrackingInsightDimension::Classification)
        ->get()
        ->keyBy(fn (EmailTrackingInsightAggregate $aggregate): string => $aggregate->classification->value);

    expect($owner->fresh()->current_team_id)->toBe($team->id)
        ->and($owner->teamRole($team))->toBe(TeamRole::Owner->value)
        ->and($team->audiences()->count())->toBe(5)
        ->and($team->tags()->count())->toBe(5)
        ->and($team->audiences()->where('name', 'Product newsletter')->sole()->subscribers()->count())->toBe(72)
        ->and($team->subscribers()->whereNull('subscribers.contact_id')->count())->toBe(0)
        ->and($team->audiences()->where('name', 'Product newsletter')->sole()->subscribers()->has('tags')->count())->toBe(72)
        ->and($campaign->status)->toBe(EmailStatus::Sent)
        ->and($campaign->subject)->toBe('Campaign Insights v2: human engagement you can trust')
        ->and($campaign->deliveries()->where('status', EmailDeliveryStatus::Delivered)->count())->toBe(30)
        ->and($campaign->links()->orderBy('position')->pluck('url')->all())->toBe([
            'https://maildun.test/releases/campaign-insights-v2',
            'https://maildun.test/guides/human-engagement',
            'https://maildun.test/privacy/email-tracking',
        ])
        ->and($team->emailTemplates()->count())->toBe(2)
        ->and($team->transactionalEmails()->where('status', 'published')->count())->toBe(2)
        ->and($team->transactionalEmailDeliveries()->count())->toBe(4)
        ->and($team->automations()->where('status', 'active')->count())->toBe(1)
        ->and($team->media()->where('status', 'ready')->count())->toBe(8)
        ->and($team->mediaCategories()->count())->toBe(3)
        ->and($team->mediaTags()->count())->toBe(6)
        ->and($team->apiKeys()->count())->toBe(2)
        ->and($team->emailIntegration()->count())->toBe(1)
        ->and($team->emailIntegration()->sole()->name)->toBe('Production SES')
        ->and($humanInsights->total_opens_count)->toBe(17)
        ->and($humanInsights->unique_opens_count)->toBe(12)
        ->and($humanInsights->total_clicks_count)->toBe(5)
        ->and($humanInsights->unique_clicks_count)->toBe(4)
        ->and($trafficInsights)->toHaveKeys(['human', 'bot', 'privacy_proxy', 'unknown'])
        ->and($trafficInsights['bot']->unique_opens_count)->toBe(1)
        ->and($trafficInsights['bot']->unique_clicks_count)->toBe(1)
        ->and($trafficInsights['privacy_proxy']->unique_opens_count)->toBe(1)
        ->and($trafficInsights['privacy_proxy']->unique_clicks_count)->toBe(1)
        ->and($trafficInsights['unknown']->unique_opens_count)->toBe(1)
        ->and($trafficInsights['unknown']->unique_clicks_count)->toBe(0)
        ->and($campaign->insightAggregates()
            ->where('classification', EmailTrackingClassification::Human)
            ->where('dimension', EmailTrackingInsightDimension::Country)
            ->pluck('dimension_key')
            ->sort()
            ->values()
            ->all())->toBe(['DE', 'GB', 'SG', 'US'])
        ->and($campaign->insightAggregates()
            ->where('classification', EmailTrackingClassification::Human)
            ->where('dimension', EmailTrackingInsightDimension::Client)
            ->pluck('dimension_key')
            ->sort()
            ->values()
            ->all())->toBe(['chrome', 'outlook', 'safari', 'thunderbird'])
        ->and($campaign->insightAggregates()
            ->where('classification', EmailTrackingClassification::Human)
            ->where('dimension', EmailTrackingInsightDimension::Device)
            ->pluck('dimension_key')
            ->sort()
            ->values()
            ->all())->toBe(['desktop', 'mobile'])
        ->and(EmailTrackingEvent::query()
            ->whereHas('delivery', fn ($query) => $query->where('email_id', $campaign->id))
            ->count())->toBe(33)
        ->and(EmailTrackingEvent::query()
            ->whereHas('delivery', fn ($query) => $query->where('email_id', $campaign->id))
            ->whereNull('processed_at')
            ->count())->toBe(0)
        ->and($team->emailIntegration()->sole()->isVerified())->toBeFalse()
        ->and($team->audiences()->count())->toBe($counts['audiences'])
        ->and($team->subscribers()->count())->toBe($counts['subscribers'])
        ->and($team->emails()->count())->toBe($counts['campaigns'])
        ->and(Email::query()->where('team_id', $team->id)->withCount('deliveries')->get()->sum('deliveries_count'))->toBe($counts['deliveries'])
        ->and($team->transactionalEmails()->count())->toBe($counts['transactional_emails'])
        ->and($team->transactionalEmailDeliveries()->count())->toBe($counts['transactional_deliveries'])
        ->and($team->automations()->count())->toBe($counts['automations'])
        ->and($team->automations()->withCount('runs')->get()->sum('runs_count'))->toBe($counts['runs'])
        ->and($team->media()->count())->toBe($counts['media']);

    Storage::disk('public')->assertExists("media/{$team->uuid}/demo/summer-launch.svg");
});

test('the seeded workspace has populated dashboard and feature pages', function () {
    Storage::fake('public');

    $this->seed(DatabaseSeeder::class);

    $owner = User::query()->where('email', AdminUserSeeder::DEMO_EMAIL)->sole();
    $team = Team::query()->where('slug', 'maildun-studio')->sole();
    $campaign = $team->emails()->where('name', 'Campaign Insights v2 launch')->sole();

    $this->actingAs($owner)
        ->get(route('dashboard', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.overview.activeAutomations', 1)
            ->has('dashboard.subscriberGrowth', 30)
            ->has('dashboard.recentCampaigns', 4));

    $this->actingAs($owner)
        ->get(route('emails.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('emails/index')
            ->has('emails.data', 5)
            ->has('templates', 4));

    $this->actingAs($owner)
        ->get(route('emails.show', [$team, $campaign]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('emails/show')
            ->where('metrics.delivered', 30)
            ->where('metrics.bounced', 2)
            ->where('metrics.opened', 14)
            ->where('metrics.clicked', 6)
            ->where('insights.human.opened', 12)
            ->where('insights.human.clicked', 4)
            ->where('insights.human.open_rate', 33.3)
            ->where('insights.human.click_rate', 11.1)
            ->where('insights.traffic.1.unique_opens', 1)
            ->where('insights.traffic.2.unique_opens', 1)
            ->where('insights.traffic.3.unique_opens', 1)
            ->has('insights.locations.countries', 4));

    $this->actingAs($owner)
        ->get(route('automations.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('automations/index')
            ->has('automations.data', 3));

    $this->actingAs($owner)
        ->get(route('media.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('media/index')
            ->has('media.data', 10)
            ->has('categories', 3)
            ->has('tags', 6));
});
