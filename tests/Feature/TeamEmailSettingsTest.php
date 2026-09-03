<?php

use App\Enums\EmailEditor;
use App\Enums\TeamRole;
use App\Jobs\SendTeamSenderVerification;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSender;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('the email builder page shows the current editor and options', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update([
        'email_editor' => EmailEditor::Builder,
    ]);

    $this->actingAs($user)
        ->get(route('teams.email.edit', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/email')
            ->where('team.slug', $team->slug)
            ->where('settings.email_editor', EmailEditor::Builder->value)
            ->where('permissions.canManageEmails', true)
            ->has('editors', 4)
            ->where('editors.0.value', EmailEditor::Html->value)
            ->where('editors.1.value', EmailEditor::Builder->value)
            ->where('editors.2.value', EmailEditor::PlainText->value)
            ->where('editors.2.label', 'Plain text')
            ->where('editors.3.value', EmailEditor::Markdown->value)
            ->where('editors.3.label', 'Markdown')
            ->missing('fallbacks')
            ->missing('settings.email_from_name'));
});

test('the sender page shows the current identity domain and provider readiness', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $sender = TeamSender::factory()->for($team)->create([
        'name' => 'Maildun HQ',
        'email' => 'news@updates.example.com',
        'reply_to' => 'replies@example.com',
    ]);
    $team->forceFill(['active_sender_id' => $sender->id])->save();
    $integration = TeamEmailIntegration::factory()->for($team)->ses()->create();

    $this->actingAs($user)
        ->get(route('teams.sender.edit', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/sender')
            ->where('team.slug', $team->slug)
            ->has('senders', 1)
            ->where('senders.0.name', 'Maildun HQ')
            ->where('senders.0.email', 'news@updates.example.com')
            ->where('senders.0.reply_to', 'replies@example.com')
            ->where('senders.0.is_verified', true)
            ->where('senders.0.was_verified', true)
            ->where('senders.0.is_default', true)
            ->where('delivery.configured', true)
            ->where('delivery.verified', true)
            ->where('canManage', true)
            ->missing('integrations')
            ->missing('fallbacks'));
});

test('teams default to the html editor', function () {
    $user = User::factory()->create();

    expect($user->currentTeam->email_editor)->toBe(EmailEditor::Html);
});

test('the default editor and sender identity can be saved separately', function () {
    Queue::fake();
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $integration = TeamEmailIntegration::factory()->withoutSender()->for($team)->smtp()->create();

    $this->actingAs($user)
        ->patch(route('teams.email.update', $team), [
            'email_editor' => EmailEditor::Builder->value,
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('teams.sender.store', $team), [
            'name' => 'Maildun HQ',
            'email' => 'hq@example.com',
            'reply_to' => 'replies@example.com',
        ])
        ->assertRedirect();

    $sender = $team->senders()->sole();

    expect($team->refresh()->email_editor)->toBe(EmailEditor::Builder)
        ->and($sender->name)->toBe('Maildun HQ')
        ->and($sender->email)->toBe('hq@example.com')
        ->and($sender->reply_to)->toBe('replies@example.com')
        ->and($sender->email_verified_at)->toBeNull();

    Queue::assertPushed(SendTeamSenderVerification::class, fn (SendTeamSenderVerification $job): bool => $job->senderId === $sender->id
        && $job->integrationId === $integration->id);
});

test('a different From address is stored as a new pending sender', function () {
    Queue::fake();
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $sender = TeamSender::factory()->for($team)->create(['email' => 'old@example.com']);
    $team->forceFill(['active_sender_id' => $sender->id])->save();
    $activeIntegration = TeamEmailIntegration::factory()->for($team)->smtp()->create();

    $this->actingAs($user)
        ->post(route('teams.sender.store', $team), [
            'name' => 'Renamed sender',
            'email' => 'new@example.com',
            'reply_to' => null,
        ])
        ->assertSessionHasNoErrors();

    expect($team->refresh()->active_sender_id)->toBe($sender->id)
        ->and($team->senders()->count())->toBe(2)
        ->and($team->senders()->where('email', 'new@example.com')->sole()->isVerified())->toBeFalse()
        ->and($activeIntegration->refresh()->id)->toBe($activeIntegration->id);
});

test('updating a sender name preserves its verified address and default selection', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $sender = TeamSender::factory()->for($team)->create(['email' => 'mail@example.com']);
    $team->forceFill(['active_sender_id' => $sender->id])->save();
    $integration = TeamEmailIntegration::factory()->for($team)->smtp()->create();

    $this->actingAs($user)
        ->patch(route('teams.sender.update', [$team, $sender]), [
            'name' => 'A new display name',
            'reply_to' => null,
        ])
        ->assertSessionHasNoErrors();

    expect($sender->refresh()->name)->toBe('A new display name')
        ->and($sender->email)->toBe('mail@example.com')
        ->and($team->refresh()->email_from_name)->toBe('A new display name');
});

test('the editor must be one of the supported options', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('teams.email.update', $user->currentTeam), [
            'email_editor' => 'mjml',
        ])
        ->assertInvalid('email_editor');
});

test('source-based email editors can be selected', function (EmailEditor $editor) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('teams.email.update', $user->currentTeam), [
            'email_editor' => $editor->value,
        ])
        ->assertRedirect();

    expect($user->currentTeam->refresh()->email_editor)->toBe($editor);
})->with([
    'plain text' => EmailEditor::PlainText,
    'markdown' => EmailEditor::Markdown,
]);

test('sender addresses must be valid email addresses', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('teams.sender.store', $user->currentTeam), [
            'email' => 'not-an-address',
            'reply_to' => 'also-not-one',
        ])
        ->assertInvalid(['email', 'reply_to']);
});

test('members cannot access email editor or sender settings', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member)
        ->get(route('teams.email.edit', $team))
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('teams.sender.edit', $team))
        ->assertForbidden();

    $this->actingAs($member)
        ->patch(route('teams.email.update', $team), [
            'email_editor' => EmailEditor::Builder->value,
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('teams.sender.store', $team), [
            'email' => 'member@example.com',
        ])
        ->assertForbidden();

    expect($team->fresh()->email_editor)->toBe(EmailEditor::Html);
});

test('admins can change the email builder and sender settings', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    TeamEmailIntegration::factory()->withoutSender()->for($team)->smtp()->create();

    $this->actingAs($admin)
        ->patch(route('teams.email.update', $team), [
            'email_editor' => EmailEditor::Builder->value,
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('teams.sender.store', $team), [
            'name' => 'Admin sender',
            'email' => 'admin@example.com',
        ])
        ->assertRedirect();

    expect($team->fresh()->email_editor)->toBe(EmailEditor::Builder);
    expect($team->senders()->where('email', 'admin@example.com')->exists())->toBeTrue();
});

test('non members cannot open the email settings pages', function () {
    $team = Team::factory()->create();
    $team->members()->attach(User::factory()->create(), ['role' => TeamRole::Owner->value]);

    $this->actingAs(User::factory()->create())
        ->get(route('teams.email.edit', $team))
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->get(route('teams.sender.edit', $team))
        ->assertForbidden();
});

test('guests are redirected from the email settings pages', function () {
    $team = Team::factory()->create();

    $this->get(route('teams.email.edit', $team))->assertRedirect(route('login'));
    $this->get(route('teams.sender.edit', $team))->assertRedirect(route('login'));
});
