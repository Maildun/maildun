<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSender;
use App\Models\User;

test('verifying an already verified sender is refused', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->withoutSender()->for($team)->create();
    $sender = TeamSender::factory()->for($team)->create(['email' => 'news@example.com']);

    expect($sender->refresh()->isVerifiedFor($integration))->toBeTrue();

    $this->actingAs($user)
        ->post(route('teams.sender.verification.store', [$team, $sender]))
        ->assertSessionHasErrors(['sender' => 'This sender is already verified.']);
});

test('a verified sender can be made the default', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->withoutSender()->for($team)->create();
    TeamSender::factory()->for($team)->create(['email' => 'hello@example.com']);
    $sender = TeamSender::factory()->for($team)->create([
        'name' => 'Acme News',
        'email' => 'news@example.com',
        'reply_to' => 'replies@example.com',
    ]);

    $this->actingAs($user)
        ->patch(route('teams.sender.default.update', [$team, $sender]))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Default sender updated.']);

    expect($team->refresh())
        ->active_sender_id->toBe($sender->id)
        ->email_from_name->toBe('Acme News')
        ->email_from_address->toBe('news@example.com')
        ->email_reply_to->toBe('replies@example.com');
});

test('a pending sender cannot be made the default', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->withoutSender()->for($team)->create();
    $sender = TeamSender::factory()->pending()->for($team)->create(['email' => 'news@example.com']);

    $this->actingAs($user)
        ->patch(route('teams.sender.default.update', [$team, $sender]))
        ->assertSessionHasErrors(['sender' => 'Verify this sender before making it the default.']);

    expect($team->refresh()->active_sender_id)->not->toBe($sender->id);
});

test('removing the default sender clears the workspace sender identity', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->withoutSender()->for($team)->create();
    $sender = TeamSender::factory()->for($team)->create(['email' => 'news@example.com']);
    $this->actingAs($user)->patch(route('teams.sender.default.update', [$team, $sender]));

    $this->delete(route('teams.sender.destroy', [$team, $sender]))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Sender removed.']);

    expect(TeamSender::query()->find($sender->id))->toBeNull()
        ->and($team->refresh())
        ->active_sender_id->toBeNull()
        ->email_from_address->toBeNull()
        ->email_from_name->toBeNull();
});

test('sender actions do not reveal another workspace sender', function () {
    $owner = User::factory()->create();
    $otherSender = TeamSender::factory()->for(User::factory()->create()->currentTeam)->create();
    $team = $owner->currentTeam;

    $this->actingAs($owner);

    $this->post(route('teams.sender.verification.store', [$team, $otherSender]))->assertNotFound();
    $this->patch(route('teams.sender.default.update', [$team, $otherSender]))->assertNotFound();
    $this->delete(route('teams.sender.destroy', [$team, $otherSender]))->assertNotFound();

    expect($otherSender->fresh())->not->toBeNull();
});

test('members cannot remove a workspace sender', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $sender = TeamSender::factory()->for($team)->create();

    $this->actingAs($member)
        ->delete(route('teams.sender.destroy', [$team, $sender]))
        ->assertForbidden();

    expect($sender->fresh())->not->toBeNull();
});
