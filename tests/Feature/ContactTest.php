<?php

use App\Enums\AutomationTrigger;
use App\Enums\EmailDeliveryStatus;
use App\Enums\SubscriberLanguage;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Events\SubscriberLifecycleOccurred;
use App\Models\Audience;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\SubscribeForm;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkspaceRole;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Add a member whose custom role manages contacts but not audiences.
 */
function contactOnlyMember(Team $team): User
{
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $role = WorkspaceRole::create([
        'team_id' => $team->id,
        'name' => 'contact-manager',
        'label' => 'Contact manager',
        'guard_name' => 'web',
    ]);
    $role->syncPermissions([TeamPermission::ManageContact->value]);
    $team->memberships()
        ->where('user_id', $member->id)
        ->sole()
        ->update(['role' => $role->name]);

    return $member;
}

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

test('adding a contact to an audience from its profile does not confirm a pending double opt-in membership', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create(['double_opt_in' => true]);
    $form = SubscribeForm::factory()->for($audience)->create();
    $pending = Subscriber::factory()->for($audience)->fromForm($form)->pendingConfirmation()->create([
        'email' => 'pending@example.com',
        'consent_text' => 'Yes, send me the newsletter.',
        'consent_ip' => '203.0.113.9',
    ]);
    Event::fake([SubscriberLifecycleOccurred::class]);

    $this->actingAs($user)
        ->post(route('contacts.audiences.store', [$team, $pending->contact]), [
            'audience_uuid' => $audience->uuid,
            'consent_confirmed' => true,
        ])
        ->assertValid()
        ->assertRedirect();

    $pending->refresh();

    expect($pending->isPendingConfirmation())->toBeTrue()
        ->and($pending->source)->toBe(SubscriberSource::Form)
        ->and($pending->consent_text)->toBe('Yes, send me the newsletter.')
        ->and($pending->consent_ip)->toBe('203.0.113.9');
    Event::assertNotDispatched(SubscriberLifecycleOccurred::class);
});

test('creating a contact fires tagged automations once its audience memberships exist', function () {
    Event::fake([SubscriberLifecycleOccurred::class]);
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('contacts.store', $team), [
            'email' => 'new@example.com',
            'company_assignment_mode' => 'automatic',
            'company_uuid' => '',
            'tags' => ['Customer'],
            'audience_uuids' => [$audience->uuid],
            'consent_confirmed' => true,
        ])
        ->assertValid()
        ->assertRedirect();

    $subscriber = $audience->subscribers()->sole();
    $customer = $team->tags()->where('name', 'Customer')->sole();

    Event::assertDispatched(SubscriberLifecycleOccurred::class, fn (SubscriberLifecycleOccurred $event): bool => $event->trigger === AutomationTrigger::Tagged
        && $event->subscriber->is($subscriber)
        && $event->context === ['tag_uuid' => $customer->uuid]);
});

test('editing a contact fires tagged automations on every membership for newly added tags only', function () {
    Event::fake([SubscriberLifecycleOccurred::class]);
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($team))->create();
    $otherMembership = Subscriber::factory()->for(Audience::factory()->for($team))->create(['email' => $subscriber->email]);
    $contact = $subscriber->contact;
    $contact->tags()->attach(Tag::factory()->for($team)->create(['name' => 'VIP']));

    $this->actingAs($user)
        ->patch(route('contacts.update', [$team, $contact]), [
            'email' => $contact->email,
            'company_assignment_mode' => 'automatic',
            'company_uuid' => '',
            'tags' => ['VIP', 'Customer'],
        ])
        ->assertValid()
        ->assertRedirect();

    $customer = $team->tags()->where('name', 'Customer')->sole();

    Event::assertDispatchedTimes(SubscriberLifecycleOccurred::class, 2);

    foreach ([$subscriber, $otherMembership] as $membership) {
        Event::assertDispatched(SubscriberLifecycleOccurred::class, fn (SubscriberLifecycleOccurred $event): bool => $event->trigger === AutomationTrigger::Tagged
            && $event->subscriber->is($membership)
            && $event->context === ['tag_uuid' => $customer->uuid]);
    }
});

test('a new contact can be added without an audience while the dialog sends unchecked consent', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('contacts.store', $team), [
            'email' => 'workspace-only@example.com',
            'company_assignment_mode' => 'automatic',
            'company_uuid' => '',
            'tags' => [],
            'audience_uuids' => [],
            'consent_confirmed' => false,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $contact = $team->contacts()->sole();

    expect($contact->email)->toBe('workspace-only@example.com')
        ->and(Subscriber::query()->where('contact_id', $contact->id)->exists())->toBeFalse();
});

test('adding a new contact to an audience still requires consent confirmation', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('contacts.store', $team), [
            'email' => 'needs-consent@example.com',
            'company_assignment_mode' => 'automatic',
            'company_uuid' => '',
            'tags' => [],
            'audience_uuids' => [$audience->uuid],
            'consent_confirmed' => false,
        ])
        ->assertInvalid(['consent_confirmed']);

    expect($team->contacts()->exists())->toBeFalse();
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

test('a recipient a stopped campaign never sent to has not received that email', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $contact = Contact::factory()->for($team)->create();
    $email = Email::factory()->for($team)->create();
    EmailDelivery::factory()->for($email)->for($contact)->create(['status' => EmailDeliveryStatus::Delivered]);
    EmailDelivery::factory()->for($email)->for($contact)->create(['status' => EmailDeliveryStatus::Cancelled]);

    $this->actingAs($user)
        ->get(route('contacts.show', [$team, $contact]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activity.received', 1)
            ->has('contact.deliveries', 2));
});

test('manually added memberships take the audience default language', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create([
        'allowed_languages' => ['en', 'id'],
        'default_language' => 'id',
    ]);

    $this->actingAs($user)
        ->post(route('contacts.store', $team), [
            'email' => 'new@example.com',
            'company_assignment_mode' => 'automatic',
            'company_uuid' => '',
            'audience_uuids' => [$audience->uuid],
            'consent_confirmed' => true,
        ])
        ->assertValid();

    expect($audience->subscribers()->sole()->language)->toBe(SubscriberLanguage::Indonesian);
});

test('a contact cannot be added to an audience without audience access', function () {
    Event::fake([SubscriberLifecycleOccurred::class]);
    $team = User::factory()->create()->currentTeam;
    $member = contactOnlyMember($team);
    $audience = Audience::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->create();

    $this->actingAs($member)
        ->post(route('contacts.audiences.store', [$team, $contact]), [
            'audience_uuid' => $audience->uuid,
            'consent_confirmed' => true,
        ])
        ->assertForbidden();

    expect($audience->subscribers()->exists())->toBeFalse();
    Event::assertNotDispatched(SubscriberLifecycleOccurred::class);
});

test('a contact cannot be removed from an audience without audience access', function () {
    $team = User::factory()->create()->currentTeam;
    $member = contactOnlyMember($team);
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($team))->unsubscribed()->create();

    $this->actingAs($member)
        ->delete(route('contacts.audiences.destroy', [$team, $subscriber->contact, $subscriber]))
        ->assertForbidden();

    $this->assertModelExists($subscriber);
    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Unsubscribed);
});

test('a new contact cannot be subscribed to audiences without audience access', function () {
    $team = User::factory()->create()->currentTeam;
    $member = contactOnlyMember($team);
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($member)
        ->post(route('contacts.store', $team), [
            'email' => 'new@example.com',
            'company_assignment_mode' => 'automatic',
            'company_uuid' => '',
            'audience_uuids' => [$audience->uuid],
            'consent_confirmed' => true,
        ])
        ->assertForbidden();

    expect($team->contacts()->exists())->toBeFalse()
        ->and($audience->subscribers()->exists())->toBeFalse();
});

test('members with audience access can remove a contact from an audience', function () {
    $team = User::factory()->create()->currentTeam;
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($team))->create();

    $this->actingAs($member)
        ->delete(route('contacts.audiences.destroy', [$team, $subscriber->contact, $subscriber]))
        ->assertRedirect();

    $this->assertModelMissing($subscriber);
});

test('the contact profile offers no audiences to join without audience access', function () {
    $team = User::factory()->create()->currentTeam;
    $member = contactOnlyMember($team);
    Audience::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->create();

    $this->actingAs($member)
        ->get(route('contacts.show', [$team, $contact]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/show')
            ->where('audiences', []));
});

test('the add contact dialog offers every audience to members with audience access', function () {
    $team = User::factory()->create()->currentTeam;
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $customers = Audience::factory()->for($team)->create(['name' => 'Customers']);
    $newsletter = Audience::factory()->for($team)->create(['name' => 'Newsletter']);

    $this->actingAs($member)
        ->get(route('contacts.index', $team))
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/index')
            ->where('addableAudiences', [
                ['uuid' => $customers->uuid, 'name' => 'Customers'],
                ['uuid' => $newsletter->uuid, 'name' => 'Newsletter'],
            ])
            ->where('canAddToAudiences', true));
});

test('the add contact dialog offers no audiences without audience access but the audience filter keeps them', function () {
    $team = User::factory()->create()->currentTeam;
    $member = contactOnlyMember($team);
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($member)
        ->get(route('contacts.index', $team))
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/index')
            ->where('addableAudiences', [])
            ->where('canAddToAudiences', false)
            ->where('audiences.0.uuid', $audience->uuid));
});

test('the add contact dialog explains missing audience access before any audience exists', function () {
    $team = User::factory()->create()->currentTeam;
    $member = contactOnlyMember($team);

    $this->actingAs($member)
        ->get(route('contacts.index', $team))
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/index')
            ->where('canAddToAudiences', false));
});

test('a contact in an audience cannot be deleted without audience access', function () {
    $team = User::factory()->create()->currentTeam;
    $member = contactOnlyMember($team);
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($team))->unsubscribed()->create();
    $contact = $subscriber->contact;

    $this->actingAs($member)
        ->delete(route('contacts.destroy', [$team, $contact]), [
            'confirmation' => $contact->email,
        ])
        ->assertForbidden();

    $this->assertModelExists($contact);
    $this->assertModelExists($subscriber);
});

test('a contact in no audience can be deleted with contact access alone', function () {
    $team = User::factory()->create()->currentTeam;
    $member = contactOnlyMember($team);
    $contact = Contact::factory()->for($team)->create();

    $this->actingAs($member)
        ->delete(route('contacts.destroy', [$team, $contact]), [
            'confirmation' => $contact->email,
        ])
        ->assertRedirect(route('contacts.index', $team));

    $this->assertModelMissing($contact);
});

test('the contact profile offers delete without audience access only for a contact in no audience', function (int $memberships, bool $canDelete) {
    $team = User::factory()->create()->currentTeam;
    $member = contactOnlyMember($team);
    $contact = Contact::factory()->for($team)->create();
    Subscriber::factory()
        ->count($memberships)
        ->for(Audience::factory()->for($team))
        ->create(['email' => $contact->email]);

    $this->actingAs($member)
        ->get(route('contacts.show', [$team, $contact]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/show')
            ->where('canDelete', $canDelete));
})->with([
    'contact in an audience' => [1, false],
    'contact in no audience' => [0, true],
]);

test('the contact profile offers delete to members with audience access for a contact in an audience', function () {
    $team = User::factory()->create()->currentTeam;
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($team))->create();

    $this->actingAs($member)
        ->get(route('contacts.show', [$team, $subscriber->contact]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/show')
            ->where('canDelete', true));
});

test('contacts can be filtered by the language of any of their memberships', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $english = Subscriber::factory()->for($audience)->for(Contact::factory()->for($team)->state(['email' => 'en@example.com']))->create([
        'email' => 'en@example.com',
        'language' => SubscriberLanguage::English,
    ]);
    Subscriber::factory()->for($audience)->for(Contact::factory()->for($team)->state(['email' => 'id@example.com']))->create([
        'email' => 'id@example.com',
        'language' => SubscriberLanguage::Indonesian,
    ]);

    $this->actingAs($user)
        ->get(route('contacts.index', [$team, 'language' => 'en']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.language', 'en')
            ->has('contacts.data', 1)
            ->where('contacts.data.0.uuid', $english->contact->uuid));
});

test('selected contacts can be unsubscribed from every audience at once', function () {
    Event::fake([SubscriberLifecycleOccurred::class]);
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $contact = Contact::factory()->for($team)->create(['email' => 'ada@example.com']);
    $memberships = Audience::factory()->for($team)->count(2)->create()->map(
        fn (Audience $audience): Subscriber => Subscriber::factory()->for($audience)->for($contact)->create(['email' => $contact->email]),
    );
    $other = Subscriber::factory()->for(Audience::factory()->for($team))->for(Contact::factory()->for($team)->state(['email' => 'grace@example.com']))->create(['email' => 'grace@example.com']);

    $this->actingAs($user)
        ->patch(route('contacts.bulk-unsubscribe', $team), ['ids' => [$contact->uuid]])
        ->assertRedirect();

    $memberships->each(fn (Subscriber $subscriber) => expect($subscriber->refresh()->status)->toBe(SubscriberStatus::Unsubscribed));
    expect($other->refresh()->status)->toBe(SubscriberStatus::Subscribed);
    Event::assertDispatchedTimes(SubscriberLifecycleOccurred::class, 2);
});

test('selected contacts can be deleted together but only within the current team', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $selected = Contact::factory()->for($team)->count(2)->create();
    $kept = Contact::factory()->for($team)->create();
    $foreign = Contact::factory()->create();

    $this->actingAs($user)
        ->delete(route('contacts.bulk-destroy', $team), ['ids' => [$foreign->uuid]])
        ->assertSessionHasErrors('ids.0');

    $this->actingAs($user)
        ->delete(route('contacts.bulk-destroy', $team), ['ids' => $selected->pluck('uuid')->all()])
        ->assertRedirect();

    expect(Contact::query()->whereKey($selected->modelKeys())->exists())->toBeFalse()
        ->and($kept->fresh())->not->toBeNull()
        ->and($foreign->fresh())->not->toBeNull();
});

test('viewers cannot bulk delete contacts', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $contact = Contact::factory()->for($team)->create();

    $this->actingAs($member)
        ->delete(route('contacts.bulk-destroy', $team), ['ids' => [$contact->uuid]])
        ->assertForbidden();

    expect($contact->fresh())->not->toBeNull();
});

test('a contact-only role cannot add, remove or resubscribe audience memberships from the contact side', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignCustomRole($team, $member, [TeamPermission::ManageContact]);
    $audience = Audience::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->create(['email' => 'ada@example.com']);
    $unsubscribed = Subscriber::factory()->for(Audience::factory()->for($team))->for($contact)->unsubscribed()->create(['email' => 'ada@example.com']);

    $this->actingAs($member)
        ->post(route('contacts.audiences.store', [$team, $contact]), [
            'audience_uuid' => $audience->uuid,
            'consent_confirmed' => true,
        ])
        ->assertForbidden();
    $this->actingAs($member)
        ->post(route('contacts.store', $team), [
            'email' => 'ada@example.com',
            'company_assignment_mode' => 'automatic',
            'company_uuid' => '',
            'audience_uuids' => [$unsubscribed->audience->uuid],
            'consent_confirmed' => true,
        ])
        ->assertForbidden();
    $this->actingAs($member)
        ->delete(route('contacts.audiences.destroy', [$team, $contact, $unsubscribed]))
        ->assertForbidden();

    expect($audience->subscribers()->exists())->toBeFalse()
        ->and($unsubscribed->refresh()->status)->toBe(SubscriberStatus::Unsubscribed);
});

test('a contact-only role cannot bulk unsubscribe audience memberships', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignCustomRole($team, $member, [TeamPermission::ManageContact]);
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($team))->create(['email' => 'ada@example.com']);

    $this->actingAs($member)
        ->patch(route('contacts.bulk-unsubscribe', $team), ['ids' => [$subscriber->refresh()->contact->uuid]])
        ->assertForbidden();

    expect($subscriber->refresh()->status)->toBe(SubscriberStatus::Subscribed);
});

test('the contact pages only offer membership actions to roles that can manage audiences', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignCustomRole($team, $member, [TeamPermission::ManageContact]);
    $contact = Contact::factory()->for($team)->create();

    Audience::factory()->for($team)->create();

    $this->actingAs($member)
        ->get(route('contacts.show', [$team, $contact]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canManage', true)
            ->where('audiences', []));
    $this->actingAs($member)
        ->get(route('contacts.index', $team))
        ->assertInertia(fn (Assert $page) => $page->where('canAddToAudiences', false));
    $this->actingAs($owner)
        ->get(route('contacts.index', $team))
        ->assertInertia(fn (Assert $page) => $page->where('canAddToAudiences', true));
});

test('resubscribing from the contact dialog keeps the membership source and form', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $form = SubscribeForm::factory()->for($audience)->create();
    $subscriber = Subscriber::factory()->for($audience)->unsubscribed()->create([
        'email' => 'ada@example.com',
        'source' => SubscriberSource::Form,
        'subscribe_form_id' => $form->id,
    ]);

    $this->actingAs($user)
        ->post(route('contacts.store', $team), [
            'email' => 'ada@example.com',
            'company_assignment_mode' => 'automatic',
            'company_uuid' => '',
            'audience_uuids' => [$audience->uuid],
            'consent_confirmed' => true,
        ])
        ->assertRedirect();

    expect($subscriber->refresh())
        ->status->toBe(SubscriberStatus::Subscribed)
        ->source->toBe(SubscriberSource::Form)
        ->subscribe_form_id->toBe($form->id);
});

test('list-shaped filters on the contacts list fall back to no filter', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $contact = Contact::factory()->for($team)->create();

    $this->actingAs($user)
        ->get(route('contacts.index', [
            'current_team' => $team,
            'search' => ['ada'],
            'company' => ['assigned'],
            'audience' => ['x'],
            'tag' => ['x'],
            'language' => ['en'],
            'sort' => ['name'],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('contacts.data.0.uuid', $contact->uuid)
            ->where('filters', ['search' => '', 'company' => '', 'audience' => '', 'tag' => '', 'language' => '', 'sort' => 'newest']));
});

test('a list-shaped contact email is a validation error', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('contacts.store', $user->currentTeam), [
            'email' => ['ada@example.com'],
            'company_assignment_mode' => 'automatic',
        ])
        ->assertSessionHasErrors(['email' => 'The email field must be a string.']);

    expect(Contact::query()->where('team_id', $user->currentTeam->id)->exists())->toBeFalse();
});

test('owners can bulk add and remove tags on selected contacts', function () {
    Event::fake([SubscriberLifecycleOccurred::class]);
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $selected = Subscriber::factory()->for(Audience::factory()->for($team))->create();
    $contact = $selected->contact;
    $kept = Contact::factory()->for($team)->create();
    $vip = Tag::factory()->for($team)->create(['name' => 'VIP']);

    $this->actingAs($user)
        ->patch(route('contacts.bulk-tags', $team), [
            'ids' => [$contact->uuid],
            'action' => 'add',
            'tags' => ['vip', 'Donor'],
        ])
        ->assertRedirect();

    expect($contact->tags()->orderBy('name')->pluck('name')->all())->toBe(['Donor', 'VIP'])
        ->and($kept->tags()->count())->toBe(0);
    Event::assertDispatched(SubscriberLifecycleOccurred::class, fn (SubscriberLifecycleOccurred $event): bool => $event->trigger === AutomationTrigger::Tagged
        && $event->subscriber->is($selected)
        && $event->context === ['tag_uuid' => $vip->uuid]);

    $this->actingAs($user)
        ->patch(route('contacts.bulk-tags', $team), [
            'ids' => [$contact->uuid],
            'action' => 'remove',
            'tags' => ['VIP'],
        ])
        ->assertRedirect();

    expect($contact->tags()->pluck('name')->all())->toBe(['Donor'])
        ->and($team->tags()->where('name', 'VIP')->exists())->toBeTrue();
});

test('bulk contact tags stay in the current team and need contact management', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $foreign = Contact::factory()->create();
    $contact = Contact::factory()->for($team)->create();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);

    $this->actingAs($owner)
        ->patch(route('contacts.bulk-tags', $team), ['ids' => [$foreign->uuid], 'action' => 'add', 'tags' => ['VIP']])
        ->assertSessionHasErrors('ids.0');

    $this->actingAs($member)
        ->patch(route('contacts.bulk-tags', $team), ['ids' => [$contact->uuid], 'action' => 'add', 'tags' => ['VIP']])
        ->assertForbidden();

    expect($foreign->tags()->count())->toBe(0)
        ->and($contact->tags()->count())->toBe(0);
});
