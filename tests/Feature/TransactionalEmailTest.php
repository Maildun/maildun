<?php

use App\Enums\EmailEditor;
use App\Enums\TeamRole;
use App\Enums\TransactionalEmailStatus;
use App\Jobs\SendTransactionalEmailTest;
use App\Models\EmailTemplate;
use App\Models\Team;
use App\Models\TransactionalEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('the list shows the team transactional emails', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    TransactionalEmail::factory()->for($team)->create(['name' => 'Welcome email', 'slug' => 'welcome-email']);
    TransactionalEmail::factory()->for(Team::factory()->create())->create(['name' => 'Other team']);

    $this->actingAs($user)
        ->get(route('transactional_emails.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactional/index')
            ->where('canManage', true)
            ->where('defaultEditor', EmailEditor::Html->value)
            ->has('emails.data', 1)
            ->where('emails.data.0.name', 'Welcome email')
            ->where('emails.data.0.slug', 'welcome-email')
            ->where('emails.data.0.status', 'draft'));
});

test('composing from a template uses the team editor and copies its body', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_editor' => EmailEditor::Builder]);
    $template = EmailTemplate::query()->where('uuid', EmailTemplate::BLANK_BUILDER_UUID)->firstOrFail();

    $response = $this->actingAs($user)
        ->post(route('transactional_emails.store', $team), [
            'name' => 'Welcome email',
            'template' => $template->uuid,
        ])
        ->assertRedirect();

    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Transactional email created.']);

    $email = $team->transactionalEmails()->firstOrFail();

    expect($email->name)->toBe('Welcome email')
        ->and($email->subject)->toBe('Welcome email')
        ->and($email->preheader)->toBeNull()
        ->and($email->slug)->toBe('welcome-email')
        ->and($email->editor)->toBe(EmailEditor::Builder)
        ->and($email->status)->toBe(TransactionalEmailStatus::Draft)
        ->and($email->design)->toBe($template->design);
});

test('composing from a template copies its subject and preheader', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_editor' => EmailEditor::Builder]);
    $template = EmailTemplate::query()->where('name', 'Newsletter')->whereNull('team_id')->firstOrFail();

    $this->actingAs($user)
        ->post(route('transactional_emails.store', $team), [
            'name' => 'April digest',
            'template' => $template->uuid,
        ])
        ->assertRedirect();

    $email = $team->transactionalEmails()->firstOrFail();

    expect($email->name)->toBe('April digest')
        ->and($email->subject)->toBe('This month at your company')
        ->and($email->preheader)->toBe('Here is what the team has been up to since the last issue.');
});

test('composing without a template falls back to the team default editor', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_editor' => EmailEditor::Builder]);

    $this->actingAs($user)
        ->post(route('transactional_emails.store', $team), ['name' => 'Password reset'])
        ->assertRedirect();

    $email = $team->transactionalEmails()->firstOrFail();

    expect($email->editor)->toBe(EmailEditor::Builder)
        ->and($email->design)->not->toBeNull()
        ->and($email->slug)->toBe('password-reset');
});

test('a template from another team cannot be used as a starting point', function () {
    $user = User::factory()->create();
    $foreign = EmailTemplate::factory()->for(Team::factory()->create())->create();

    $this->actingAs($user)
        ->post(route('transactional_emails.store', $user->currentTeam), [
            'name' => 'Sneaky draft',
            'template' => $foreign->uuid,
        ])
        ->assertInvalid('template');
});

test('a template using a different editor cannot start a transactional email', function () {
    $user = User::factory()->create();
    $template = EmailTemplate::query()->where('uuid', EmailTemplate::BLANK_BUILDER_UUID)->firstOrFail();

    $this->actingAs($user)
        ->post(route('transactional_emails.store', $user->currentTeam), [
            'name' => 'Welcome email',
            'template' => $template->uuid,
        ])
        ->assertInvalid('template');
});

test('slugs are unique within a team', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('transactional_emails.store', $team), ['name' => 'Welcome email'])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('transactional_emails.store', $team), ['name' => 'Welcome email'])
        ->assertRedirect();

    $slugs = $team->transactionalEmails()->orderBy('id')->pluck('slug');

    expect($slugs->all())->toBe(['welcome-email', 'welcome-email-2']);
});

test('the edit page exposes the email and sender defaults', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update([
        'email_editor' => EmailEditor::Builder,
        'email_from_name' => 'Maildun HQ',
        'email_from_address' => 'hq@example.com',
    ]);
    $email = TransactionalEmail::factory()->for($team)->create([
        'slug' => 'welcome-email',
        'variables' => [['key' => 'first_name', 'example' => 'Ada']],
    ]);

    $this->actingAs($user)
        ->get(route('transactional_emails.edit', [$team, $email]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactional/edit')
            ->where('email.uuid', $email->uuid)
            ->where('email.slug', 'welcome-email')
            ->where('email.editor', EmailEditor::Builder->value)
            ->where('email.slug_frozen', false)
            ->where('canManage', true)
            ->where('defaults.from_name', 'Maildun HQ')
            ->where('defaults.from_address', 'hq@example.com')
            ->where('email.variables.0.key', 'first_name'));
});

test('saving merges detected variables and keeps examples', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $email = TransactionalEmail::factory()->for($team)->create([
        'variables' => [['key' => 'first_name', 'example' => 'Ada']],
    ]);

    $this->actingAs($user)
        ->patch(route('transactional_emails.update', [$team, $email]), [
            'name' => 'Welcome email',
            'slug' => 'welcome-email',
            'subject' => 'Hi {{ first_name }}',
            'html' => '<p>Reset: {{ reset_url }}</p>',
            'variables' => [
                ['key' => 'first_name', 'example' => 'Ada'],
            ],
        ])
        ->assertRedirect();

    $email->refresh();

    expect($email->name)->toBe('Welcome email')
        ->and($email->slug)->toBe('welcome-email')
        ->and($email->subject)->toBe('Hi {{ first_name }}')
        ->and($email->variables)->toBe([
            ['key' => 'first_name', 'example' => 'Ada'],
            ['key' => 'reset_url', 'example' => ''],
        ]);
});

test('the block editor requires a document', function () {
    $user = User::factory()->create();
    $user->currentTeam->update(['email_editor' => EmailEditor::Builder]);
    $email = TransactionalEmail::factory()->for($user->currentTeam)->builder()->create();

    $this->actingAs($user)
        ->patch(route('transactional_emails.update', [$user->currentTeam, $email]), [
            'name' => $email->name,
            'slug' => $email->slug,
            'subject' => $email->subject,
            'html' => '<p>rendered</p>',
        ])
        ->assertInvalid('design');
});

test('saving uses the team editor and drops an incompatible block document', function () {
    $user = User::factory()->create();
    $email = TransactionalEmail::factory()->for($user->currentTeam)->builder()->create();

    $this->actingAs($user)
        ->patch(route('transactional_emails.update', [$user->currentTeam, $email]), [
            'name' => $email->name,
            'slug' => $email->slug,
            'subject' => $email->subject,
            'html' => '<p>Hand written</p>',
        ])
        ->assertRedirect();

    expect($email->fresh()->editor)->toBe(EmailEditor::Html)
        ->and($email->fresh()->design)->toBeNull();
});

test('a plain text transactional email keeps its source and rendered html', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_editor' => EmailEditor::PlainText]);
    $email = TransactionalEmail::factory()->for($team)->create();

    $this->actingAs($user)
        ->patch(route('transactional_emails.update', [$team, $email]), [
            'name' => $email->name,
            'slug' => $email->slug,
            'subject' => $email->subject,
            'source' => 'Your receipt is ready.',
            'html' => '<div>Your receipt is ready.</div>',
        ])
        ->assertRedirect();

    $email->refresh();

    expect($email->editor)->toBe(EmailEditor::PlainText)
        ->and($email->source)->toBe('Your receipt is ready.')
        ->and($email->html)->toBe('<div>Your receipt is ready.</div>')
        ->and($email->design)->toBeNull();
});

test('a transactional email can be published and unpublished', function () {
    $user = User::factory()->create();
    $email = TransactionalEmail::factory()->for($user->currentTeam)->create([
        'subject' => 'Welcome',
        'html' => '<p>Hello</p>',
    ]);

    $this->actingAs($user)
        ->post(route('transactional_emails.publish', [$user->currentTeam, $email]))
        ->assertRedirect();

    $email->refresh();

    expect($email->status)->toBe(TransactionalEmailStatus::Published)
        ->and($email->published_at)->not->toBeNull();

    $publishedAt = $email->published_at;

    $this->actingAs($user)
        ->post(route('transactional_emails.unpublish', [$user->currentTeam, $email]))
        ->assertRedirect();

    $email->refresh();

    expect($email->status)->toBe(TransactionalEmailStatus::Draft)
        ->and($email->published_at?->eq($publishedAt))->toBeTrue();
});

test('publishing requires a subject and content', function () {
    $user = User::factory()->create();
    $email = TransactionalEmail::factory()->for($user->currentTeam)->create([
        'subject' => '',
        'html' => '',
    ]);

    $this->actingAs($user)
        ->post(route('transactional_emails.publish', [$user->currentTeam, $email]))
        ->assertInvalid('email');
});

test('the identifier cannot change after the first publish', function () {
    $user = User::factory()->create();
    $email = TransactionalEmail::factory()->for($user->currentTeam)->published()->create([
        'slug' => 'welcome-email',
        'html' => '<p>Hello</p>',
    ]);

    $this->actingAs($user)
        ->patch(route('transactional_emails.update', [$user->currentTeam, $email]), [
            'name' => $email->name,
            'slug' => 'renamed-email',
            'subject' => $email->subject,
            'html' => '<p>Hello</p>',
        ])
        ->assertInvalid('slug');

    expect($email->fresh()->slug)->toBe('welcome-email');
});

test('a test send queues rendered merge tags', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_from_name' => 'Maildun HQ', 'email_from_address' => 'hq@example.com']);
    $email = TransactionalEmail::factory()->for($team)->create([
        'from_name' => null,
        'from_address' => null,
        'subject' => 'Hi {{ first_name }}',
        'html' => '<p>Hello {{ first_name }}</p>',
    ]);

    $this->actingAs($user)
        ->post(route('transactional_emails.test', [$team, $email]), [
            'to' => 'reviewer@example.com',
            'data' => ['first_name' => 'Ada'],
        ])
        ->assertRedirect();

    Queue::assertPushed(SendTransactionalEmailTest::class, fn (SendTransactionalEmailTest $job): bool => $job->emailId === $email->id
        && $job->recipient === 'reviewer@example.com'
        && $job->subject === 'Hi Ada'
        && $job->html === '<p>Hello Ada</p>');

    expect($email->fresh()->last_tested_at)->toBeNull();
});

test('a test send requires a valid address', function () {
    Mail::fake();

    $user = User::factory()->create();
    $email = TransactionalEmail::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->post(route('transactional_emails.test', [$user->currentTeam, $email]), ['to' => 'not-an-address'])
        ->assertInvalid('to');

    Mail::assertNothingSent();
});

test('a transactional test send queues before the delivery worker resolves its provider', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $email = TransactionalEmail::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('transactional_emails.test', [$team, $email]), [
            'to' => 'reviewer@example.com',
        ])
        ->assertRedirect();

    Queue::assertPushed(SendTransactionalEmailTest::class);
    expect($email->fresh()->last_tested_at)->toBeNull();
});

test('a transactional email can be duplicated', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $email = TransactionalEmail::factory()->for($team)->published()->create([
        'name' => 'Welcome email',
        'slug' => 'welcome-email',
        'html' => '<p>Hello {{ first_name }}</p>',
        'variables' => [['key' => 'first_name', 'example' => 'Ada']],
    ]);

    $response = $this->actingAs($user)
        ->post(route('transactional_emails.duplicate', [$team, $email]))
        ->assertRedirect();

    $copy = $team->transactionalEmails()->where('id', '!=', $email->id)->firstOrFail();

    $response->assertRedirect(route('transactional_emails.edit', [$team, $copy]));

    expect($copy->name)->toBe('Welcome email copy')
        ->and($copy->slug)->toBe('welcome-email-copy')
        ->and($copy->status)->toBe(TransactionalEmailStatus::Draft)
        ->and($copy->published_at)->toBeNull()
        ->and($copy->html)->toBe($email->html)
        ->and($copy->variables)->toBe($email->variables);
});

test('a transactional email can be deleted', function () {
    $user = User::factory()->create();
    $email = TransactionalEmail::factory()->for($user->currentTeam)->create();

    $response = $this->actingAs($user)
        ->delete(route('transactional_emails.destroy', [$user->currentTeam, $email]))
        ->assertRedirect(route('transactional_emails.index', $user->currentTeam));

    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Transactional email deleted.']);

    $this->assertSoftDeleted($email);
});

test('members can read transactional emails but cannot change them', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $email = TransactionalEmail::factory()->for($team)->create();

    $this->actingAs($member)
        ->get(route('transactional_emails.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

    $this->actingAs($member)
        ->post(route('transactional_emails.store', $team), ['name' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($member)
        ->patch(route('transactional_emails.update', [$team, $email]), [
            'name' => 'Nope',
            'slug' => 'nope',
            'subject' => 'Nope',
            'html' => '<p>Nope</p>',
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('transactional_emails.test', [$team, $email]), ['to' => 'member@example.com'])
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('transactional_emails.publish', [$team, $email]))
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('transactional_emails.destroy', [$team, $email]))
        ->assertForbidden();

    Mail::assertNothingSent();
});

test('transactional emails cannot be reached through another team', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $email = TransactionalEmail::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->get(route('transactional_emails.edit', [$otherTeam, $email]))
        ->assertNotFound();
});

test('the list can be searched and filtered by status and editor', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    TransactionalEmail::factory()->for($team)->create([
        'name' => 'Welcome email',
        'slug' => 'welcome-email',
        'status' => TransactionalEmailStatus::Published,
        'published_at' => now(),
    ]);
    TransactionalEmail::factory()->for($team)->create([
        'name' => 'Receipt draft',
        'slug' => 'receipt-draft',
        'editor' => EmailEditor::Builder,
    ]);

    $this->actingAs($user)
        ->get(route('transactional_emails.index', ['current_team' => $team, 'q' => 'welcome']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('emails.data', 1)
            ->where('emails.data.0.name', 'Welcome email')
            ->where('filters.q', 'welcome'));

    $this->actingAs($user)
        ->get(route('transactional_emails.index', ['current_team' => $team, 'status' => TransactionalEmailStatus::Published->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('emails.data', 1)
            ->where('emails.data.0.name', 'Welcome email')
            ->where('filters.status', TransactionalEmailStatus::Published->value));

    $this->actingAs($user)
        ->get(route('transactional_emails.index', ['current_team' => $team, 'editor' => EmailEditor::Builder->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('emails.data', 1)
            ->where('emails.data.0.name', 'Receipt draft')
            ->where('filters.editor', EmailEditor::Builder->value));
});

test('unknown transactional filter values fall back to no filter', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    TransactionalEmail::factory()->for($team)->create(['name' => 'Welcome email', 'slug' => 'welcome-email']);

    $this->actingAs($user)
        ->get(route('transactional_emails.index', ['current_team' => $team, 'status' => 'archived', 'editor' => 'mjml']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('emails.data', 1)
            ->where('filters.status', '')
            ->where('filters.editor', ''));
});
