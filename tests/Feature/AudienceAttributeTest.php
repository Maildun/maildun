<?php

use App\Enums\AudienceAttributeType;
use App\Enums\SubscribeFormFieldMode;
use App\Enums\TeamRole;
use App\Models\Audience;
use App\Models\AudienceAttribute;
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

test('team owners can configure the main subscriber fields in attribute settings', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->patch(route('audiences.update', [$team, $audience]), [
            'first_name_mode' => SubscribeFormFieldMode::Required->value,
            'last_name_mode' => SubscribeFormFieldMode::Hidden->value,
        ])
        ->assertRedirect();

    expect($audience->fresh()->first_name_mode)->toBe(SubscribeFormFieldMode::Required)
        ->and($audience->fresh()->last_name_mode)->toBe(SubscribeFormFieldMode::Hidden);
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

test('reserved subscriber fields cannot be used as attribute keys', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('audiences.attributes.store', [$team, $audience]), [
            'name' => 'Email',
            'type' => AudienceAttributeType::Text->value,
        ])
        ->assertInvalid('key');
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
