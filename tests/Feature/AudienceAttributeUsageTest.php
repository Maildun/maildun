<?php

use App\Enums\AudienceAttributeType;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\SubscribeForm;
use App\Models\Subscriber;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('team owners can create an attribute of each type', function (AudienceAttributeType $type) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->from(route('audiences.settings.attributes', [$team, $audience]))
        ->post(route('audiences.attributes.store', [$team, $audience]), [
            'name' => 'Renewal value',
            'type' => $type->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('audiences.settings.attributes', [$team, $audience]));

    $attribute = $audience->audienceAttributes()->sole();

    expect($attribute->type)->toBe($type)
        ->and($attribute->key)->toBe('renewal_value')
        ->and($attribute->required)->toBeFalse();
})->with(AudienceAttributeType::cases());

test('creating an attribute rejects an unknown type', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('audiences.attributes.store', [$team, $audience]), [
            'name' => 'Favourite colour',
            'type' => 'color',
        ])
        ->assertSessionHasErrors('type');

    expect($audience->audienceAttributes()->exists())->toBeFalse();
});

test('an attribute cannot become a number while members hold values that are not numbers', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $attribute = AudienceAttribute::factory()->for($audience)->create(['name' => 'Seats', 'key' => 'seats']);
    Subscriber::factory()->for($audience)->create(['attribute_values' => ['seats' => 'a few']]);

    $this->actingAs($user)
        ->patch(route('audiences.attributes.update', [$team, $audience, $attribute]), [
            'name' => 'Seats',
            'key' => 'seats',
            'type' => AudienceAttributeType::Number->value,
        ])
        ->assertSessionHasErrors([
            'type' => 'Some subscribers have values that are not a valid number. Fix those values before changing the type.',
        ]);

    expect($attribute->fresh()->type)->toBe(AudienceAttributeType::Text);
});

test('reordered attributes appear in the new order on subscribers, forms and segments', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $company = AudienceAttribute::factory()->for($audience)->create(['name' => 'Company', 'key' => 'company']);
    $seats = AudienceAttribute::factory()->number()->for($audience)->create(['name' => 'Seats', 'key' => 'seats']);
    $renewal = AudienceAttribute::factory()->date()->for($audience)->create(['name' => 'Renewal', 'key' => 'renewal']);
    $subscriber = Subscriber::factory()->for($audience)->create([
        'attribute_values' => ['company' => 'Acme', 'seats' => 12, 'renewal' => '2026-12-31'],
    ]);
    $subscribeForm = SubscribeForm::factory()->for($audience)->published()->create();

    $this->actingAs($user)
        ->patch(route('audiences.attributes.reorder', [$team, $audience]), [
            'attributes' => [$renewal->uuid, $company->uuid, $seats->uuid],
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get(route('contacts.show', [
            'current_team' => $team,
            'contact' => $subscriber->fresh()->contact,
            'audience' => $audience->uuid,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('contact.memberships.0.attributes.0.key', 'renewal')
            ->where('contact.memberships.0.attributes.0.type', 'date')
            ->where('contact.memberships.0.attributes.0.value', '2026-12-31')
            ->where('contact.memberships.0.attributes.1.key', 'company')
            ->where('contact.memberships.0.attributes.1.value', 'Acme')
            ->where('contact.memberships.0.attributes.2.key', 'seats')
            ->where('contact.memberships.0.attributes.2.value', 12));

    $this->actingAs($user)
        ->get(route('audiences.subscribe_forms.edit', [$team, $audience, $subscribeForm]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('attributes.0.key', 'renewal')
            ->where('attributes.1.key', 'company')
            ->where('attributes.2.key', 'seats'));

    $this->get(route('public.subscribe_forms.show', $subscribeForm))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscribeForm.attributes.0.key', 'renewal')
            ->where('subscribeForm.attributes.1.key', 'company')
            ->where('subscribeForm.attributes.2.key', 'seats'));

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('segmentRuleOptions.attributes', [
                ['uuid' => $renewal->uuid, 'name' => 'Renewal', 'type' => 'date'],
                ['uuid' => $company->uuid, 'name' => 'Company', 'type' => 'text'],
                ['uuid' => $seats->uuid, 'name' => 'Seats', 'type' => 'number'],
            ]));
});

test('a deleted attribute no longer appears on subscribers, forms or segments', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $attribute = AudienceAttribute::factory()->for($audience)->create(['name' => 'Company', 'key' => 'company']);
    $subscriber = Subscriber::factory()->for($audience)->create(['attribute_values' => ['company' => 'Acme']]);
    $subscribeForm = SubscribeForm::factory()->for($audience)->published()->create();

    $this->actingAs($user)
        ->delete(route('audiences.attributes.destroy', [$team, $audience, $attribute]))
        ->assertRedirect();

    $this->actingAs($user)
        ->get(route('contacts.show', [
            'current_team' => $team,
            'contact' => $subscriber->fresh()->contact,
            'audience' => $audience->uuid,
        ]))
        ->assertInertia(fn (Assert $page) => $page->has('contact.memberships.0.attributes', 0));

    $this->actingAs($user)
        ->get(route('audiences.subscribe_forms.edit', [$team, $audience, $subscribeForm]))
        ->assertInertia(fn (Assert $page) => $page->has('attributes', 0));

    $this->get(route('public.subscribe_forms.show', $subscribeForm))
        ->assertInertia(fn (Assert $page) => $page->has('subscribeForm.attributes', 0));

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience]))
        ->assertInertia(fn (Assert $page) => $page->where('segmentRuleOptions.attributes', []));
});
