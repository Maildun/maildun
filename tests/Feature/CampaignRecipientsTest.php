<?php

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailStatus;
use App\Enums\TeamRole;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function sentCampaignWithRecipients(Team $team): Email
{
    $email = Email::factory()->for($team)->create([
        'name' => 'September news',
        'status' => EmailStatus::PartiallyFailed,
        'recipient_count' => 3,
    ]);
    EmailDelivery::factory()->for($email)->create([
        'email_address' => 'ada@example.com',
        'first_name' => 'Ada',
        'status' => EmailDeliveryStatus::Sent,
        'opens_count' => 2,
    ]);
    EmailDelivery::factory()->for($email)->create([
        'email_address' => 'grace@example.com',
        'first_name' => 'Grace',
        'status' => EmailDeliveryStatus::Failed,
    ]);
    EmailDelivery::factory()->for($email)->create([
        'email_address' => 'alan@example.com',
        'first_name' => 'Alan',
        'status' => EmailDeliveryStatus::Sent,
    ]);

    return $email;
}

test('the recipients tab searches by address or name and counts each tab', function () {
    $user = User::factory()->create();
    $email = sentCampaignWithRecipients($user->currentTeam);

    $this->actingAs($user)
        ->get(route('emails.recipients', [$user->currentTeam, $email, 'q' => 'a']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', 'a')
            ->where('filterCounts.all', 3)
            ->where('filterCounts.opened', 1)
            ->where('filterCounts.failed', 1));

    $this->actingAs($user)
        ->get(route('emails.recipients', [$user->currentTeam, $email, 'q' => 'GRACE']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recipients.data', 1)
            ->where('recipients.data.0.email', 'grace@example.com')
            ->where('filterCounts.all', 1));
});

test('the recipient export follows the selected tab and search', function () {
    $user = User::factory()->create();
    $email = sentCampaignWithRecipients($user->currentTeam);

    $response = $this->actingAs($user)
        ->get(route('emails.recipients.exports.show', [$user->currentTeam, $email, 'csv', 'status' => 'sent', 'q' => 'ada']));

    $csv = $response->assertOk()->streamedContent();

    expect($csv)->toContain('ada@example.com')
        ->not->toContain('alan@example.com')
        ->not->toContain('grace@example.com')
        ->and($response->headers->get('content-disposition'))->toContain('september-news-recipients-');
});

test('members who cannot manage campaigns cannot export recipients', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $email = sentCampaignWithRecipients($team);

    $this->actingAs($member)
        ->get(route('emails.recipients.exports.show', [$team, $email, 'csv']))
        ->assertForbidden();
});

test('a draft campaign has no recipients to export', function () {
    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create(['status' => EmailStatus::Draft]);

    $this->actingAs($user)
        ->get(route('emails.recipients.exports.show', [$user->currentTeam, $email, 'csv']))
        ->assertNotFound();
});
