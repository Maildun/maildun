<?php

use App\Enums\EmailDeliveryStatus;
use App\Enums\SubscriberStatus;
use App\Enums\TeamRole;
use App\Models\Audience;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkspaceRole;
use Inertia\Testing\AssertableInertia as Assert;

test('editing a subscriber profile updates the shared contact and its other memberships', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $otherAudience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create(['email' => 'old@example.com', 'first_name' => 'Old']);
    $otherMembership = Subscriber::factory()->for($otherAudience)->create(['email' => 'old@example.com', 'first_name' => 'Old']);

    $this->actingAs($user)
        ->put(route('audiences.subscribers.update', [$team, $audience, $subscriber]), [
            'email' => 'New@Example.com',
            'first_name' => 'Taylor',
            'last_name' => 'Otwell',
            'tags' => [],
            'consent_confirmed' => false,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $contact = $subscriber->fresh()->contact;

    expect($contact->only(['email', 'first_name', 'last_name']))->toBe([
        'email' => 'new@example.com',
        'first_name' => 'Taylor',
        'last_name' => 'Otwell',
    ])
        ->and($otherMembership->fresh()->contact_id)->toBe($contact->id)
        ->and($otherMembership->fresh()->only(['email', 'first_name', 'last_name']))->toBe([
            'email' => 'new@example.com',
            'first_name' => 'Taylor',
            'last_name' => 'Otwell',
        ]);
});

test('the profile shows the membership as unsubscribed and then subscribed again with renewed consent', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create(['consent_text' => 'Joined at checkout']);
    $contact = $subscriber->fresh()->contact;

    $this->actingAs($user)
        ->patch(route('audiences.subscribers.unsubscribe', [$team, $audience, $subscriber]))
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Subscriber unsubscribed.');

    $this->actingAs($user)
        ->get(route('contacts.show', [$team, $contact]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('contact.memberships.0.status', SubscriberStatus::Unsubscribed->value)
            ->whereNot('contact.memberships.0.unsubscribed_at', null));

    $this->actingAs($user)
        ->patch(route('audiences.subscribers.resubscribe', [$team, $audience, $subscriber]), ['consent_confirmed' => true])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Subscriber resubscribed.');

    $this->actingAs($user)
        ->get(route('contacts.show', [$team, $contact]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('contact.memberships.0.status', SubscriberStatus::Subscribed->value)
            ->where('contact.memberships.0.unsubscribed_at', null)
            ->where('contact.memberships.0.consent_text', 'Marketing consent confirmed by a team member.'));
});

test('deleting a membership keeps the contact and its other audience memberships', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $otherAudience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create(['email' => 'shared@example.com']);
    $otherMembership = Subscriber::factory()->for($otherAudience)->create(['email' => 'shared@example.com']);
    $contact = $subscriber->fresh()->contact;

    $this->actingAs($user)
        ->delete(route('audiences.subscribers.destroy', [$team, $audience, $subscriber]))
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Subscriber permanently deleted.');

    $this->assertModelMissing($subscriber);
    $this->assertModelExists($contact);
    $this->assertModelExists($otherMembership);

    $this->actingAs($user)
        ->get(route('contacts.show', [$team, $contact]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('contact.memberships', 1)
            ->where('contact.memberships.0.uuid', $otherMembership->uuid));
});

test('view-only members cannot edit or delete a subscriber', function () {
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $viewerRole = WorkspaceRole::create([
        'team_id' => $team->id,
        'name' => 'viewer',
        'label' => 'Viewer',
        'guard_name' => 'web',
    ]);
    $team->memberships()->where('user_id', $member->id)->sole()->update(['role' => $viewerRole->name]);
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create(['email' => 'kept@example.com']);

    $this->actingAs($member)
        ->put(route('audiences.subscribers.update', [$team, $audience, $subscriber]), [
            'email' => 'changed@example.com',
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('audiences.subscribers.destroy', [$team, $audience, $subscriber]))
        ->assertForbidden();

    expect($subscriber->fresh()->email)->toBe('kept@example.com');
});

test('subscriber details are not reachable from another workspace', function () {
    $outsider = User::factory()->create();
    $team = Team::factory()->create();
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();

    $this->actingAs($outsider)
        ->get(route('audiences.subscribers.show', [$team, $audience, $subscriber]))
        ->assertForbidden();

    $this->actingAs($outsider)
        ->get(route('audiences.subscribers.show', [$outsider->currentTeam, $audience, $subscriber]))
        ->assertNotFound();
});

test('the history keeps emails and automations that were deleted without linking to them', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $contact = $subscriber->fresh()->contact;
    $email = Email::factory()->for($team)->create(['audience_id' => $audience->id, 'name' => 'Old newsletter']);
    EmailDelivery::factory()->for($email)->for($subscriber)->for($contact)->create([
        'email_address' => $subscriber->email,
        'status' => EmailDeliveryStatus::Delivered,
        'sent_at' => now()->subDay(),
    ]);
    $automation = Automation::factory()->for($team)->create(['name' => 'Old welcome']);
    AutomationRun::factory()->for($automation)->for($subscriber)->create();
    $email->delete();
    $automation->delete();

    $this->actingAs($user)
        ->get(route('contacts.show', [$team, $contact]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('contact.deliveries.0.campaign.name', 'Old newsletter')
            ->where('contact.deliveries.0.campaign.uuid', null)
            ->where('automations.0.name', 'Old welcome')
            ->where('automations.0.automation_uuid', null));
});
