<?php

use App\Enums\CampaignSeriesGoal;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailProvider;
use App\Enums\EmailStatus;
use App\Enums\TeamRole;
use App\Models\Audience;
use App\Models\CampaignSeries;
use App\Models\Email;
use App\Models\EmailConversionEvent;
use App\Models\EmailDelivery;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the campaign series list only shows series from the current team', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    CampaignSeries::factory()->for($team)->create(['name' => 'Demo pipeline']);
    CampaignSeries::factory()->for(Team::factory()->create())->create(['name' => 'Other team series']);

    $this->actingAs($user)
        ->get(route('campaign_series.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaign-series/index')
            ->where('canManage', true)
            ->has('series.data', 1)
            ->where('series.data.0.name', 'Demo pipeline')
            ->has('goals', count(CampaignSeriesGoal::cases())));
});

test('a campaign series can be created and updated', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->actingAs($user)
        ->post(route('campaign_series.store', $team), [
            'name' => 'Q4 demo sequence',
            'description' => 'Follow up with qualified leads.',
            'goal' => CampaignSeriesGoal::BookMeetings->value,
            'objective' => 'Book 30 demos',
            'primary_cta_url' => 'https://example.com/book-demo',
        ])
        ->assertRedirect();

    $campaignSeries = $team->campaignSeries()->sole();
    $response->assertRedirect(route('campaign_series.show', [$team, $campaignSeries]));
    expect($campaignSeries)
        ->name->toBe('Q4 demo sequence')
        ->goal->toBe(CampaignSeriesGoal::BookMeetings)
        ->objective->toBe('Book 30 demos');

    $this->actingAs($user)
        ->patch(route('campaign_series.update', [$team, $campaignSeries]), [
            'name' => 'Q4 enterprise demos',
            'description' => '',
            'goal' => CampaignSeriesGoal::GenerateLeads->value,
            'objective' => '',
            'primary_cta_url' => '',
        ])
        ->assertRedirect();

    expect($campaignSeries->fresh())
        ->name->toBe('Q4 enterprise demos')
        ->goal->toBe(CampaignSeriesGoal::GenerateLeads)
        ->description->toBeNull()
        ->objective->toBeNull()
        ->primary_cta_url->toBeNull();
});

test('a campaign series requires a valid sales goal and primary CTA URL', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('campaign_series.store', $user->currentTeam), [
            'name' => '',
            'goal' => 'unknown',
            'primary_cta_url' => 'javascript:alert(1)',
        ])
        ->assertInvalid(['name', 'goal', 'primary_cta_url']);

    $this->assertDatabaseCount('campaign_series', 0);
});

test('an existing campaign can be added to and removed from a series', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $campaignSeries = CampaignSeries::factory()->for($team)->create();
    $campaign = Email::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('campaign_series.campaigns.store', [$team, $campaignSeries]), [
            'campaign_uuids' => [$campaign->uuid],
        ])
        ->assertRedirect();

    expect($campaign->fresh()->campaign_series_id)->toBe($campaignSeries->id);

    $this->actingAs($user)
        ->delete(route('campaign_series.campaigns.destroy', [$team, $campaignSeries, $campaign]))
        ->assertRedirect();

    expect($campaign->fresh()->campaign_series_id)->toBeNull();
});

test('campaigns from another team or another series cannot be added', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $campaignSeries = CampaignSeries::factory()->for($team)->create();
    $otherSeries = CampaignSeries::factory()->for($team)->create();
    $assignedCampaign = Email::factory()->for($team)->for($otherSeries, 'campaignSeries')->create();
    $foreignCampaign = Email::factory()->for(Team::factory()->create())->create();

    $this->actingAs($user)
        ->post(route('campaign_series.campaigns.store', [$team, $campaignSeries]), [
            'campaign_uuids' => [$assignedCampaign->uuid, $foreignCampaign->uuid],
        ])
        ->assertInvalid('campaign_uuids.0');

    expect($assignedCampaign->fresh()->campaign_series_id)->toBe($otherSeries->id)
        ->and($foreignCampaign->fresh()->campaign_series_id)->toBeNull();
});

test('a new campaign can be composed directly inside a series', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $campaignSeries = CampaignSeries::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('emails.store', $team), [
            'name' => 'Demo follow-up',
            'campaign_series' => $campaignSeries->uuid,
        ])
        ->assertRedirect();

    expect($team->emails()->sole())
        ->name->toBe('Demo follow-up')
        ->campaign_series_id->toBe($campaignSeries->id);
});

test('a campaign cannot be composed inside another teams series', function () {
    $user = User::factory()->create();
    $foreignSeries = CampaignSeries::factory()->for(Team::factory()->create())->create();

    $this->actingAs($user)
        ->post(route('emails.store', $user->currentTeam), [
            'name' => 'Cross-team campaign',
            'campaign_series' => $foreignSeries->uuid,
        ])
        ->assertInvalid('campaign_series');

    expect($user->currentTeam->emails()->count())->toBe(0);
});

test('the series report deduplicates people and compares campaign aggregates', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $firstAudience = Audience::factory()->for($team)->create(['name' => 'Leads']);
    $secondAudience = Audience::factory()->for($team)->create(['name' => 'Customers']);
    $campaignSeries = CampaignSeries::factory()->for($team)->create([
        'name' => 'Demo conversion',
        'goal' => CampaignSeriesGoal::BookMeetings,
        'primary_cta_url' => 'https://example.com/book-demo',
    ]);
    $firstCampaign = Email::factory()->for($team)->for($campaignSeries, 'campaignSeries')->create([
        'name' => 'Initial pitch',
        'audience_id' => $firstAudience->id,
        'status' => EmailStatus::Sent,
        'recipient_count' => 2,
        'sent_at' => now()->subDay(),
    ]);
    $secondCampaign = Email::factory()->for($team)->for($campaignSeries, 'campaignSeries')->create([
        'name' => 'Follow-up proof',
        'audience_id' => $secondAudience->id,
        'status' => EmailStatus::Sent,
        'recipient_count' => 2,
        'sent_at' => now(),
    ]);

    EmailDelivery::factory()->for($firstCampaign)->create([
        'email_address' => 'alice@example.com',
        'status' => EmailDeliveryStatus::Delivered,
        'provider' => EmailProvider::AmazonSes,
        'delivered_at' => now(),
        'first_opened_at' => now(),
        'first_clicked_at' => now(),
    ]);
    EmailDelivery::factory()->for($firstCampaign)->create([
        'email_address' => 'bob@example.com',
        'status' => EmailDeliveryStatus::Delivered,
        'provider' => EmailProvider::AmazonSes,
        'delivered_at' => now(),
    ]);
    EmailDelivery::factory()->for($secondCampaign)->create([
        'email_address' => 'ALICE@example.com',
        'status' => EmailDeliveryStatus::Delivered,
        'provider' => EmailProvider::AmazonSes,
        'delivered_at' => now(),
        'first_opened_at' => now(),
        'first_clicked_at' => now(),
    ]);
    EmailDelivery::factory()->for($secondCampaign)->create([
        'email_address' => 'carol@example.com',
        'status' => EmailDeliveryStatus::Delivered,
        'provider' => EmailProvider::AmazonSes,
        'delivered_at' => now(),
        'first_opened_at' => now(),
    ]);
    $firstCampaign->trackingAggregate()->create([
        'unique_opens_count' => 1,
        'unique_clicks_count' => 1,
    ]);
    $secondCampaign->trackingAggregate()->create([
        'unique_opens_count' => 2,
        'unique_clicks_count' => 1,
    ]);
    $firstLink = $firstCampaign->links()->create([
        'url' => $campaignSeries->primary_cta_url,
        'url_hash' => hash('sha256', $campaignSeries->primary_cta_url),
        'position' => 0,
    ]);
    $firstLink->trackingAggregate()->create([
        'total_clicks_count' => 2,
        'unique_clicks_count' => 1,
    ]);
    $secondLink = $secondCampaign->links()->create([
        'url' => $campaignSeries->primary_cta_url,
        'url_hash' => hash('sha256', $campaignSeries->primary_cta_url),
        'position' => 0,
    ]);
    $secondLink->trackingAggregate()->create([
        'total_clicks_count' => 3,
        'unique_clicks_count' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('campaign_series.show', [$team, $campaignSeries]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaign-series/show')
            ->where('report.summary.unique_recipients', 3)
            ->where('report.summary.opened', 2)
            ->where('report.summary.clicked', 1)
            ->where('report.summary.cta_clicks', 5)
            ->where('report.summary.delivery_rate', 100)
            ->where('report.summary.open_rate', 66.7)
            ->where('report.summary.click_rate', 33.3)
            ->where('report.summary.click_to_open_rate', 50)
            ->where('report.has_mixed_recipients', true)
            ->has('report.campaigns', 2)
            ->where('report.campaigns.0.name', 'Initial pitch')
            ->where('report.campaigns.0.open_rate', 50)
            ->where('report.campaigns.1.name', 'Follow-up proof')
            ->where('report.campaigns.1.open_rate', 100));
});

test('the series report counts primary CTA clicks on links tagged with a campaign query string', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $campaignSeries = CampaignSeries::factory()->for($team)->create([
        'primary_cta_url' => 'https://example.com/book-demo#form',
    ]);
    $tagged = Email::factory()->for($team)->for($campaignSeries, 'campaignSeries')->create([
        'status' => EmailStatus::Sent,
        'sent_at' => now(),
        'query_string' => 'utm_source=maildun&utm_campaign=pitch',
    ]);
    $ctaLink = $tagged->links()->create([
        'url' => 'https://example.com/book-demo?utm_source=maildun&utm_campaign=pitch#form',
        'url_hash' => hash('sha256', 'https://example.com/book-demo?utm_source=maildun&utm_campaign=pitch#form'),
        'position' => 0,
    ]);
    $ctaLink->trackingAggregate()->create(['total_clicks_count' => 4, 'unique_clicks_count' => 2]);
    $otherLink = $tagged->links()->create([
        'url' => 'https://example.com/pricing?utm_source=maildun&utm_campaign=pitch',
        'url_hash' => hash('sha256', 'https://example.com/pricing?utm_source=maildun&utm_campaign=pitch'),
        'position' => 1,
    ]);
    $otherLink->trackingAggregate()->create(['total_clicks_count' => 9, 'unique_clicks_count' => 9]);

    $this->actingAs($user)
        ->get(route('campaign_series.show', [$team, $campaignSeries]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.summary.cta_clicks', 4)
            ->where('report.campaigns.0.cta_clicks', 4));
});

test('the series report counts Amazon SES deliveries toward delivery feedback', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $campaignSeries = CampaignSeries::factory()->for($team)->create();
    $campaign = Email::factory()->for($team)->for($campaignSeries, 'campaignSeries')->create([
        'status' => EmailStatus::Sent,
        'recipient_count' => 2,
        'sent_at' => now(),
    ]);

    EmailDelivery::factory()->for($campaign)->create([
        'email_address' => 'alice@example.com',
        'status' => EmailDeliveryStatus::Delivered,
        'provider' => EmailProvider::AmazonSes,
        'delivered_at' => now(),
    ]);
    EmailDelivery::factory()->for($campaign)->create([
        'email_address' => 'bob@example.com',
        'status' => EmailDeliveryStatus::Sent,
        'provider' => EmailProvider::AmazonSes,
    ]);
    $campaign->trackingAggregate()->create();

    $this->actingAs($user)
        ->get(route('campaign_series.show', [$team, $campaignSeries]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaign-series/show')
            ->where('report.summary.delivery_feedback', 'available')
            ->where('report.summary.feedback_recipient_count', 2)
            ->where('report.summary.delivered', 1)
            ->where('report.summary.delivery_rate', 50)
            ->where('report.campaigns.0.delivery_feedback', 'available')
            ->where('report.campaigns.0.delivery_rate', 50));
});

test('deleting a series keeps its campaigns', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $campaignSeries = CampaignSeries::factory()->for($team)->create();
    $campaign = Email::factory()->for($team)->for($campaignSeries, 'campaignSeries')->create();

    $this->actingAs($user)
        ->delete(route('campaign_series.destroy', [$team, $campaignSeries]))
        ->assertRedirect(route('campaign_series.index', $team));

    $this->assertDatabaseMissing('campaign_series', ['id' => $campaignSeries->id]);
    expect($campaign->fresh())->not->toBeNull()
        ->and($campaign->fresh()->campaign_series_id)->toBeNull();
});

test('viewers can read campaign series but cannot change them', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($viewer, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $viewer);
    $campaignSeries = CampaignSeries::factory()->for($team)->create();
    $campaign = Email::factory()->for($team)->create();

    $this->actingAs($viewer)
        ->get(route('campaign_series.show', [$team, $campaignSeries]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

    $this->actingAs($viewer)
        ->post(route('campaign_series.store', $team), [
            'name' => 'Not allowed',
            'goal' => CampaignSeriesGoal::BookMeetings->value,
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('campaign_series.campaigns.store', [$team, $campaignSeries]), [
            'campaign_uuids' => [$campaign->uuid],
        ])
        ->assertForbidden();

    expect($campaign->fresh()->campaign_series_id)->toBeNull();
});

test('a campaign series cannot be reached through another team', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $campaignSeries = CampaignSeries::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->get(route('campaign_series.show', [$otherTeam, $campaignSeries]))
        ->assertNotFound();
});

test('a campaign series stores its conversion tracking settings', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('campaign_series.store', $team), [
            'name' => 'Q4 demo sequence',
            'goal' => CampaignSeriesGoal::BookMeetings->value,
            'conversion_event_key' => 'meeting_booked',
            'attribution_window_days' => 14,
            'conversion_target' => 30,
            'value_currency' => 'eur',
        ])
        ->assertRedirect();

    expect($team->campaignSeries()->sole())
        ->conversion_event_key->toBe('meeting_booked')
        ->attribution_window_days->toBe(14)
        ->conversion_target->toBe(30)
        ->value_currency->toBe('EUR');
});

test('clearing the conversion event makes a series engagement only with the default window', function () {
    $user = User::factory()->create();
    $campaignSeries = CampaignSeries::factory()->for($user->currentTeam)->tracksConversions('meeting_booked', 7)->create();

    $this->actingAs($user)
        ->patch(route('campaign_series.update', [$user->currentTeam, $campaignSeries]), [
            'name' => 'Engagement only',
            'goal' => CampaignSeriesGoal::BookMeetings->value,
            'conversion_event_key' => '',
        ])
        ->assertRedirect();

    expect($campaignSeries->fresh())
        ->conversion_event_key->toBeNull()
        ->attribution_window_days->toBe(30);
});

test('campaign series conversion settings are validated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('campaign_series.store', $user->currentTeam), [
            'name' => 'Q4 demo sequence',
            'goal' => CampaignSeriesGoal::BookMeetings->value,
            'conversion_event_key' => 'Meeting Booked',
            'attribution_window_days' => 0,
            'conversion_target' => -5,
            'value_currency' => 'dollars',
        ])
        ->assertInvalid([
            'conversion_event_key' => 'Use lowercase letters, numbers, and underscores, starting with a letter, such as meeting_booked.',
            'attribution_window_days' => 'The attribution window days field must be at least 1.',
            'conversion_target' => 'The conversion target field must be at least 1.',
            'value_currency' => 'Use a three-letter currency code such as USD.',
        ]);

    $this->assertDatabaseCount('campaign_series', 0);
});

test('the series report totals conversions, value, target progress and the conversion leader', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $campaignSeries = CampaignSeries::factory()->for($team)->tracksConversions()->create([
        'conversion_target' => 4,
        'value_currency' => 'USD',
    ]);
    $firstCampaign = Email::factory()->for($team)->for($campaignSeries, 'campaignSeries')->create([
        'name' => 'Initial pitch',
        'status' => EmailStatus::Sent,
        'recipient_count' => 2,
        'sent_at' => now()->subDays(2),
    ]);
    $secondCampaign = Email::factory()->for($team)->for($campaignSeries, 'campaignSeries')->create([
        'name' => 'Follow-up proof',
        'status' => EmailStatus::Sent,
        'recipient_count' => 2,
        'sent_at' => now()->subDay(),
    ]);
    $aliceClick = EmailDelivery::factory()->for($firstCampaign)->create([
        'email_address' => 'alice@example.com',
        'sent_at' => now()->subDays(2),
        'first_clicked_at' => now()->subDays(2),
    ]);
    EmailDelivery::factory()->for($firstCampaign)->create([
        'email_address' => 'bob@example.com',
        'sent_at' => now()->subDays(2),
    ]);
    EmailDelivery::factory()->for($secondCampaign)->create([
        'email_address' => 'ALICE@example.com',
        'sent_at' => now()->subDay(),
    ]);
    EmailDelivery::factory()->for($secondCampaign)->create([
        'email_address' => 'carol@example.com',
        'sent_at' => now()->subDay(),
        'first_clicked_at' => now()->subDay(),
    ]);
    EmailConversionEvent::factory()->clickedFrom($aliceClick)->worth(250000, 'USD')->create();
    EmailConversionEvent::factory()->clickedFrom($aliceClick)->worth(10000, 'EUR')->create();
    EmailConversionEvent::factory()->assistedBy($campaignSeries)->worth(5000, 'USD')->create(['email' => 'bob@example.com']);
    EmailConversionEvent::factory()->for($team)->create(['email' => 'carol@example.com']);

    $this->actingAs($user)
        ->get(route('campaign_series.show', [$team, $campaignSeries]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaign-series/show')
            ->where('series.conversion_event_key', 'meeting_booked')
            ->where('report.summary.conversion_tracking', true)
            ->where('report.summary.unique_recipients', 3)
            ->where('report.summary.conversions', 2)
            ->where('report.summary.attributed_conversions', 1)
            ->where('report.summary.assisted_conversions', 1)
            ->where('report.summary.conversion_rate', 66.7)
            ->where('report.summary.target_progress', 50)
            ->where('report.summary.conversion_value', [
                ['currency' => 'USD', 'value_minor' => 255000],
                ['currency' => 'EUR', 'value_minor' => 10000],
            ])
            ->where('report.summary.contact_groups', [
                'converted' => 2,
                'clicked_not_converted' => 1,
                'opened_not_clicked' => 0,
                'not_engaged' => 0,
            ])
            ->where('report.campaigns.0.conversions', 1)
            ->where('report.campaigns.0.conversion_rate', 50)
            ->where('report.campaigns.0.conversion_value', [
                ['currency' => 'USD', 'value_minor' => 250000],
                ['currency' => 'EUR', 'value_minor' => 10000],
            ])
            ->where('report.campaigns.0.is_conversion_leader', true)
            ->where('report.campaigns.1.conversions', 0)
            ->where('report.campaigns.1.is_conversion_leader', false)
            ->where('contactGroup', null));
});

test('a tie on conversion rate names no conversion leader', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $campaignSeries = CampaignSeries::factory()->for($team)->tracksConversions()->create();
    $campaigns = Email::factory()->count(2)->for($team)->for($campaignSeries, 'campaignSeries')->create([
        'status' => EmailStatus::Sent,
        'recipient_count' => 1,
        'sent_at' => now()->subDay(),
    ]);

    foreach ($campaigns as $campaign) {
        $delivery = EmailDelivery::factory()->for($campaign)->create([
            'sent_at' => now()->subDay(),
            'first_clicked_at' => now()->subDay(),
        ]);
        EmailConversionEvent::factory()->clickedFrom($delivery)->create();
    }

    $this->actingAs($user)
        ->get(route('campaign_series.show', [$team, $campaignSeries]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.campaigns.0.conversions', 1)
            ->where('report.campaigns.0.is_conversion_leader', false)
            ->where('report.campaigns.1.is_conversion_leader', false));
});

test('an engagement-only series reports no conversion tracking', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $campaignSeries = CampaignSeries::factory()->for($team)->create();

    $this->actingAs($user)
        ->get(route('campaign_series.show', [$team, $campaignSeries]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('series.conversion_event_key', null)
            ->where('report.summary.conversion_tracking', false)
            ->where('report.summary.conversions', 0)
            ->where('report.summary.conversion_value', [])
            ->where('report.summary.target_progress', null)
            ->has('report.campaigns', 0));
});

test('viewers can drill into the people of a follow-up group', function (string $group, string $expectedEmail, int $expectedConversions) {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($viewer, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $viewer);
    $campaignSeries = CampaignSeries::factory()->for($team)->tracksConversions()->create();
    $campaign = Email::factory()->for($team)->for($campaignSeries, 'campaignSeries')->create([
        'status' => EmailStatus::Sent,
        'recipient_count' => 4,
        'sent_at' => now()->subDay(),
    ]);
    $converted = EmailDelivery::factory()->for($campaign)->create([
        'email_address' => 'converted@example.com',
        'sent_at' => now()->subDay(),
        'first_clicked_at' => now()->subDay(),
    ]);
    EmailDelivery::factory()->for($campaign)->create([
        'email_address' => 'Clicked@Example.com',
        'sent_at' => now()->subDay(),
        'first_opened_at' => now()->subDay(),
        'first_clicked_at' => now()->subDay(),
        'last_clicked_at' => now()->subDay(),
    ]);
    EmailDelivery::factory()->for($campaign)->create([
        'email_address' => 'opened@example.com',
        'sent_at' => now()->subDay(),
        'first_opened_at' => now()->subDay(),
    ]);
    EmailDelivery::factory()->for($campaign)->create([
        'email_address' => 'quiet@example.com',
        'sent_at' => now()->subDay(),
    ]);
    EmailConversionEvent::factory()->count(2)->clickedFrom($converted)->create();

    $this->actingAs($viewer)
        ->get(route('campaign_series.show', [$team, $campaignSeries, 'contacts' => $group]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canManage', false)
            ->where('contactGroup.group', $group)
            ->where('contactGroup.total', 1)
            ->where('contactGroup.contacts.0.email', $expectedEmail)
            ->where('contactGroup.contacts.0.conversions', $expectedConversions)
            ->has('contactGroup.contacts', 1));
})->with([
    'converted' => ['converted', 'converted@example.com', 2],
    'clicked but not converted' => ['clicked_not_converted', 'clicked@example.com', 0],
    'opened but did not click' => ['opened_not_clicked', 'opened@example.com', 0],
    'not engaged' => ['not_engaged', 'quiet@example.com', 0],
]);
