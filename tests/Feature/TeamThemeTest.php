<?php

use App\Enums\TeamBrandColor;
use App\Enums\TeamBrandFont;
use App\Enums\TeamBrandInputStyle;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('teams use the default brand theme', function () {
    $team = Team::factory()->make();

    expect($team->brand_color)->toBe(TeamBrandColor::Blue)
        ->and($team->brand_font)->toBe(TeamBrandFont::Inter)
        ->and($team->brand_input_style)->toBe(TeamBrandInputStyle::Default);
});

test('members can view the theme options and current values', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->get(route('teams.theme.edit', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/theme')
            ->where('team.brandTheme.color', TeamBrandColor::Blue->value)
            ->where('team.brandTheme.font', TeamBrandFont::Inter->value)
            ->where('team.brandTheme.inputStyle', TeamBrandInputStyle::Default->value)
            ->where('permissions.canUpdateTeam', true)
            ->has('colors', 15)
            ->has('fonts', 8)
            ->has('inputStyles', 3));
});

test('team theme can be saved by a manager', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->patch(route('teams.theme.update', $team), [
            'brand_color' => TeamBrandColor::Fuchsia->value,
            'brand_font' => TeamBrandFont::InstrumentSans->value,
            'brand_input_style' => TeamBrandInputStyle::Soft->value,
        ])
        ->assertRedirect();

    expect($team->fresh()->brand_color)->toBe(TeamBrandColor::Fuchsia)
        ->and($team->fresh()->brand_font)->toBe(TeamBrandFont::InstrumentSans)
        ->and($team->fresh()->brand_input_style)->toBe(TeamBrandInputStyle::Soft);
});

test('admins can change the team theme', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $this->actingAs($admin)
        ->patch(route('teams.theme.update', $team), [
            'brand_color' => TeamBrandColor::Orange->value,
            'brand_font' => TeamBrandFont::Mono->value,
            'brand_input_style' => TeamBrandInputStyle::Underline->value,
        ])
        ->assertRedirect();

    expect($team->fresh()->brand_color)->toBe(TeamBrandColor::Orange)
        ->and($team->fresh()->brand_font)->toBe(TeamBrandFont::Mono)
        ->and($team->fresh()->brand_input_style)->toBe(TeamBrandInputStyle::Underline);
});

test('team theme values must be supported options', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('teams.theme.update', $user->currentTeam), [
            'brand_color' => 'magenta',
            'brand_font' => 'comic-sans',
            'brand_input_style' => 'pill',
        ])
        ->assertInvalid(['brand_color', 'brand_font', 'brand_input_style']);
});

test('members cannot access brand theme settings', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member)
        ->get(route('teams.theme.edit', $team))
        ->assertForbidden();

    $this->actingAs($member)
        ->patch(route('teams.theme.update', $team), [
            'brand_color' => TeamBrandColor::Rose->value,
            'brand_font' => TeamBrandFont::Mono->value,
            'brand_input_style' => TeamBrandInputStyle::Underline->value,
        ])
        ->assertForbidden();
});

test('non members cannot view or update a team theme', function () {
    $team = Team::factory()->create();
    $team->members()->attach(User::factory()->create(), ['role' => TeamRole::Owner->value]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('teams.theme.edit', $team))
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('teams.theme.update', $team), [
            'brand_color' => TeamBrandColor::Blue->value,
            'brand_font' => TeamBrandFont::Inter->value,
            'brand_input_style' => TeamBrandInputStyle::Default->value,
        ])
        ->assertForbidden();
});

test('the current team shared prop includes its brand theme', function () {
    $user = User::factory()->create();
    $user->currentTeam->update(['brand_color' => TeamBrandColor::Emerald]);

    $this->actingAs($user)
        ->get(route('teams.edit', $user->currentTeam))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentTeam.brandTheme.color', TeamBrandColor::Emerald->value));
});
