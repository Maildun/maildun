<?php

use App\Enums\AudienceAttributeType;
use App\Enums\SubscribeFormFieldMode;
use App\Enums\SubscriberLanguage;
use App\Enums\TeamRole;
use App\Jobs\MoveAudienceAttributeValues;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSender;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('team owners can create rename and delete audience attributes', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->from(route('audiences.settings.attributes', [$team, $audience]))
        ->post(route('audiences.attributes.store', [$team, $audience]), [
            'name' => 'Company',
            'type' => AudienceAttributeType::Text->value,
            'required' => true,
        ])
        ->assertRedirect();

    $attribute = $audience->audienceAttributes()->firstOrFail();

    expect($attribute->name)->toBe('Company')
        ->and($attribute->key)->toBe('company')
        ->and($attribute->type)->toBe(AudienceAttributeType::Text)
        ->and($attribute->required)->toBeTrue()
        ->and($attribute->position)->toBe(1);

    $this->actingAs($user)
        ->patch(route('audiences.attributes.update', [$team, $audience, $attribute]), [
            'name' => 'Company name',
            'key' => 'company_name',
            'type' => AudienceAttributeType::Text->value,
            'required' => false,
        ])
        ->assertRedirect();

    expect($attribute->fresh()->name)->toBe('Company name')
        ->and($attribute->fresh()->key)->toBe('company_name')
        ->and($attribute->fresh()->required)->toBeFalse();

    $this->actingAs($user)
        ->delete(route('audiences.attributes.destroy', [$team, $audience, $attribute]))
        ->assertRedirect();

    $this->assertModelMissing($attribute);
});

test('renaming an attribute key moves the stored subscriber values to it', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $other = Audience::factory()->for($team)->create();
    $attribute = AudienceAttribute::factory()->for($audience)->create(['name' => 'Company', 'key' => 'company']);
    $moved = Subscriber::factory()->for($audience)->create(['attribute_values' => ['company' => 'Acme', 'plan' => 'pro']]);
    $newer = Subscriber::factory()->for($audience)->create(['attribute_values' => ['company' => 'Old', 'company_name' => 'New']]);
    $elsewhere = Subscriber::factory()->for($other)->create(['attribute_values' => ['company' => 'Untouched']]);

    $this->actingAs($user)
        ->patch(route('audiences.attributes.update', [$team, $audience, $attribute]), [
            'name' => 'Company name',
            'key' => 'company_name',
            'type' => AudienceAttributeType::Text->value,
            'required' => false,
        ])
        ->assertRedirect();

    expect($moved->fresh()->attribute_values)->toBe(['plan' => 'pro', 'company_name' => 'Acme'])
        ->and($newer->fresh()->attribute_values)->toBe(['company_name' => 'New'])
        ->and($elsewhere->fresh()->attribute_values)->toBe(['company' => 'Untouched']);
});

test('a rename that runs after a later one still lands on the final key', function () {
    $audience = Audience::factory()->create();
    $attribute = AudienceAttribute::factory()->for($audience)->create(['key' => 'company']);
    $subscriber = Subscriber::factory()->for($audience)->create(['attribute_values' => ['company' => 'Acme']]);

    // company -> organisation -> employer, with the second move running first.
    $attribute->update(['key' => 'employer']);
    (new MoveAudienceAttributeValues($audience->id, 'organisation', $attribute->id))->handle();
    (new MoveAudienceAttributeValues($audience->id, 'company', $attribute->id))->handle();

    expect($subscriber->fresh()->attribute_values)->toBe(['employer' => 'Acme']);
});

test('deleting an attribute removes its stored subscriber values', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $attribute = AudienceAttribute::factory()->for($audience)->create(['key' => 'company']);
    $subscriber = Subscriber::factory()->for($audience)->create(['attribute_values' => ['company' => 'Acme', 'plan' => 'pro']]);

    $this->actingAs($user)
        ->delete(route('audiences.attributes.destroy', [$team, $audience, $attribute]))
        ->assertRedirect();

    expect($subscriber->fresh()->attribute_values)->toBe(['plan' => 'pro']);
});

test('an attribute cannot become a date while members hold values that are not dates', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $attribute = AudienceAttribute::factory()->for($audience)->create([
        'name' => 'Tier',
        'key' => 'tier',
        'type' => AudienceAttributeType::Text,
    ]);
    Subscriber::factory()->for($audience)->create(['attribute_values' => ['tier' => 'VIP']]);

    $this->actingAs($user)
        ->patch(route('audiences.attributes.update', [$team, $audience, $attribute]), [
            'name' => 'Tier',
            'key' => 'tier',
            'type' => AudienceAttributeType::Date->value,
        ])
        ->assertSessionHasErrors([
            'type' => 'Some subscribers have values that are not a valid date. Fix those values before changing the type.',
        ]);

    expect($attribute->fresh()->type)->toBe(AudienceAttributeType::Text);
});

test('an attribute can become a date when every stored value is a date', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $attribute = AudienceAttribute::factory()->for($audience)->create([
        'name' => 'Renewal',
        'key' => 'renewal',
        'type' => AudienceAttributeType::Text,
    ]);
    Subscriber::factory()->for($audience)->create(['attribute_values' => ['renewal' => '2026-12-31']]);

    $this->actingAs($user)
        ->patch(route('audiences.attributes.update', [$team, $audience, $attribute]), [
            'name' => 'Renewal',
            'key' => 'renewal',
            'type' => AudienceAttributeType::Date->value,
        ])
        ->assertSessionHasNoErrors();

    expect($attribute->fresh()->type)->toBe(AudienceAttributeType::Date);
});

test('team owners can configure the main subscriber fields in attribute settings', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->patch(route('audiences.update', [$team, $audience]), [
            'first_name_mode' => SubscribeFormFieldMode::Required->value,
            'last_name_mode' => SubscribeFormFieldMode::Hidden->value,
            'language_mode' => SubscribeFormFieldMode::Optional->value,
            'allowed_languages' => [
                SubscriberLanguage::English->value,
                SubscriberLanguage::Indonesian->value,
            ],
            'default_language' => SubscriberLanguage::English->value,
        ])
        ->assertRedirect();

    expect($audience->fresh()->first_name_mode)->toBe(SubscribeFormFieldMode::Required)
        ->and($audience->fresh()->last_name_mode)->toBe(SubscribeFormFieldMode::Hidden)
        ->and($audience->fresh()->language_mode)->toBe(SubscribeFormFieldMode::Optional)
        ->and($audience->fresh()->allowed_languages)->toBe(['en', 'id'])
        ->and($audience->fresh()->default_language)->toBe(SubscriberLanguage::English);
});

test('visible language fields require allowed choices and a default from that list', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->patch(route('audiences.update', [$team, $audience]), [
            'language_mode' => SubscribeFormFieldMode::Required->value,
            'allowed_languages' => [],
            'default_language' => null,
        ])
        ->assertInvalid(['allowed_languages', 'default_language']);

    $this->actingAs($user)
        ->patch(route('audiences.update', [$team, $audience]), [
            'language_mode' => SubscribeFormFieldMode::Optional->value,
            'allowed_languages' => [SubscriberLanguage::English->value],
            'default_language' => SubscriberLanguage::Indonesian->value,
        ])
        ->assertInvalid('default_language');
});

test('attribute keys are generated from the name and stay unique in the audience', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    AudienceAttribute::factory()->for($audience)->create([
        'name' => 'Company',
        'key' => 'company',
    ]);

    $this->actingAs($user)
        ->post(route('audiences.attributes.store', [$team, $audience]), [
            'name' => 'Company',
            'type' => AudienceAttributeType::Text->value,
        ])
        ->assertInvalid('name');

    $this->actingAs($user)
        ->post(route('audiences.attributes.store', [$team, $audience]), [
            'name' => 'Workplace',
            'key' => 'company',
            'type' => AudienceAttributeType::Text->value,
        ])
        ->assertInvalid('key');
});

test('reserved subscriber fields cannot be used as attribute keys', function (string $name) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('audiences.attributes.store', [$team, $audience]), [
            'name' => $name,
            'type' => AudienceAttributeType::Text->value,
        ])
        ->assertInvalid('key');
})->with(['Email', 'Language']);

test('built-in merge tag names cannot be used as attribute keys', function (string $name) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('audiences.attributes.store', [$team, $audience]), [
            'name' => $name,
            'type' => AudienceAttributeType::Text->value,
        ])
        ->assertInvalid(['key' => 'That key is reserved for a built-in subscriber field or merge tag.']);

    expect($audience->audienceAttributes()->count())->toBe(0);
})->with(['Year', 'Unsubscribe URL', 'Web view URL']);

test('deleting an attribute removes its stored values from members', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $attribute = AudienceAttribute::factory()->for($audience)->create(['name' => 'Company', 'key' => 'company']);
    $member = Subscriber::factory()->for($audience)->create([
        'attribute_values' => ['company' => 'Acme', 'city' => 'Paris'],
    ]);
    $otherAudienceMember = Subscriber::factory()->for(Audience::factory()->for($team))->create([
        'attribute_values' => ['company' => 'Globex'],
    ]);

    $this->actingAs($user)
        ->delete(route('audiences.attributes.destroy', [$team, $audience, $attribute]))
        ->assertRedirect();

    $this->assertModelMissing($attribute);
    expect($member->fresh()->attribute_values)->toBe(['city' => 'Paris']);
    expect($otherAudienceMember->fresh()->attribute_values)->toBe(['company' => 'Globex']);
});

test('members cannot manage audience attributes', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $audience = Audience::factory()->for($team)->create();
    $attribute = AudienceAttribute::factory()->for($audience)->create();

    $this->actingAs($member)
        ->post(route('audiences.attributes.store', [$team, $audience]), [
            'name' => 'Blocked',
            'type' => AudienceAttributeType::Text->value,
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->patch(route('audiences.attributes.update', [$team, $audience, $attribute]), [
            'name' => 'Blocked',
            'key' => $attribute->key,
            'type' => $attribute->type->value,
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('audiences.attributes.destroy', [$team, $audience, $attribute]))
        ->assertForbidden();
});

test('attributes are isolated between audiences', function () {
    $user = User::factory()->create();
    $otherOwner = User::factory()->create();
    $audience = Audience::factory()->for($user->currentTeam)->create();
    $foreign = AudienceAttribute::factory()->for(Audience::factory()->for($otherOwner->currentTeam))->create();

    $this->actingAs($user)
        ->patch(route('audiences.attributes.update', [$user->currentTeam, $audience, $foreign]), [
            'name' => 'Stolen',
            'key' => 'stolen',
            'type' => AudienceAttributeType::Text->value,
        ])
        ->assertNotFound();
});

test('attribute and sender settings pages receive their own props', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->withoutSender()->create();
    $team->update([
        'email_from_name' => 'Maildun HQ',
        'email_from_address' => 'hq@example.com',
        'email_reply_to' => 'replies@example.com',
    ]);
    $audience = Audience::factory()->for($team)->create();
    $sender = TeamSender::factory()->for($team)->create([
        'name' => 'Maildun News',
        'email' => 'news@example.com',
    ]);
    TeamSender::factory()->for($team)->pending()->create();
    $attribute = AudienceAttribute::factory()->for($audience)->create([
        'name' => 'Company',
        'key' => 'company',
        'type' => AudienceAttributeType::Text,
        'required' => true,
    ]);

    $this->actingAs($user)
        ->get(route('audiences.settings.attributes', [$team, $audience]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audiences/settings/attributes')
            ->where('audience.first_name_mode', 'optional')
            ->where('audience.last_name_mode', 'optional')
            ->where('audience.language_mode', 'hidden')
            ->where('audience.allowed_languages', [])
            ->where('audience.default_language', null)
            ->where('languages.3.value', 'en')
            ->where('languages.7.value', 'id')
            ->has('attributes', 1)
            ->where('attributes.0.uuid', $attribute->uuid)
            ->where('attributes.0.key', 'company')
            ->where('attributes.0.required', true)
            ->has('attributeTypes', 3));

    $this->actingAs($user)
        ->get(route('audiences.settings.sender', [$team, $audience]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audiences/settings/sender')
            ->where('senderFallbacks.from_name', 'Maildun HQ')
            ->where('senderFallbacks.from_address', 'hq@example.com')
            ->where('senderFallbacks.reply_to', 'replies@example.com')
            ->where('selectedSenderUuid', 'workspace-default')
            ->has('senders', 1)
            ->where('senders.0.uuid', $sender->uuid)
            ->where('senders.0.name', 'Maildun News')
            ->where('senders.0.email', 'news@example.com'));
});
