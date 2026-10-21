<?php

use App\Models\Audience;
use App\Models\Contact;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use App\Services\ManageContact;
use App\Services\ResolveContactCompany;

test('adding an existing contact to another audience keeps its tags and adds the new ones', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($team))->create(['email' => 'ada@example.com']);
    $subscriber->contact->tags()->attach(Tag::factory()->for($team)->create(['name' => 'Customer']));
    $other = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('audiences.subscribers.store', [$team, $other]), [
            'email' => 'ada@example.com',
            'consent_confirmed' => true,
            'tags' => ['Webinar'],
        ])
        ->assertSessionHasNoErrors();

    expect($subscriber->contact->tags()->orderBy('name')->pluck('name')->all())->toBe(['Customer', 'Webinar']);
});

test('editing a subscriber without sending tags keeps the contact tags', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $subscriber->contact->tags()->attach(Tag::factory()->for($team)->create(['name' => 'Customer']));

    $this->actingAs($user)
        ->patch(route('audiences.subscribers.update', [$team, $audience, $subscriber]), [
            'email' => $subscriber->email,
            'first_name' => 'Renamed',
        ])
        ->assertSessionHasNoErrors();

    expect($subscriber->contact->tags()->pluck('name')->all())->toBe(['Customer']);
});

test('a tag typed in different capitals reuses the existing tag', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $vip = Tag::factory()->for($team)->create(['name' => 'VIP']);
    $contact = Contact::factory()->for($team)->create();

    $this->actingAs($user)
        ->patch(route('contacts.update', [$team, $contact]), [
            'email' => $contact->email,
            'company_assignment_mode' => 'automatic',
            'tags' => ['vip'],
        ])
        ->assertSessionHasNoErrors();

    expect($contact->tags()->pluck('tags.id')->all())->toBe([$vip->id])
        ->and($team->tags()->count())->toBe(1);
});

test('a contact past the tag limit can still be edited but cannot grow', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $contact = Contact::factory()->for($team)->create();
    $tags = Tag::factory()->for($team)->count(21)->create();
    $contact->tags()->attach($tags);
    $names = $tags->pluck('name')->all();

    $this->actingAs($user)
        ->patch(route('contacts.update', [$team, $contact]), [
            'email' => $contact->email,
            'company_assignment_mode' => 'automatic',
            'tags' => $names,
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->patch(route('contacts.update', [$team, $contact]), [
            'email' => $contact->email,
            'company_assignment_mode' => 'automatic',
            'tags' => [...$names, 'One more'],
        ])
        ->assertSessionHasErrors('tags');

    expect($contact->tags()->count())->toBe(21);
});

test('a subscriber past the tag limit can still be edited', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $tags = Tag::factory()->for($team)->count(21)->create();
    $subscriber->contact->tags()->attach($tags);

    $this->actingAs($user)
        ->patch(route('audiences.subscribers.update', [$team, $audience, $subscriber]), [
            'email' => $subscriber->email,
            'tags' => $tags->pluck('name')->all(),
        ])
        ->assertSessionHasNoErrors();
});

test('a contact edit that fails while saving tags changes nothing', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($team))->create(['email' => 'ada@example.com']);
    $contact = $subscriber->contact;
    app()->instance(ManageContact::class, new class(app(ResolveContactCompany::class)) extends ManageContact
    {
        public function syncTags(Team $team, Contact $contact, array $names): array
        {
            throw new RuntimeException('Tag storage is unavailable.');
        }
    });

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($user)->patch(route('contacts.update', [$team, $contact]), [
        'email' => 'grace@example.com',
        'company_assignment_mode' => 'automatic',
        'tags' => ['VIP'],
    ]))->toThrow(RuntimeException::class);

    expect($contact->fresh()->email)->toBe('ada@example.com')
        ->and($subscriber->fresh()->email)->toBe('ada@example.com');
});
