<?php

use App\Enums\TeamRole;
use App\Models\Audience;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\User;
use App\Services\DiceBearAvatarGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

test('the teams index page redirects to the current team editor', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('teams.index'))
        ->assertRedirect(route('teams.edit', $user->currentTeam));

    $this
        ->actingAs($user)
        ->followingRedirects()
        ->get(route('teams.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/edit')
            ->where('team.slug', $user->currentTeam->slug),
        );
});

test('the workspace creation page can be rendered', function () {
    $user = User::factory()->create();

    expect(parse_url(route('teams.create'), PHP_URL_PATH))
        ->toBe('/settings/workspace/create');

    $this
        ->actingAs($user)
        ->get(route('teams.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/create'),
        );
});

test('workspace settings links no longer expose the teams path', function () {
    $routeNames = [
        'teams.index',
        'teams.create',
        'teams.store',
        'teams.edit',
        'teams.update',
        'teams.destroy',
        'teams.switch',
        'teams.leave',
        'teams.members.index',
        'teams.members.update',
        'teams.members.destroy',
        'teams.members.generate-password',
        'teams.members.send-password-reset-link',
        'teams.invitations.store',
        'teams.invitations.destroy',
        'teams.email.edit',
        'teams.email.update',
        'teams.sender.edit',
        'teams.sender.update',
        'teams.theme.edit',
        'teams.theme.update',
        'teams.api.index',
        'teams.api.store',
        'teams.api.destroy',
        'tags.index',
        'tags.store',
        'tags.bulk-destroy',
        'tags.update',
        'tags.destroy',
    ];

    foreach ($routeNames as $routeName) {
        $uri = app('router')->getRoutes()->getByName($routeName)?->uri();

        expect($uri)
            ->not->toBeNull()
            ->toStartWith('settings/workspace')
            ->not->toContain('settings/teams');
    }
});

test('legacy team settings links redirect to workspace settings', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get('/settings/teams/create')
        ->assertStatus(301)
        ->assertRedirect('/settings/workspace/create');
});

test('workspaces can be created', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('teams.store'), [
            'name' => 'Test Team',
        ]);

    $workspace = Team::where('name', 'Test Team')->firstOrFail();

    $response
        ->assertRedirect(route('teams.edit', $workspace))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Workspace created.']);

    $this->assertDatabaseHas('teams', [
        'name' => 'Test Team',
        'is_personal' => false,
    ]);
});

test('a workspace can be created with a logo', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->post(route('teams.store'), [
            'name' => 'Acme Workspace',
            'logo' => UploadedFile::fake()->image('acme.png'),
        ])
        ->assertSessionHasNoErrors();

    $workspace = Team::where('name', 'Acme Workspace')->firstOrFail();

    expect($workspace->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($workspace->logo_path);
});

test('team slug uses next available suffix', function () {
    $user = User::factory()->create();

    Team::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
    Team::factory()->create(['name' => 'Acme One', 'slug' => 'acme-1']);
    Team::factory()->create(['name' => 'Acme Ten', 'slug' => 'acme-10']);

    $this
        ->actingAs($user)
        ->post(route('teams.store'), [
            'name' => 'Acme',
        ]);

    $this->assertDatabaseHas('teams', [
        'name' => 'Acme',
        'slug' => 'acme-11',
    ]);
});

test('the team edit page can be rendered', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($user)
        ->get(route('teams.edit', $team));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/edit')
            ->missing('members')
            ->missing('invitations')
            ->where('permissions.canLeaveTeam', false),
        );
});

test('teams can be updated by owners', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['name' => 'Original Name']);

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($user)
        ->patch(route('teams.update', $team), [
            'name' => 'Updated Name',
        ]);

    $response->assertRedirect(route('teams.edit', $team->fresh()));

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => 'Updated Name',
    ]);
});

test('team logo can be uploaded', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $logo = UploadedFile::fake()->image('logo.png');

    $response = $this
        ->actingAs($user)
        ->patch(route('teams.update', $team), [
            'name' => $team->name,
            'logo' => $logo,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('teams.edit', $team->fresh()));

    $team->refresh();

    expect($team->logo_path)->not->toBeNull()
        ->and($team->logo)->toBe(Storage::disk('public')->url($team->logo_path));
    Storage::disk('public')->assertExists($team->logo_path);
});

test('a local loops logo is generated when no team logo has been uploaded', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $expectedLogo = URL::signedRoute('avatars.show', [
        'style' => DiceBearAvatarGenerator::LOOPS,
        'seed' => $team->uuid,
    ], absolute: false);

    expect($team->logo)
        ->toBe($expectedLogo)
        ->not->toContain('api.dicebear.com')
        ->and($team->fresh()->logo)->toBe($expectedLogo)
        ->and($team->toArray()['logo'])->toBe($expectedLogo);

    $this->actingAs($user)
        ->get(route('teams.edit', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('team.logo', $expectedLogo)
            ->where('currentTeam.logo', $expectedLogo));
});

test('replacing a team logo removes the previous file', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $this
        ->actingAs($user)
        ->patch(route('teams.update', $team), [
            'name' => $team->name,
            'logo' => UploadedFile::fake()->image('logo-one.png'),
        ]);

    $originalLogoPath = $team->refresh()->logo_path;

    $this
        ->actingAs($user)
        ->patch(route('teams.update', $team), [
            'name' => $team->name,
            'logo' => UploadedFile::fake()->image('logo-two.png'),
        ]);

    $team->refresh();

    expect($team->logo_path)->not->toBe($originalLogoPath);
    Storage::disk('public')->assertMissing($originalLogoPath);
    Storage::disk('public')->assertExists($team->logo_path);
});

test('members cannot open workspace settings', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this
        ->actingAs($member)
        ->get(route('teams.edit', $team))
        ->assertForbidden();
});

test('teams cannot be updated by members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($member)
        ->patch(route('teams.update', $team), [
            'name' => 'Updated Name',
        ]);

    $response->assertForbidden();
});

test('teams can be deleted by owners', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $integration = TeamEmailIntegration::factory()->for($team)->smtp()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('teams.destroy', $team), [
            'name' => $team->name,
        ]);

    $response->assertRedirect();

    $this->assertSoftDeleted('teams', [
        'id' => $team->id,
    ]);
    $this->assertModelMissing($integration);
});

test('team deletion requires name confirmation', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($user)
        ->delete(route('teams.destroy', $team), [
            'name' => 'Wrong Name',
        ]);

    $response->assertSessionHasErrors('name');

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'deleted_at' => null,
    ]);
});

test('deleting current team switches to alphabetically first remaining team', function () {
    $user = User::factory()->create(['name' => 'Mike']);

    $zuluTeam = Team::factory()->create(['name' => 'Zulu Team']);
    $zuluTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $alphaTeam = Team::factory()->create(['name' => 'Alpha Team']);
    $alphaTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $betaTeam = Team::factory()->create(['name' => 'Beta Team']);
    $betaTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $user->update(['current_team_id' => $zuluTeam->id]);

    $response = $this
        ->actingAs($user)
        ->delete(route('teams.destroy', $zuluTeam), [
            'name' => $zuluTeam->name,
        ]);

    $response->assertRedirect(route('teams.edit', $alphaTeam));

    $this->assertSoftDeleted('teams', [
        'id' => $zuluTeam->id,
    ]);

    expect($user->fresh()->current_team_id)->toEqual($alphaTeam->id);
});

test('deleting current team falls back to personal team when alphabetically first', function () {
    $user = User::factory()->create();
    $personalTeam = $user->personalTeam();
    $team = Team::factory()->create(['name' => 'Zulu Team']);
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $user->update(['current_team_id' => $team->id]);

    $response = $this
        ->actingAs($user)
        ->delete(route('teams.destroy', $team), [
            'name' => $team->name,
        ]);

    $response->assertRedirect(route('teams.edit', $personalTeam));

    $this->assertSoftDeleted('teams', [
        'id' => $team->id,
    ]);

    expect($user->fresh()->current_team_id)->toEqual($personalTeam->id);
});

test('deleting non current team leaves current team unchanged', function () {
    $user = User::factory()->create();
    $personalTeam = $user->personalTeam();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $user->update(['current_team_id' => $personalTeam->id]);

    $response = $this
        ->actingAs($user)
        ->delete(route('teams.destroy', $team), [
            'name' => $team->name,
        ]);

    $response->assertRedirect(route('teams.edit', $personalTeam));

    $this->assertSoftDeleted('teams', [
        'id' => $team->id,
    ]);

    expect($user->fresh()->current_team_id)->toEqual($personalTeam->id);
});

test('members can leave non personal teams', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($member)
        ->delete(route('teams.leave', $team));

    $response->assertRedirect(route('teams.edit', $member->personalTeam()));
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => "You left the workspace \"{$team->name}\""]);

    expect($member->fresh()->belongsToTeam($team))->toBeFalse();
});

test('leaving current team switches to alphabetically first remaining team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['name' => 'Mike']);

    $zuluTeam = Team::factory()->create(['name' => 'Zulu Team']);
    $zuluTeam->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $zuluTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

    $alphaTeam = Team::factory()->create(['name' => 'Alpha Team']);
    $alphaTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

    $betaTeam = Team::factory()->create(['name' => 'Beta Team']);
    $betaTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

    $member->update(['current_team_id' => $zuluTeam->id]);

    $response = $this
        ->actingAs($member)
        ->delete(route('teams.leave', $zuluTeam));

    $response->assertRedirect(route('teams.edit', $alphaTeam));

    expect($member->fresh()->belongsToTeam($zuluTeam))->toBeFalse();
    expect($member->fresh()->current_team_id)->toEqual($alphaTeam->id);
});

test('personal teams cannot be left', function () {
    $user = User::factory()->create();
    $personalTeam = $user->personalTeam();

    $response = $this
        ->actingAs($user)
        ->delete(route('teams.leave', $personalTeam));

    $response->assertForbidden();

    expect($user->fresh()->belongsToTeam($personalTeam))->toBeTrue();
});

test('team owners cannot leave their team', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('teams.leave', $team));

    $response->assertForbidden();

    expect($owner->fresh()->belongsToTeam($team))->toBeTrue();
});

test('users cannot leave teams they dont belong to', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('teams.leave', $team));

    $response->assertForbidden();
});

test('deleting team switches other affected users to their personal team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $owner->update(['current_team_id' => $team->id]);
    $member->update(['current_team_id' => $team->id]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('teams.destroy', $team), [
            'name' => $team->name,
        ]);

    $response->assertRedirect();

    expect($member->fresh()->current_team_id)->toEqual($member->personalTeam()->id);
});

test('personal teams cannot be deleted', function () {
    $user = User::factory()->create();

    $personalTeam = $user->personalTeam();

    $response = $this
        ->actingAs($user)
        ->delete(route('teams.destroy', $personalTeam), [
            'name' => $personalTeam->name,
        ]);

    $response->assertForbidden();

    $this->assertDatabaseHas('teams', [
        'id' => $personalTeam->id,
        'deleted_at' => null,
    ]);
});

test('teams cannot be deleted by non owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($member)
        ->delete(route('teams.destroy', $team), [
            'name' => $team->name,
        ]);

    $response->assertForbidden();
});

test('users can switch teams', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($user)
        ->post(route('teams.switch', $team));

    $response->assertRedirect(route('dashboard', ['current_team' => $team]));

    expect($user->fresh()->current_team_id)->toEqual($team->id);
});

test('switching workspaces keeps an equivalent list page in the destination workspace', function () {
    $user = User::factory()->create();
    $personalTeam = $user->currentTeam;
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $this
        ->actingAs($user)
        ->from(route('audiences.index', [
            'current_team' => $personalTeam,
            'search' => 'product',
        ]))
        ->post(route('teams.switch', $team))
        ->assertRedirect(route('audiences.index', [
            'current_team' => $team,
            'search' => 'product',
        ]));

    expect($user->fresh()->current_team_id)->toEqual($team->id);
});

test('switching workspaces returns to the dashboard when the current page is missing', function () {
    $user = User::factory()->create();
    $personalTeam = $user->currentTeam;
    $team = Team::factory()->create();
    $audience = Audience::factory()->for($personalTeam)->create();

    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $this
        ->actingAs($user)
        ->from(route('audiences.show', [
            'current_team' => $personalTeam,
            'audience' => $audience,
        ]))
        ->post(route('teams.switch', $team))
        ->assertRedirect(route('dashboard', ['current_team' => $team]));

    expect($user->fresh()->current_team_id)->toEqual($team->id);
});

test('switching workspaces keeps account settings pages', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->post(route('teams.switch', $team))
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh()->current_team_id)->toEqual($team->id);
});

test('switching workspaces rewrites workspace settings to the destination workspace', function () {
    $user = User::factory()->create();
    $personalTeam = $user->currentTeam;
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $this
        ->actingAs($user)
        ->from(route('teams.edit', $personalTeam))
        ->post(route('teams.switch', $team))
        ->assertRedirect(route('teams.edit', $team));

    expect($user->fresh()->current_team_id)->toEqual($team->id);
});

test('users cannot switch to team they dont belong to', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('teams.switch', $team));

    $response->assertForbidden();
});

test('guests cannot access teams', function () {
    $response = $this->get(route('teams.index'));

    $response->assertRedirect(route('login'));
});
