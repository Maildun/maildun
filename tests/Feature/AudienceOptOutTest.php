<?php

use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Models\Audience;
use App\Models\AudienceOptOut;
use App\Models\Contact;
use App\Models\SubscribeForm;
use App\Models\Subscriber;
use App\Models\TeamApiKey;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('removing an unsubscribed member keeps the opt-out when a team member adds them back', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $member = Subscriber::factory()->for($audience)->unsubscribed(now()->subMonth())->create(['email' => 'ada@example.com']);
    $unsubscribedAt = $member->unsubscribed_at;
    Event::fake([SubscriberLifecycleOccurred::class]);

    $this->actingAs($user)->delete(route('audiences.subscribers.destroy', [$team, $audience, $member]))->assertRedirect();
    $this->actingAs($user)
        ->post(route('audiences.subscribers.store', [$team, $audience]), [
            'email' => 'ada@example.com',
            'consent_confirmed' => true,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'warning');

    $readded = $audience->subscribers()->sole();

    expect($readded->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($readded->unsubscribed_at?->equalTo($unsubscribedAt))->toBeTrue()
        ->and($readded->consented_at)->toBeNull()
        ->and(Subscriber::query()->sendableFor($team)->exists())->toBeFalse()
        ->and(AudienceOptOut::query()->exists())->toBeFalse();
    Event::assertNotDispatched(SubscriberLifecycleOccurred::class);
});

test('deleting an unsubscribed contact keeps the opt-out when the contact is created again', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $contact = Subscriber::factory()->for($audience)->unsubscribed()->create(['email' => 'ada@example.com'])->contact;

    $this->actingAs($user)
        ->delete(route('contacts.destroy', [$team, $contact]), ['confirmation' => 'ada@example.com'])
        ->assertRedirect();
    $this->actingAs($user)
        ->post(route('contacts.store', $team), [
            'email' => 'ada@example.com',
            'company_assignment_mode' => 'automatic',
            'company_uuid' => '',
            'audience_uuids' => [$audience->uuid],
            'consent_confirmed' => true,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'warning');

    expect(Contact::query()->whereKey($contact->id)->exists())->toBeFalse()
        ->and($audience->subscribers()->sole()->status)->toBe(SubscriberStatus::Unsubscribed);
});

test('a bulk deleted opt-out turns a public form signup into a re-consent request', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create(['double_opt_in' => false]);
    $form = SubscribeForm::factory()->for($audience)->published()->create();
    $member = Subscriber::factory()->for($audience)->unsubscribed()->create(['email' => 'ada@example.com']);
    Event::fake([SubscriberLifecycleOccurred::class]);

    $this->actingAs($user)
        ->delete(route('audiences.subscribers.bulk-destroy', [$team, $audience]), ['ids' => [$member->uuid]])
        ->assertRedirect();
    $this->postJson(route('public.subscribe_forms.store', $form), [
        'email' => 'ada@example.com',
        'consent' => true,
        'website' => '',
    ])->assertOk();

    $readded = $audience->subscribers()->sole();

    expect($readded->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($readded->isPendingResubscription())->toBeTrue();
    Event::assertNotDispatched(SubscriberLifecycleOccurred::class);
});

test('an api signup for a deleted opt-out stays unsubscribed', function () {
    $audience = Audience::factory()->create();
    Subscriber::factory()->for($audience)->unsubscribed()->create(['email' => 'ada@example.com'])->delete();
    $issued = TeamApiKey::issue($audience->team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), ['email' => 'ada@example.com'])
        ->assertOk()
        ->assertJsonPath('data.status', 'unsubscribed');

    expect($audience->subscribers()->sole()->status)->toBe(SubscriberStatus::Unsubscribed);
});

test('a kept opt-out applies only to its own audience and not to removed subscribed members', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $left = Audience::factory()->for($team)->create();
    $other = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($left)->unsubscribed()->create(['email' => 'ada@example.com'])->delete();
    Subscriber::factory()->for($other)->create(['email' => 'grace@example.com'])->delete();

    $this->actingAs($user)->post(route('audiences.subscribers.store', [$team, $other]), [
        'email' => 'ada@example.com',
        'consent_confirmed' => true,
    ]);
    $this->actingAs($user)->post(route('audiences.subscribers.store', [$team, $other]), [
        'email' => 'grace@example.com',
        'consent_confirmed' => true,
    ]);

    expect($other->subscribers()->optedIn()->pluck('email')->sort()->values()->all())->toBe(['ada@example.com', 'grace@example.com'])
        ->and(AudienceOptOut::query()->where('audience_id', $left->id)->exists())->toBeTrue();
});

test('a kept opt-out is never mailed even if a member row skips the restore', function () {
    $audience = Audience::factory()->create();
    $member = Subscriber::factory()->for($audience)->create(['email' => 'ada@example.com']);

    AudienceOptOut::query()->create(['audience_id' => $audience->id, 'email' => 'ada@example.com', 'unsubscribed_at' => now()]);

    expect(Subscriber::query()->whereKey($member->id)->optedIn()->exists())->toBeFalse()
        ->and(Subscriber::query()->whereKey($member->id)->sendableFor($audience->team)->exists())->toBeFalse();
});

test('a restored opt-out can be resubscribed with renewed consent', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->unsubscribed()->create(['email' => 'ada@example.com'])->delete();
    $this->actingAs($user)->post(route('audiences.subscribers.store', [$team, $audience]), [
        'email' => 'ada@example.com',
        'consent_confirmed' => true,
    ]);
    $restored = $audience->subscribers()->sole();

    $this->actingAs($user)
        ->patch(route('audiences.subscribers.resubscribe', [$team, $audience, $restored]), ['consent_confirmed' => true])
        ->assertRedirect();

    expect(Subscriber::query()->whereKey($restored->id)->sendableFor($team)->exists())->toBeTrue();
});
