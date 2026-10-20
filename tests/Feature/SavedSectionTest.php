<?php

use App\Enums\EmailEditor;
use App\Enums\TeamRole;
use App\Mcp\Servers\MaildunServer;
use App\Mcp\Tools\SavedSections\ListSavedSectionsTool;
use App\Models\Email;
use App\Models\SavedSection;
use App\Models\Team;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Inertia\Testing\AssertableInertia as Assert;

/** @return list<array<string, mixed>> */
function footerBlocks(): array
{
    return [[
        'id' => 'footer',
        'type' => 'container',
        'children' => [['id' => 'footer-text', 'type' => 'text', 'props' => ['markdown' => 'Acme Inc.']]],
    ]];
}

test('saves a section without block ids so every insert gets fresh ones', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->from(route('dashboard', $team))
        ->post(route('saved_sections.store', $team), ['name' => 'Footer', 'blocks' => footerBlocks()])
        ->assertRedirect(route('dashboard', $team));

    $section = $team->savedSections()->sole();

    expect($section->name)->toBe('Footer')
        ->and($section->created_by)->toBe($user->id)
        ->and($section->blocks)->toBe([[
            'type' => 'container',
            'children' => [['type' => 'text', 'props' => ['markdown' => 'Acme Inc.']]],
        ]]);
});

test('requires a name and at least one typed block', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('saved_sections.store', $user->currentTeam), ['name' => '', 'blocks' => []])
        ->assertSessionHasErrors(['name', 'blocks']);

    $this->actingAs($user)
        ->post(route('saved_sections.store', $user->currentTeam), ['name' => 'Footer', 'blocks' => [['props' => []]]])
        ->assertSessionHasErrors(['blocks.0.type']);

    expect(SavedSection::count())->toBe(0);
});

test('renames a section without touching its blocks', function () {
    $user = User::factory()->create();
    $section = SavedSection::factory()->for($user->currentTeam)->create(['name' => 'Footer']);
    $blocks = $section->blocks;

    $this->actingAs($user)
        ->patch(route('saved_sections.update', [$user->currentTeam, $section]), ['name' => 'Legal footer'])
        ->assertRedirect();

    expect($section->refresh()->name)->toBe('Legal footer')
        ->and($section->blocks)->toBe($blocks);

    $this->actingAs($user)
        ->patch(route('saved_sections.update', [$user->currentTeam, $section]), ['name' => 'Footer', 'blocks' => footerBlocks()])
        ->assertSessionHasErrors(['blocks']);
});

test('deletes a section', function () {
    $user = User::factory()->create();
    $section = SavedSection::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->delete(route('saved_sections.destroy', [$user->currentTeam, $section]))
        ->assertRedirect();

    $this->assertModelMissing($section);
});

test('viewers cannot save rename or delete sections', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($viewer, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $viewer);
    $section = SavedSection::factory()->for($team)->create(['name' => 'Footer']);

    $this->actingAs($viewer)
        ->post(route('saved_sections.store', $team), ['name' => 'Header', 'blocks' => footerBlocks()])
        ->assertForbidden();
    $this->actingAs($viewer)
        ->patch(route('saved_sections.update', [$team, $section]), ['name' => 'Renamed'])
        ->assertForbidden();
    $this->actingAs($viewer)
        ->delete(route('saved_sections.destroy', [$team, $section]))
        ->assertForbidden();

    expect($section->refresh()->name)->toBe('Footer')
        ->and($team->savedSections()->count())->toBe(1);
});

test('sections of another workspace cannot be reached', function () {
    $user = User::factory()->create();
    $section = SavedSection::factory()->create();

    $this->actingAs($user)
        ->delete(route('saved_sections.destroy', [$user->currentTeam, $section]))
        ->assertNotFound();

    $this->assertModelExists($section);
});

test('block editors list the workspace sections newest first', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['email_editor' => EmailEditor::Builder]);
    $email = Email::factory()->for($team)->builder()->create();
    SavedSection::factory()->for($team)->create(['name' => 'Footer', 'created_at' => now()->subDay()]);
    $header = SavedSection::factory()->for($team)->create(['name' => 'Header']);
    SavedSection::factory()->create(['name' => 'Other workspace']);

    $this->actingAs($user)
        ->get(route('emails.edit', [$team, $email]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('savedSections', 2)
            ->where('savedSections.0.id', $header->uuid)
            ->where('savedSections.0.name', 'Header')
            ->where('savedSections.1.name', 'Footer'));
});

test('MCP clients list the workspace sections with their blocks', function () {
    $team = Team::factory()->create();
    SavedSection::factory()->for($team)->create(['name' => 'Footer']);
    SavedSection::factory()->for($team)->create(['name' => 'Header']);
    SavedSection::factory()->create(['name' => 'Other footer']);

    MaildunServer::tool(ListSavedSectionsTool::class, ['workspace' => $team->slug, 'search' => 'foot'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('count', 1)
            ->where('sections.0.name', 'Footer')
            ->has('sections.0.blocks', 1)
            ->etc());
});
