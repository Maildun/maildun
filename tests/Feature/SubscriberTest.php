<?php

use App\Enums\EmailDeliveryStatus;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Enums\TeamRole;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Contact;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkspaceRole;
use App\Services\DiceBearAvatarGenerator;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

test('subscribers receive a stable local micah avatar', function () {
    $subscriber = Subscriber::factory()->create();

    $expectedAvatar = URL::signedRoute('avatars.show', [
        'style' => DiceBearAvatarGenerator::MICAH,
        'seed' => $subscriber->uuid,
    ], absolute: false);

    expect($subscriber->avatar)
        ->toBe($expectedAvatar)
        ->not->toContain('api.dicebear.com')
        ->and($subscriber->fresh()->avatar)->toBe($expectedAvatar)
        ->and($subscriber->toArray()['avatar'])->toBe($expectedAvatar);
});

test('owners can manually add normalized consented subscribers', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('audiences.subscribers.store', [$team, $audience]), [
            'email' => '  Person@Example.COM ',
            'first_name' => 'Taylor',
            'last_name' => 'Otwell',
            'consent_confirmed' => true,
        ])
        ->assertRedirect();

    $subscriber = $audience->subscribers()->firstOrFail();

    expect($subscriber->email)->toBe('person@example.com')
        ->and($subscriber->status)->toBe(SubscriberStatus::Subscribed)
        ->and($subscriber->consented_at)->not->toBeNull()
        ->and($subscriber->subscribed_at)->not->toBeNull();
});

test('manual subscribers require consent and are unique within an audience', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->create(['email' => 'person@example.com']);

    $this->actingAs($user)
        ->post(route('audiences.subscribers.store', [$team, $audience]), [
            'email' => 'new@example.com',
        ])
        ->assertInvalid('consent_confirmed');

    $this->actingAs($user)
        ->post(route('audiences.subscribers.store', [$team, $audience]), [
            'email' => 'PERSON@example.com',
            'consent_confirmed' => true,
        ])
        ->assertInvalid('email');
});

test('owners can update unsubscribe resubscribe and delete subscribers', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();

    $this->actingAs($user)
        ->patch(route('audiences.subscribers.update', [$team, $audience, $subscriber]), [
            'email' => 'updated@example.com',
            'first_name' => 'Updated',
        ])
        ->assertRedirect();

    expect($subscriber->fresh()->email)->toBe('updated@example.com');

    // The edit dialog always submits `consent_confirmed` as false; consent is
    // create-only and must not block an update.
    $this->actingAs($user)
        ->put(route('audiences.subscribers.update', [$team, $audience, $subscriber]), [
            'email' => 'updated-again@example.com',
            'first_name' => 'Updated',
            'last_name' => '',
            'tags' => [],
            'consent_confirmed' => false,
        ])
        ->assertValid()
        ->assertRedirect();

    expect($subscriber->fresh()->email)->toBe('updated-again@example.com');

    $this->actingAs($user)
        ->patch(route('audiences.subscribers.unsubscribe', [$team, $audience, $subscriber]))
        ->assertRedirect();

    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($subscriber->fresh()->unsubscribed_at)->not->toBeNull();

    $this->actingAs($user)
        ->patch(route('audiences.subscribers.resubscribe', [$team, $audience, $subscriber]))
        ->assertInvalid('consent_confirmed');

    $this->actingAs($user)
        ->patch(route('audiences.subscribers.resubscribe', [$team, $audience, $subscriber]), [
            'consent_confirmed' => true,
        ])
        ->assertRedirect();

    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Subscribed)
        ->and($subscriber->fresh()->unsubscribed_at)->toBeNull()
        ->and($subscriber->fresh()->consent_text)->toBe('Marketing consent confirmed by a team member.')
        ->and($subscriber->fresh()->consented_at)->not->toBeNull();

    $this->actingAs($user)
        ->delete(route('audiences.subscribers.destroy', [$team, $audience, $subscriber]))
        ->assertRedirect();

    $this->assertModelMissing($subscriber);
});

test('a subscriber email cannot be changed to another team contact email', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $currentContact = Contact::factory()->for($team)->create([
        'email' => 'current@example.com',
        'first_name' => 'Current',
    ]);
    $otherContact = Contact::factory()->for($team)->create([
        'email' => 'owned@example.com',
        'first_name' => 'Owned',
    ]);
    $subscriber = Subscriber::factory()->for($audience)->create([
        'email' => $currentContact->email,
        'first_name' => $currentContact->first_name,
        'last_name' => $currentContact->last_name,
    ]);

    $this->actingAs($user)
        ->put(route('audiences.subscribers.update', [$team, $audience, $subscriber]), [
            'email' => $otherContact->email,
            'first_name' => 'Replacement',
            'last_name' => '',
            'tags' => [],
        ])
        ->assertInvalid([
            'email' => 'This email is already used by another contact.',
        ]);

    expect($subscriber->fresh()->email)->toBe('current@example.com')
        ->and($subscriber->fresh()->contact_id)->toBe($currentContact->id)
        ->and($currentContact->fresh()->first_name)->toBe('Current')
        ->and($otherContact->fresh()->first_name)->toBe('Owned');
});

test('subscriber nested bindings and permissions prevent cross audience changes', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $viewerRole = WorkspaceRole::create([
        'team_id' => $team->id,
        'name' => 'viewer',
        'label' => 'Viewer',
        'guard_name' => 'web',
    ]);
    $team->memberships()
        ->where('user_id', $member->id)
        ->sole()
        ->update(['role' => $viewerRole->name]);
    $audience = Audience::factory()->for($team)->create();
    $otherAudience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();

    $this->actingAs($owner)
        ->patch(route('audiences.subscribers.unsubscribe', [$team, $otherAudience, $subscriber]))
        ->assertNotFound();

    $this->actingAs($member)
        ->patch(route('audiences.subscribers.unsubscribe', [$team, $audience, $subscriber]))
        ->assertForbidden();
});

test('owners can bulk unsubscribe and delete selected subscribers', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $keep = Subscriber::factory()->for($audience)->create();
    $unsubscribe = Subscriber::factory()->for($audience)->create();
    $delete = Subscriber::factory()->for($audience)->create();
    $alreadyUnsubscribed = Subscriber::factory()->for($audience)->unsubscribed()->create();

    $this->actingAs($user)
        ->patch(route('audiences.subscribers.bulk-unsubscribe', [$team, $audience]), [
            'ids' => [$unsubscribe->uuid, $alreadyUnsubscribed->uuid],
        ])
        ->assertRedirect();

    expect($unsubscribe->fresh()->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($unsubscribe->fresh()->unsubscribed_at)->not->toBeNull()
        ->and($alreadyUnsubscribed->fresh()->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($keep->fresh()->status)->toBe(SubscriberStatus::Subscribed);

    $this->actingAs($user)
        ->delete(route('audiences.subscribers.bulk-destroy', [$team, $audience]), [
            'ids' => [$delete->uuid, $alreadyUnsubscribed->uuid],
        ])
        ->assertRedirect();

    $this->assertModelMissing($delete);
    $this->assertModelMissing($alreadyUnsubscribed);
    $this->assertModelExists($keep);
    $this->assertModelExists($unsubscribe);
});

test('bulk subscriber actions stay scoped to the audience and managers', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $viewerRole = WorkspaceRole::create([
        'team_id' => $team->id,
        'name' => 'viewer',
        'label' => 'Viewer',
        'guard_name' => 'web',
    ]);
    $team->memberships()
        ->where('user_id', $member->id)
        ->sole()
        ->update(['role' => $viewerRole->name]);
    $audience = Audience::factory()->for($team)->create();
    $otherAudience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $foreign = Subscriber::factory()->for($otherAudience)->create();

    $this->actingAs($member)
        ->patch(route('audiences.subscribers.bulk-unsubscribe', [$team, $audience]), [
            'ids' => [$subscriber->uuid],
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->delete(route('audiences.subscribers.bulk-destroy', [$team, $audience]), [
            'ids' => [$foreign->uuid],
        ])
        ->assertInvalid('ids.0');

    $this->assertModelExists($subscriber);
    $this->assertModelExists($foreign);
});

test('subscriber details redirect to the selected contact membership with activity and automation history', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $attribute = AudienceAttribute::factory()->for($audience)->create([
        'name' => 'Company',
        'key' => 'company',
    ]);
    $subscriber = Subscriber::factory()->for($audience)->create([
        'first_name' => 'Taylor',
        'last_name' => 'Otwell',
        'attribute_values' => ['company' => 'Laravel'],
        'consent_ip' => '203.0.113.10',
    ]);
    $contact = $subscriber->fresh()->contact;
    $tag = Tag::factory()->for($team)->create(['name' => 'VIP']);
    $subscriber->tags()->attach($tag);
    $segment = Segment::factory()->for($audience)->create(['name' => 'Founders']);
    $segment->subscribers()->attach($subscriber);
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'name' => 'March newsletter',
        'subject' => 'Hello from Maildun',
    ]);
    EmailDelivery::factory()
        ->for($email)
        ->for($subscriber)
        ->for($contact)
        ->create([
            'email_address' => $subscriber->email,
            'status' => EmailDeliveryStatus::Delivered,
            'opens_count' => 2,
            'clicks_count' => 1,
            'sent_at' => now()->subDay(),
            'delivered_at' => now()->subDay(),
            'last_opened_at' => now()->subHour(),
            'last_clicked_at' => now()->subMinutes(30),
        ]);
    $automation = Automation::factory()->for($team)->create(['name' => 'Welcome series']);
    AutomationRun::factory()->for($automation)->for($subscriber)->create();

    $this->actingAs($user)
        ->get(route('audiences.subscribers.show', [$team, $audience, $subscriber]))
        ->assertRedirect(route('contacts.show', [
            'current_team' => $team,
            'contact' => $contact,
            'audience' => $audience->uuid,
        ]));

    $this->actingAs($user)
        ->get(route('contacts.show', [
            'current_team' => $team,
            'contact' => $contact,
            'audience' => $audience->uuid,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/show')
            ->where('canManage', true)
            ->where('selectedAudienceUuid', $audience->uuid)
            ->where('contact.uuid', $contact->uuid)
            ->where('contact.memberships.0.uuid', $subscriber->uuid)
            ->where('contact.memberships.0.consent_ip', '203.0.113.10')
            ->where('contact.memberships.0.attributes.0.uuid', $attribute->uuid)
            ->where('contact.memberships.0.attributes.0.value', 'Laravel')
            ->where('contact.memberships.0.segments.0.uuid', $segment->uuid)
            ->where('activity.received', 1)
            ->where('activity.opened', 1)
            ->where('activity.clicked', 1)
            ->where('contact.deliveries.0.campaign.name', 'March newsletter')
            ->where('contact.deliveries.0.opens', 2)
            ->has('automations', 1)
            ->where('automations.0.name', 'Welcome series'));
});

test('subscriber detail links retain the current audience context', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $contact = $subscriber->fresh()->contact;

    $this->actingAs($user)
        ->get(route('audiences.subscribers.show', [
            $team,
            $audience,
            $subscriber,
        ]))
        ->assertRedirect(route('contacts.show', [
            'current_team' => $team,
            'contact' => $contact,
            'audience' => $audience->uuid,
        ]));
});

test('members can follow subscriber detail links to the contact profile', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $contact = $subscriber->fresh()->contact;

    $this->actingAs($member)
        ->get(route('audiences.subscribers.show', [$team, $audience, $subscriber]))
        ->assertRedirect(route('contacts.show', [
            'current_team' => $team,
            'contact' => $contact,
            'audience' => $audience->uuid,
        ]));

    $this->actingAs($member)
        ->get(route('contacts.show', [$team, $contact]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('contacts/show'));
});

test('subscriber profile bindings stay scoped to the audience', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $otherAudience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();

    $this->actingAs($user)
        ->get(route('audiences.subscribers.show', [$team, $otherAudience, $subscriber]))
        ->assertNotFound();
});

test('deleting a subscriber from the profile returns to the audience', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();

    $this->actingAs($user)
        ->from(route('audiences.subscribers.show', [$team, $audience, $subscriber]))
        ->delete(route('audiences.subscribers.destroy', [$team, $audience, $subscriber]))
        ->assertRedirect(route('audiences.show', [$team, $audience]));

    $this->assertModelMissing($subscriber);
});

test('the subscriber list normalizes its filters and can be filtered by status and source', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->create(['email' => 'taylor@example.com']);
    Subscriber::factory()->for($audience)->create([
        'email' => 'nuno@example.com',
        'source' => SubscriberSource::Form,
    ]);
    Subscriber::factory()->unsubscribed()->for($audience)->create(['email' => 'jess@example.com']);

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('subscribers.data', 3)
            ->where('filters.search', '')
            ->where('filters.status', 'all')
            ->where('filters.source', 'all'));

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience, 'search' => ' TAYLOR ', 'status' => 'subscribed']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('subscribers.data', 1)
            ->where('subscribers.data.0.email', 'taylor@example.com')
            ->where('filters.search', 'TAYLOR')
            ->where('filters.status', 'subscribed'));

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience, 'source' => SubscriberSource::Form->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('subscribers.data', 1)
            ->where('subscribers.data.0.email', 'nuno@example.com')
            ->where('filters.source', SubscriberSource::Form->value));

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience, 'status' => 'bounced', 'source' => 'import']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('subscribers.data', 3)
            ->where('filters.status', 'all')
            ->where('filters.source', 'all'));
});
