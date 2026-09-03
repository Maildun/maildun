<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the media settings page shows the convert switch', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->get(route('media.settings.edit', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('media/settings')
            ->where('convertUploadsToWebp', false)
            ->where('canManage', true));
});

test('teams default to leaving uploads in their original format', function () {
    $user = User::factory()->create();

    expect($user->currentTeam->convert_uploads_to_webp)->toBeFalse();
});

test('owners can turn convert to webp on', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->patch(route('media.settings.update', $team), [
            'convert_uploads_to_webp' => true,
        ])
        ->assertRedirect();

    expect($team->fresh()->convert_uploads_to_webp)->toBeTrue();
});

test('admins can update media settings', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $this->actingAs($admin)
        ->patch(route('media.settings.update', $team), [
            'convert_uploads_to_webp' => true,
        ])
        ->assertRedirect();

    expect($team->fresh()->convert_uploads_to_webp)->toBeTrue();
});

test('members cannot change media settings', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);

    $this->actingAs($member)
        ->get(route('media.settings.edit', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

    $this->actingAs($member)
        ->patch(route('media.settings.update', $team), [
            'convert_uploads_to_webp' => true,
        ])
        ->assertForbidden();
});
