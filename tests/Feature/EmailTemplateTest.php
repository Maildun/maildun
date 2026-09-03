<?php

use App\Enums\EmailEditor;
use App\Enums\TeamRole;
use App\Models\EmailTemplate;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the gallery lists the starter templates alongside the team ones', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    EmailTemplate::factory()->for($team)->create(['name' => 'House style']);
    EmailTemplate::factory()->for(Team::factory()->create())->create(['name' => 'Other team style']);

    $this->actingAs($user)
        ->get(route('email_templates.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('email-templates/index')
            ->where('canManage', true)
            ->missing('editors')
            ->has('templates', 5)
            ->where('templates.4.name', 'House style')
            ->where('templates.4.is_starter', false));
});

test('starter templates are seeded and belong to no team', function () {
    $starters = EmailTemplate::query()->whereNull('team_id')->get();

    expect($starters)->toHaveCount(4)
        ->and($starters->every(fn (EmailTemplate $template) => $template->isStarter()))->toBeTrue();

    $blank = EmailTemplate::query()->where('uuid', EmailTemplate::BLANK_BUILDER_UUID)->firstOrFail();

    expect($blank->editor)->toBe(EmailEditor::Builder)
        ->and($blank->design)->toHaveKey('root')
        ->and($blank->subject)->toBeNull();

    $newsletter = EmailTemplate::query()->where('name', 'Newsletter')->whereNull('team_id')->firstOrFail();

    expect($newsletter->subject)->toBe('This month at your company')
        ->and($newsletter->preheader)->toBe('Here is what the team has been up to since the last issue.');
});

test('a team can create a template', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('email_templates.store', $team), [
            'name' => 'Weekly digest',
            'description' => 'Our usual layout',
            'html' => '<p>Digest</p>',
        ])
        ->assertRedirect();

    $template = $team->emailTemplates()->firstOrFail();

    expect($template->name)->toBe('Weekly digest')
        ->and($template->editor)->toBe(EmailEditor::Html)
        ->and($template->subject)->toBe('Weekly digest')
        ->and($template->design)->toBeNull()
        ->and($template->isStarter())->toBeFalse();
});

test('composing a new template uses the team editor and opens the edit page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_editor' => EmailEditor::Builder]);

    $response = $this->actingAs($user)
        ->post(route('email_templates.store', $team), [
            'name' => 'House style',
            'description' => 'Reusable intro',
            'compose' => '1',
        ])
        ->assertRedirect();

    $template = $team->emailTemplates()->firstOrFail();

    $response->assertRedirect(route('email_templates.edit', [$team, $template]));

    expect($template->editor)->toBe(EmailEditor::Builder)
        ->and($template->subject)->toBe('House style')
        ->and($template->design)->toHaveKey('root')
        ->and($template->isStarter())->toBeFalse();
});

test('a block template must carry its document', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_editor' => EmailEditor::Builder]);

    $this->actingAs($user)
        ->post(route('email_templates.store', $team), [
            'name' => 'Blocks',
            'html' => '<p>rendered</p>',
        ])
        ->assertInvalid('design');
});

test('template names must be unique within a team', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    EmailTemplate::factory()->for($team)->create(['name' => 'Weekly digest']);

    $this->actingAs($user)
        ->post(route('email_templates.store', $team), [
            'name' => 'Weekly digest',
            'html' => '<p>Digest</p>',
        ])
        ->assertInvalid('name');
});

test('the compose page exposes the template details and body', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $template = EmailTemplate::factory()->for($team)->create([
        'name' => 'House style',
        'subject' => 'What we shipped',
        'preheader' => 'A short look back',
        'html' => '<p>Body</p>',
    ]);

    $this->actingAs($user)
        ->get(route('email_templates.edit', [$team, $template]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('email-templates/edit')
            ->where('canManage', true)
            ->where('template.uuid', $template->uuid)
            ->where('template.subject', 'What we shipped')
            ->where('template.preheader', 'A short look back')
            ->where('template.html', '<p>Body</p>'));
});

test('a team template can be renamed and deleted', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $template = EmailTemplate::factory()->for($team)->create(['name' => 'Old name']);

    $this->actingAs($user)
        ->patch(route('email_templates.update', [$team, $template]), [
            'name' => 'New name',
            'subject' => 'Updated subject',
            'preheader' => 'Updated preview',
            'html' => '<p>Updated</p>',
        ])
        ->assertRedirect();

    expect($template->fresh()->name)->toBe('New name')
        ->and($template->fresh()->subject)->toBe('Updated subject')
        ->and($template->fresh()->preheader)->toBe('Updated preview');

    $this->actingAs($user)
        ->delete(route('email_templates.destroy', [$team, $template]))
        ->assertRedirect(route('email_templates.index', $team));

    $this->assertModelMissing($template);
});

test('a markdown template keeps its editable source', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_editor' => EmailEditor::Markdown]);
    $template = EmailTemplate::factory()->for($team)->create([
        'editor' => EmailEditor::Markdown,
        'source' => '# Old heading',
    ]);

    $this->actingAs($user)
        ->patch(route('email_templates.update', [$team, $template]), [
            'name' => $template->name,
            'html' => '<h1>New heading</h1>',
            'source' => '# New heading',
        ])
        ->assertRedirect();

    expect($template->fresh()->source)->toBe('# New heading')
        ->and($template->fresh()->html)->toBe('<h1>New heading</h1>')
        ->and($template->fresh()->design)->toBeNull();
});

test('starter templates cannot be edited or deleted', function () {
    $user = User::factory()->create();
    $starter = EmailTemplate::query()->whereNull('team_id')->firstOrFail();

    // Scoped bindings only reach the team's own templates.
    $this->actingAs($user)
        ->patch(route('email_templates.update', [$user->currentTeam, $starter]), [
            'name' => 'Hijacked',
            'html' => '<p>Nope</p>',
        ])
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('email_templates.edit', [$user->currentTeam, $starter]))
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('email_templates.destroy', [$user->currentTeam, $starter]))
        ->assertNotFound();

    expect($starter->fresh())->not->toBeNull();
});

test('a starter can be duplicated into the team library under a free name', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $starter = EmailTemplate::query()->where('uuid', EmailTemplate::BLANK_BUILDER_UUID)->firstOrFail();

    $first = $this->actingAs($user)
        ->post(route('email_templates.duplicate', [$team, $starter]));

    $copy = $team->emailTemplates()->where('name', 'Blank canvas copy')->firstOrFail();

    $first->assertRedirect(route('email_templates.edit', [$team, $copy]));

    $this->actingAs($user)
        ->post(route('email_templates.duplicate', [$team, $starter]))
        ->assertRedirect();

    expect($team->emailTemplates()->pluck('name')->sort()->values()->all())
        ->toBe(['Blank canvas copy', 'Blank canvas copy 2']);

    expect($copy->editor)->toBe($starter->editor)
        ->and($copy->design)->toBe($starter->design)
        ->and($copy->subject)->toBe($starter->subject)
        ->and($copy->preheader)->toBe($starter->preheader)
        ->and($copy->isStarter())->toBeFalse();
});

test('a template from another team cannot be duplicated', function () {
    $user = User::factory()->create();
    $foreign = EmailTemplate::factory()->for(Team::factory()->create())->create();

    $this->actingAs($user)
        ->post(route('email_templates.duplicate', [$user->currentTeam, $foreign]))
        ->assertNotFound();

    expect($user->currentTeam->emailTemplates()->count())->toBe(0);
});

test('members can browse templates but cannot manage them', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $template = EmailTemplate::factory()->for($team)->create();

    $this->actingAs($member)
        ->get(route('email_templates.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

    $this->actingAs($member)
        ->post(route('email_templates.store', $team), [
            'name' => 'Nope',
            'html' => '<p>Nope</p>',
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('email_templates.edit', [$team, $template]))
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('email_templates.destroy', [$team, $template]))
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('email_templates.duplicate', [$team, $template]))
        ->assertForbidden();
});

test('non members cannot open the template gallery', function () {
    $team = Team::factory()->create();
    $team->members()->attach(User::factory()->create(), ['role' => TeamRole::Owner->value]);

    $this->actingAs(User::factory()->create())
        ->get(route('email_templates.index', $team))
        ->assertForbidden();
});

test('saving a campaign as a template copies subject and preheader', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->from(route('emails.index', $team))
        ->post(route('email_templates.store', $team), [
            'name' => 'March layout',
            'description' => 'From the campaign',
            'subject' => 'What we shipped in March',
            'preheader' => 'A short look back',
            'html' => '<p>Campaign body</p>',
        ])
        ->assertRedirect(route('emails.index', $team));

    $template = $team->emailTemplates()->firstOrFail();

    expect($template->name)->toBe('March layout')
        ->and($template->subject)->toBe('What we shipped in March')
        ->and($template->preheader)->toBe('A short look back')
        ->and($template->html)->toBe('<p>Campaign body</p>')
        ->and($template->editor)->toBe(EmailEditor::Html);
});

test('the gallery can be searched and filtered by editor and type', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    EmailTemplate::factory()->for($team)->create(['name' => 'House style', 'editor' => EmailEditor::Html]);
    EmailTemplate::factory()->for($team)->create(['name' => 'Block layout', 'editor' => EmailEditor::Builder]);

    $this->actingAs($user)
        ->get(route('email_templates.index', ['current_team' => $team, 'q' => 'house']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('templates', 1)
            ->where('templates.0.name', 'House style')
            ->where('filters.q', 'house'));

    $this->actingAs($user)
        ->get(route('email_templates.index', ['current_team' => $team, 'type' => 'team', 'editor' => EmailEditor::Builder->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('templates', 1)
            ->where('templates.0.name', 'Block layout')
            ->where('filters.type', 'team')
            ->where('filters.editor', EmailEditor::Builder->value));

    $this->actingAs($user)
        ->get(route('email_templates.index', ['current_team' => $team, 'type' => 'starter']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('templates', 4)
            ->where('templates.0.is_starter', true));
});

test('unknown gallery filter values fall back to no filter', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('email_templates.index', ['current_team' => $user->currentTeam, 'editor' => 'mjml', 'type' => 'shared']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.editor', '')
            ->where('filters.type', '')
            ->has('templates', 4));
});
