<?php

use App\Enums\SubscriberStatus;
use App\Models\Audience;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('editing a contact updates its profile and every audience membership copy', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $contact = Contact::factory()->for($team)->create(['email' => 'old@example.com']);
    $memberships = Audience::factory()->for($team)->count(2)->create()->map(
        fn (Audience $audience): Subscriber => Subscriber::factory()->for($audience)->for($contact)->create(['email' => $contact->email]),
    );

    $this->actingAs($user)
        ->put(route('contacts.update', [$team, $contact]), [
            'email' => ' New@Example.com ',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'company_assignment_mode' => 'automatic',
            'company_uuid' => '',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($contact->refresh())
        ->email->toBe('new@example.com')
        ->first_name->toBe('Ada')
        ->last_name->toBe('Lovelace');
    $memberships->each(fn (Subscriber $subscriber) => expect($subscriber->refresh())
        ->email->toBe('new@example.com')
        ->first_name->toBe('Ada')
        ->last_name->toBe('Lovelace'));
});

test('editing a contact rejects an email another contact in the workspace already uses', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    Contact::factory()->for($team)->create(['email' => 'taken@example.com']);
    $contact = Contact::factory()->for($team)->create(['email' => 'mine@example.com']);

    $this->actingAs($user)
        ->put(route('contacts.update', [$team, $contact]), [
            'email' => 'TAKEN@example.com',
            'company_assignment_mode' => 'automatic',
        ])
        ->assertSessionHasErrors(['email' => 'This email is already used by another contact.']);

    expect($contact->refresh()->email)->toBe('mine@example.com');
});

test('deleting a contact requires typing its email address', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $contact = Contact::factory()->for($team)->create(['email' => 'ada@example.com']);

    $this->actingAs($user)
        ->delete(route('contacts.destroy', [$team, $contact]), ['confirmation' => 'someone@example.com'])
        ->assertSessionHasErrors('confirmation');

    $this->assertModelExists($contact);
});

test('a contact from another workspace cannot be viewed, edited or deleted', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $foreign = Contact::factory()->create(['email' => 'foreign@example.com']);

    $this->actingAs($user)
        ->get(route('contacts.show', [$team, $foreign]))
        ->assertNotFound();
    $this->actingAs($user)
        ->put(route('contacts.update', [$team, $foreign]), [
            'email' => 'changed@example.com',
            'company_assignment_mode' => 'automatic',
        ])
        ->assertNotFound();
    $this->actingAs($user)
        ->delete(route('contacts.destroy', [$team, $foreign]), ['confirmation' => $foreign->email])
        ->assertNotFound();

    expect($foreign->refresh()->email)->toBe('foreign@example.com');
});

test('contacts can be filtered by tag, company and audience', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $tag = Tag::factory()->for($team)->create(['name' => 'VIP']);
    $company = Company::factory()->for($team)->create();
    $audience = Audience::factory()->for($team)->create();
    $tagged = Contact::factory()->for($team)->create(['email' => 'tagged@example.com']);
    $tagged->tags()->attach($tag);
    $employee = Contact::factory()->for($team)->create([
        'email' => 'employee@example.com',
        'company_id' => $company->id,
        'company_assignment_mode' => 'manual',
    ]);
    $member = Contact::factory()->for($team)->create(['email' => 'member@example.com']);
    Subscriber::factory()->for($audience)->for($member)->create(['email' => $member->email]);

    $filtered = fn (array $filters) => $this->actingAs($user)
        ->get(route('contacts.index', ['current_team' => $team, ...$filters]));

    $filtered(['tag' => $tag->uuid])->assertInertia(fn (Assert $page) => $page
        ->has('contacts.data', 1)
        ->where('contacts.data.0.uuid', $tagged->uuid));
    $filtered(['company' => $company->uuid])->assertInertia(fn (Assert $page) => $page
        ->has('contacts.data', 1)
        ->where('contacts.data.0.uuid', $employee->uuid));
    $filtered(['company' => 'assigned'])->assertInertia(fn (Assert $page) => $page
        ->has('contacts.data', 1)
        ->where('contacts.data.0.uuid', $employee->uuid));
    $filtered(['company' => 'unassigned'])->assertInertia(fn (Assert $page) => $page
        ->has('contacts.data', 2));
    $filtered(['audience' => $audience->uuid])->assertInertia(fn (Assert $page) => $page
        ->has('contacts.data', 1)
        ->where('contacts.data.0.uuid', $member->uuid));
});

test('contact search matches part of an email address', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $match = Contact::factory()->for($team)->create(['email' => 'grace.hopper@navy.example']);
    Contact::factory()->for($team)->create(['email' => 'ada@example.com']);

    $this->actingAs($user)
        ->get(route('contacts.index', ['current_team' => $team, 'search' => 'navy']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'navy')
            ->has('contacts.data', 1)
            ->where('contacts.data.0.uuid', $match->uuid));
});

test('removing a contact from an audience deletes only that membership', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $contact = Contact::factory()->for($team)->create(['email' => 'ada@example.com']);
    $removed = Subscriber::factory()->for(Audience::factory()->for($team))->for($contact)->create(['email' => $contact->email]);
    $kept = Subscriber::factory()->for(Audience::factory()->for($team))->for($contact)->create(['email' => $contact->email]);

    $this->actingAs($user)
        ->delete(route('contacts.audiences.destroy', [$team, $contact, $removed]))
        ->assertRedirect();

    $this->assertModelMissing($removed);
    $this->assertModelExists($kept);
    $this->assertModelExists($contact);
});

test('a membership of another contact cannot be removed through this contact', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->create(['email' => 'ada@example.com']);
    $otherMembership = Subscriber::factory()->for($audience)->for(Contact::factory()->for($team)->state(['email' => 'grace@example.com']))->create(['email' => 'grace@example.com']);

    $this->actingAs($user)
        ->delete(route('contacts.audiences.destroy', [$team, $contact, $otherMembership]))
        ->assertNotFound();

    $this->assertModelExists($otherMembership);
});

test('adding a contact to an audience requires consent confirmation', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('contacts.audiences.store', [$team, $contact]), ['audience_uuid' => $audience->uuid])
        ->assertSessionHasErrors('consent_confirmed');

    expect($audience->subscribers()->exists())->toBeFalse();
});

test('bulk unsubscribe rejects contacts from another workspace', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $foreign = Subscriber::factory()->create(['email' => 'foreign@example.com']);

    $this->actingAs($user)
        ->patch(route('contacts.bulk-unsubscribe', $team), ['ids' => [$foreign->refresh()->contact->uuid]])
        ->assertSessionHasErrors('ids.0');

    expect($foreign->refresh()->status)->toBe(SubscriberStatus::Subscribed);
});
