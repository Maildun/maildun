<?php

use App\Models\Audience;
use App\Models\Company;
use App\Models\Contact;
use App\Models\EmailDelivery;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the contact index includes the fields required to edit from its hover card', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $company = Company::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->create([
        'company_id' => $company->id,
        'company_assignment_mode' => 'manual',
    ]);

    $this->actingAs($user)
        ->get(route('contacts.index', $team))
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/index')
            ->where('contacts.data.0.uuid', $contact->uuid)
            ->where('contacts.data.0.company_assignment_mode', 'manual')
            ->where('contacts.data.0.company.uuid', $company->uuid));
});

test('audience memberships share one contact profile for the same email', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $firstAudience = Audience::factory()->for($team)->create();
    $secondAudience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('audiences.subscribers.store', [$team, $firstAudience]), [
            'email' => 'Taylor@Acme.test',
            'first_name' => 'Taylor',
            'last_name' => 'Otwell',
            'consent_confirmed' => true,
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('audiences.subscribers.store', [$team, $secondAudience]), [
            'email' => 'taylor@acme.test',
            'first_name' => 'Replacement',
            'last_name' => 'Name',
            'consent_confirmed' => true,
        ])
        ->assertRedirect();

    $contact = $team->contacts()->sole();
    $memberships = Subscriber::query()
        ->whereIn('audience_id', [$firstAudience->id, $secondAudience->id])
        ->get();

    expect($contact->email)->toBe('taylor@acme.test')
        ->and($contact->first_name)->toBe('Taylor')
        ->and($contact->last_name)->toBe('Otwell')
        ->and($memberships)->toHaveCount(2)
        ->and($memberships->pluck('contact_id')->unique()->all())->toBe([$contact->id])
        ->and($memberships->pluck('first_name')->unique()->all())->toBe(['Taylor'])
        ->and($memberships->pluck('last_name')->unique()->all())->toBe(['Otwell']);

    $this->actingAs($user)
        ->get(route('contacts.show', [$team, $contact]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/show')
            ->where('contact.uuid', $contact->uuid)
            ->has('contact.memberships', 2));
});

test('contact lookup is normalized and scoped to the current team', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create(['name' => 'Customers']);
    $contact = Contact::factory()->for($team)->create([
        'email' => 'taylor@example.com',
        'first_name' => 'Taylor',
        'last_name' => 'Otwell',
    ]);
    $tag = Tag::factory()->for($team)->create(['name' => 'VIP']);
    $contact->tags()->attach($tag);
    Subscriber::factory()->for($audience)->create([
        'email' => $contact->email,
        'first_name' => $contact->first_name,
        'last_name' => $contact->last_name,
    ]);

    $this->actingAs($user)
        ->getJson(route('contacts.lookup', [
            'current_team' => $team,
            'email' => ' TAYLOR@EXAMPLE.COM ',
        ]))
        ->assertOk()
        ->assertJsonPath('contact.uuid', $contact->uuid)
        ->assertJsonPath('contact.first_name', 'Taylor')
        ->assertJsonPath('contact.tags.0.name', 'VIP')
        ->assertJsonPath('contact.memberships.0.audience.uuid', $audience->uuid)
        ->assertJsonPath('contact.memberships.0.status', 'subscribed');

    $foreignContact = Contact::factory()->create([
        'email' => 'foreign@example.com',
    ]);

    $this->actingAs($user)
        ->getJson(route('contacts.lookup', [
            'current_team' => $team,
            'email' => $foreignContact->email,
        ]))
        ->assertOk()
        ->assertExactJson(['contact' => null]);
});

test('a contact can be created and added to multiple audiences in one submission', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $firstAudience = Audience::factory()->for($team)->create();
    $secondAudience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('contacts.store', $team), [
            'email' => 'new@example.com',
            'first_name' => 'New',
            'last_name' => 'Contact',
            'company_assignment_mode' => 'automatic',
            'company_uuid' => '',
            'tags' => ['Customer'],
            'audience_uuids' => [$firstAudience->uuid, $secondAudience->uuid],
            'consent_confirmed' => true,
        ])
        ->assertValid()
        ->assertRedirect();

    $contact = $team->contacts()->sole();

    expect($contact->email)->toBe('new@example.com')
        ->and($contact->tags()->pluck('name')->all())->toBe(['Customer'])
        ->and($contact->subscribers)->toHaveCount(2)
        ->and($contact->subscribers->pluck('audience_id')->sort()->values()->all())
        ->toBe(collect([$firstAudience->id, $secondAudience->id])->sort()->values()->all());
});

test('adding an existing contact to another audience preserves its shared profile', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->create([
        'email' => 'existing@example.com',
        'first_name' => 'Existing',
        'last_name' => 'Person',
    ]);
    $tag = Tag::factory()->for($team)->create(['name' => 'VIP']);
    $contact->tags()->attach($tag);

    $this->actingAs($user)
        ->post(route('contacts.store', $team), [
            'email' => 'EXISTING@example.com',
            'first_name' => 'Replacement',
            'last_name' => 'Name',
            'company_assignment_mode' => 'automatic',
            'company_uuid' => '',
            'tags' => ['Replacement'],
            'audience_uuids' => [$audience->uuid],
            'consent_confirmed' => true,
        ])
        ->assertValid()
        ->assertRedirect();

    $contact->refresh();
    $subscriber = $audience->subscribers()->sole();

    expect($team->contacts()->count())->toBe(1)
        ->and($contact->first_name)->toBe('Existing')
        ->and($contact->last_name)->toBe('Person')
        ->and($contact->tags()->pluck('name')->all())->toBe(['VIP'])
        ->and($subscriber->contact_id)->toBe($contact->id)
        ->and($subscriber->first_name)->toBe('Existing')
        ->and($subscriber->last_name)->toBe('Person');
});

test('an existing contact requires an audience when reused from the add dialog', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    Contact::factory()->for($team)->create(['email' => 'existing@example.com']);

    $this->actingAs($user)
        ->post(route('contacts.store', $team), [
            'email' => 'existing@example.com',
            'company_assignment_mode' => 'automatic',
            'company_uuid' => '',
            'tags' => [],
            'audience_uuids' => [],
        ])
        ->assertInvalid(['email' => 'This contact already exists. Select at least one audience to continue.']);

    expect($team->contacts()->count())->toBe(1);
});

test('contacts can be managed independently and deletion removes memberships but preserves delivery snapshots', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('contacts.store', $team), [
            'email' => 'person@example.test',
            'first_name' => 'Person',
            'last_name' => 'Example',
            'company_assignment_mode' => 'automatic',
            'company_uuid' => '',
            'tags' => ['Customer'],
        ])
        ->assertRedirect();

    $contact = $team->contacts()->sole();

    $this->actingAs($user)
        ->post(route('contacts.audiences.store', [$team, $contact]), [
            'audience_uuid' => $audience->uuid,
            'consent_confirmed' => true,
        ])
        ->assertRedirect();

    $subscriber = $audience->subscribers()->sole();
    $delivery = EmailDelivery::factory()->create([
        'subscriber_id' => $subscriber->id,
        'contact_id' => $contact->id,
    ]);

    $this->actingAs($user)
        ->delete(route('contacts.destroy', [$team, $contact]), [
            'confirmation' => $contact->email,
        ])
        ->assertRedirect(route('contacts.index', $team));

    $this->assertModelMissing($contact);
    $this->assertModelMissing($subscriber);
    $this->assertModelExists($delivery);
    expect($delivery->fresh()->contact_id)->toBeNull();
});
