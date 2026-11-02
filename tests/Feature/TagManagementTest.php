<?php

use App\Models\Audience;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

dataset('tag pickers', [
    'subscriber dialog on the audience page' => [
        'audiences/show',
        fn (Team $team): string => route('audiences.show', [$team, Audience::factory()->for($team)->create()]),
    ],
    'contact list' => [
        'contacts/index',
        fn (Team $team): string => route('contacts.index', $team),
    ],
    'contact detail' => [
        'contacts/show',
        fn (Team $team): string => route('contacts.show', [$team, Contact::factory()->for($team)->create()]),
    ],
]);

test('the tag pickers offer the team tags from the tag settings page', function (string $component, Closure $pickerUrl) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    Tag::factory()->for(Team::factory()->create())->create(['name' => 'Other team tag']);

    $this->actingAs($user)
        ->post(route('tags.store', $team), ['name' => 'VIP', 'color' => Tag::COLORS[0]])
        ->assertRedirect();

    $this->actingAs($user)
        ->get($pickerUrl($team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component($component)
            ->has('tags', 1)
            ->where('tags.0.name', 'VIP')
            ->where('tags.0.color', Tag::COLORS[0]));
})->with('tag pickers');

test('the tag pickers drop a tag once it is deleted from the tag settings page', function (string $component, Closure $pickerUrl) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $tag = Tag::factory()->for($team)->create(['name' => 'VIP']);

    $this->actingAs($user)
        ->delete(route('tags.destroy', [$team, $tag]))
        ->assertRedirect();

    $this->actingAs($user)
        ->get($pickerUrl($team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component($component)
            ->has('tags', 0));
})->with('tag pickers');
