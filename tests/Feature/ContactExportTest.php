<?php

use App\Enums\SubscriberSource;
use App\Enums\TeamRole;
use App\Models\Audience;
use App\Models\Contact;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\User;

test('workspace contacts can be exported as a filtered CSV', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    Contact::factory()->for($team)->create([
        'email' => 'included@example.com',
        'first_name' => '=Included',
    ]);
    Contact::factory()->for($team)->create([
        'email' => 'excluded@example.com',
        'first_name' => 'Excluded',
    ]);
    Contact::factory()->for(Team::factory())->create([
        'email' => 'other-team@example.com',
    ]);

    $response = $this->actingAs($user)->get(route('contacts.exports.show', [
        'current_team' => $team,
        'format' => 'csv',
        'search' => 'included',
    ]));

    $response->assertOk()->assertDownload('contacts-'.now()->format('Y-m-d').'.csv');

    expect($response->streamedContent())
        ->toContain('Email,"First name","Last name",Company,Tags,Audiences,"Created at"')
        ->toContain('included@example.com')
        ->toContain("'=Included")
        ->not->toContain('excluded@example.com')
        ->not->toContain('other-team@example.com');
});

test('audience contacts can be exported with the active subscriber filters', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create(['name' => 'Product News']);
    Subscriber::factory()->for($audience)->create([
        'email' => 'included@example.com',
        'first_name' => 'Included',
        'source' => SubscriberSource::Api,
    ]);
    Subscriber::factory()->for($audience)->unsubscribed()->create([
        'email' => 'unsubscribed@example.com',
        'source' => SubscriberSource::Api,
    ]);
    Subscriber::factory()->for($audience)->create([
        'email' => 'manual@example.com',
        'source' => SubscriberSource::Manual,
    ]);

    $response = $this->actingAs($user)->get(route('audiences.exports.show', [
        'current_team' => $team,
        'audience' => $audience,
        'format' => 'csv',
        'search' => 'included',
        'status' => 'subscribed',
        'source' => 'api',
    ]));

    $response->assertOk()->assertDownload('product-news-contacts-'.now()->format('Y-m-d').'.csv');

    expect($response->streamedContent())
        ->toContain('included@example.com')
        ->not->toContain('unsubscribed@example.com')
        ->not->toContain('manual@example.com');
});

test('invalid audience filters fall back to exporting all contacts', function () {
    $user = User::factory()->create();
    $audience = Audience::factory()->for($user->currentTeam)->create();
    Subscriber::factory()->for($audience)->create([
        'email' => 'api@example.com',
        'source' => SubscriberSource::Api,
    ]);
    Subscriber::factory()->for($audience)->unsubscribed()->create([
        'email' => 'manual@example.com',
        'source' => SubscriberSource::Manual,
    ]);

    $response = $this->actingAs($user)->get(route('audiences.exports.show', [
        'current_team' => $user->currentTeam,
        'audience' => $audience,
        'format' => 'csv',
        'status' => 'invalid',
        'source' => 'invalid',
    ]));

    $response->assertOk();

    expect($response->streamedContent())
        ->toContain('api@example.com')
        ->toContain('manual@example.com');
});

test('workspace contacts can be exported as an Excel XML workbook', function () {
    $user = User::factory()->create();
    Contact::factory()->for($user->currentTeam)->create([
        'email' => 'excel@example.com',
        'first_name' => 'A & B',
    ]);

    $response = $this->actingAs($user)->get(route('contacts.exports.show', [
        'current_team' => $user->currentTeam,
        'format' => 'xls',
    ]));

    $response->assertOk()->assertDownload('contacts-'.now()->format('Y-m-d').'.xls');

    expect($response->headers->get('content-type'))
        ->toBe('application/vnd.ms-excel; charset=UTF-8')
        ->and($response->streamedContent())
        ->toContain('<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"')
        ->toContain('excel@example.com')
        ->toContain('A &amp; B');
});

test('audience exports cannot target another workspace audience', function () {
    $user = User::factory()->create();
    $otherAudience = Audience::factory()->for(Team::factory())->create();

    $this->actingAs($user)
        ->get(route('audiences.exports.show', [
            'current_team' => $user->currentTeam,
            'audience' => $otherAudience,
            'format' => 'csv',
        ]))
        ->assertNotFound();
});

test('members without contact management permission cannot export contacts', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);

    $this->actingAs($member)
        ->get(route('contacts.exports.show', [
            'current_team' => $team,
            'format' => 'csv',
        ]))
        ->assertForbidden();
});
