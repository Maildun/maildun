<?php

use App\Enums\TeamRole;
use App\Models\Audience;
use App\Models\Subscriber;
use App\Models\TransactionalEmail;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the landing pages setting shows the saved link and a blank link clears it', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->patch(route('audiences.update', [$team, $audience]), ['unsubscribed_url' => 'https://example.com/bye'])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get(route('audiences.settings.landing-pages', [$team, $audience]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('audiences/settings/landing-pages')
            ->where('audience.unsubscribed_url', 'https://example.com/bye'));

    $this->actingAs($user)
        ->patch(route('audiences.update', [$team, $audience]), ['unsubscribed_url' => '  '])
        ->assertSessionHasNoErrors();

    expect($audience->fresh()->unsubscribed_url)->toBeNull();
});

test('the double opt-in page keeps listing the selected email after it goes back to draft', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $selected = TransactionalEmail::factory()->for($team)->create();
    TransactionalEmail::factory()->for($team)->create();
    $audience = Audience::factory()->for($team)->create([
        'double_opt_in' => true,
        'double_opt_in_email_id' => $selected->id,
    ]);

    $this->actingAs($user)
        ->get(route('audiences.settings.double-opt-in', [$team, $audience]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('audience.double_opt_in_email_uuid', $selected->uuid)
            ->has('transactionalEmails', 1)
            ->where('transactionalEmails.0.uuid', $selected->uuid)
            ->where('transactionalEmails.0.published', false));
});

test('double opt-in rejects a published confirmation email from another workspace', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $foreignEmail = TransactionalEmail::factory()->for(User::factory()->create()->currentTeam)->published()->create();

    $this->actingAs($user)
        ->patch(route('audiences.update', [$team, $audience]), [
            'double_opt_in' => true,
            'double_opt_in_email_uuid' => $foreignEmail->uuid,
        ])
        ->assertInvalid(['double_opt_in_email_uuid' => 'Choose a published confirmation email.']);

    expect($audience->fresh())
        ->double_opt_in->toBeFalse()
        ->double_opt_in_email_id->toBeNull();
});

test('view-only members cannot open the landing pages or danger zone or delete the audience', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $audience = Audience::factory()->for($team)->create(['name' => 'Customers']);

    $this->actingAs($member)
        ->get(route('audiences.settings.landing-pages', [$team, $audience]))
        ->assertForbidden();
    $this->actingAs($member)
        ->get(route('audiences.settings.danger', [$team, $audience]))
        ->assertForbidden();
    $this->actingAs($member)
        ->delete(route('audiences.destroy', [$team, $audience]), ['name' => 'Customers'])
        ->assertForbidden();

    $this->assertModelExists($audience);
});

test('audience settings of another workspace are not found', function (string $routeName) {
    $user = User::factory()->create();
    $audience = Audience::factory()->for(User::factory()->create()->currentTeam)->create();

    $this->actingAs($user)
        ->get(route($routeName, [$user->currentTeam, $audience]))
        ->assertNotFound();
})->with([
    'audiences.settings.sender',
    'audiences.settings.double-opt-in',
    'audiences.settings.landing-pages',
    'audiences.settings.hygiene',
    'audiences.settings.danger',
]);

test('another workspace cannot clean up or delete an audience', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for(User::factory()->create()->currentTeam)->create(['name' => 'Customers']);
    $unconfirmed = Subscriber::factory()->for($audience)->create(['subscribed_at' => null]);
    $snapshot = ['counted_at' => now()->toISOString(), 'count' => 1];

    $this->actingAs($user)
        ->delete(route('audiences.settings.hygiene.unconfirmed.destroy', [$team, $audience]), $snapshot)
        ->assertNotFound();
    $this->actingAs($user)
        ->delete(route('audiences.settings.hygiene.inactive.destroy', [$team, $audience]), $snapshot)
        ->assertNotFound();
    $this->actingAs($user)
        ->delete(route('audiences.destroy', [$team, $audience]), ['name' => 'Customers'])
        ->assertNotFound();

    $this->assertModelExists($audience);
    $this->assertModelExists($unconfirmed);
});
