<?php

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailProvider;
use App\Enums\EmailStatus;
use App\Enums\SubscriberStatus;
use App\Enums\TeamRole;
use App\Models\Audience;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\TeamInvitation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
});

test('dashboard includes team scoped activity metrics', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->create(['team_id' => $team->id]);
    $otherAudience = Audience::factory()->create();

    Subscriber::factory()->create([
        'audience_id' => $audience->id,
        'status' => SubscriberStatus::Subscribed,
        'subscribed_at' => now()->subDays(2),
    ]);
    Subscriber::factory()->create([
        'audience_id' => $otherAudience->id,
        'status' => SubscriberStatus::Subscribed,
        'subscribed_at' => now()->subDays(2),
    ]);

    $campaign = Email::factory()->create([
        'team_id' => $team->id,
        'status' => EmailStatus::Sent,
        'recipient_count' => 2,
        'send_started_at' => now()->subDay(),
        'sent_at' => now()->subDay(),
    ]);
    EmailDelivery::factory()->create([
        'email_id' => $campaign->id,
        'provider' => EmailProvider::AmazonSes,
        'sent_at' => now()->subDay(),
        'delivered_at' => now()->subDay(),
        'first_opened_at' => now()->subDay(),
        'first_clicked_at' => now()->subDay(),
    ]);
    EmailDelivery::factory()->create([
        'email_id' => $campaign->id,
        'provider' => EmailProvider::AmazonSes,
        'status' => EmailDeliveryStatus::Bounced,
        'sent_at' => now()->subDay(),
        'bounced_at' => now()->subDay(),
    ]);

    $failedCampaign = Email::factory()->create([
        'team_id' => $team->id,
        'status' => EmailStatus::Failed,
    ]);
    EmailDelivery::factory()->create([
        'email_id' => $failedCampaign->id,
        'status' => EmailDeliveryStatus::Failed,
        'send_attempted_at' => now()->subDay(),
    ]);

    $draft = Email::factory()->create([
        'team_id' => $team->id,
        'status' => EmailStatus::Draft,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.overview.subscribers', 1)
        ->where('dashboard.overview.newSubscribers', 1)
        ->where('dashboard.overview.deliveryRate', 50)
        ->where('dashboard.overview.openRate', 50)
        ->where('dashboard.overview.clickRate', 50)
        ->where('dashboard.period', 30)
        ->where('dashboard.deliveryIssues.failed', 1)
        ->where('dashboard.deliveryIssues.bounced', 1)
        ->where('dashboard.deliveryIssues.complained', 0)
        ->has('dashboard.performance', 30)
        ->has('dashboard.performance.0', fn (Assert $point) => $point
            ->hasAll(['date', 'subscribers', 'sent', 'opens', 'clicks']))
        ->has('dashboard.draftCampaigns', 1)
        ->where('dashboard.draftCampaigns.0.uuid', $draft->uuid)
        ->has('dashboard.recentCampaigns', 1)
        ->where('dashboard.recentCampaigns.0.uuid', $campaign->uuid)
        ->where('dashboard.recentCampaigns.0.delivered', 1)
        ->where('dashboard.recentCampaigns.0.deliveryReported', true)
        ->where('dashboard.recentCampaigns.0.opened', 1)
        ->where('dashboard.recentCampaigns.0.clicked', 1)
        ->where('dashboard.recentCampaigns.0.deliveryRate', 50)
        ->where('dashboard.recentCampaigns.0.openRate', 50)
        ->where('dashboard.recentCampaigns.0.clickRate', 50)
        ->where('canManageCampaigns', true),
    );
});

test('dashboard does not report a delivery rate for SMTP-only sends', function () {
    $user = User::factory()->create();
    $campaign = Email::factory()->create([
        'team_id' => $user->currentTeam->id,
        'recipient_count' => 1,
        'send_started_at' => now()->subDay(),
        'sent_at' => now()->subDay(),
    ]);
    EmailDelivery::factory()->create([
        'email_id' => $campaign->id,
        'provider' => EmailProvider::Smtp,
        'sent_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.overview.deliveryRate', null)
            ->where('dashboard.recentCampaigns.0.deliveryReported', false));
});

test('dashboard delivery rate leaves SMTP sends out of a mixed campaign', function () {
    $user = User::factory()->create();
    $campaign = Email::factory()->create([
        'team_id' => $user->currentTeam->id,
        'status' => EmailStatus::Sent,
        'recipient_count' => 3,
        'send_started_at' => now()->subDay(),
        'sent_at' => now()->subDay(),
    ]);
    EmailDelivery::factory()->create([
        'email_id' => $campaign->id,
        'provider' => EmailProvider::AmazonSes,
        'sent_at' => now()->subDay(),
        'delivered_at' => now()->subDay(),
    ]);
    EmailDelivery::factory()->count(2)->create([
        'email_id' => $campaign->id,
        'provider' => EmailProvider::Smtp,
        'sent_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.overview.deliveryRate', 100)
            ->where('dashboard.recentCampaigns.0.deliveryReported', true));
});

test('dashboard supports normalized reporting periods', function (string $query, int $period) {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard', ['period' => $query]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.period', $period)
        ->has('dashboard.performance', $period),
    );
})->with([
    'seven days' => ['7', 7],
    'ninety days' => ['90', 90],
    'unsupported period' => ['365', 30],
]);

test('dashboard includes pending invitations for the authenticated user', function () {
    $owner = User::factory()->create(['name' => 'Taylor Otwell']);
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create(['name' => 'Laravel Team']);

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 1)
        ->where('pendingInvitations.0.code', $invitation->code)
        ->where('pendingInvitations.0.inviterName', 'Taylor Otwell')
        ->where('pendingInvitations.0.team.name', 'Laravel Team')
        ->where('pendingInvitations.0.team.slug', $team->slug)
        ->missing('pendingInvitations.0.teamName'),
    );
});

test('dashboard does not include accepted invitations', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    TeamInvitation::factory()->accepted()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 0),
    );
});

test('dashboard excludes expired invitations without deleting them', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->expired()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 0),
    );

    $this->assertDatabaseHas('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('dashboard does not include or delete other users invitations', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->expired()->create([
        'team_id' => $team->id,
        'email' => 'someone@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 0),
    );

    $this->assertDatabaseHas('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('sending is reported as paused only when the delivery connection fails its test', function (?string $state, bool $paused) {
    $user = User::factory()->create();

    if ($state === 'tested') {
        TeamEmailIntegration::factory()->for($user->currentTeam)->smtp()->create();
    } elseif ($state === 'untested') {
        TeamEmailIntegration::factory()->for($user->currentTeam)->smtp()->untested()->create();
    }

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('deliveryPaused', $paused));
})->with([
    'no connection yet' => [null, false],
    'a tested connection' => ['tested', false],
    'a connection that needs a new test' => ['untested', true],
]);
