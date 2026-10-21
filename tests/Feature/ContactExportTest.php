<?php

use App\Enums\SubscriberLanguage;
use App\Enums\SubscriberSource;
use App\Enums\TeamRole;
use App\Models\Audience;
use App\Models\AudienceAttribute;
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
        ->toContain('Email,"First name","Last name",Company,Tags,Audiences,Languages,"Created at"')
        ->toContain('included@example.com')
        ->toContain("'=Included")
        ->not->toContain('excluded@example.com')
        ->not->toContain('other-team@example.com');
});

test('contacts exports show each membership status and the contact languages', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $contact = Contact::factory()->for($team)->create(['email' => 'ada@example.com']);
    $newsletter = Audience::factory()->for($team)->create(['name' => 'Newsletter']);
    $beta = Audience::factory()->for($team)->create(['name' => 'Beta']);
    $events = Audience::factory()->for($team)->create(['name' => 'Events', 'double_opt_in' => true]);
    Subscriber::factory()->for($newsletter)->for($contact)->create(['email' => $contact->email, 'language' => SubscriberLanguage::French]);
    Subscriber::factory()->for($beta)->for($contact)->unsubscribed()->create(['email' => $contact->email, 'language' => SubscriberLanguage::English]);
    Subscriber::factory()->for($events)->for($contact)->pendingConfirmation()->create(['email' => $contact->email, 'language' => SubscriberLanguage::French]);

    $lines = explode("\n", trim($this->actingAs($user)
        ->get(route('contacts.exports.show', ['current_team' => $team, 'format' => 'csv']))
        ->streamedContent()));

    expect($lines[0])->toContain('Audiences,Languages,"Created at"')
        ->and($lines[1])->toContain('"Newsletter (subscribed), Beta (unsubscribed), Events (pending confirmation)","fr, en"');
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

test('audience exports include custom fields, consent and the opt-out date', function () {
    $user = User::factory()->create();
    $audience = Audience::factory()->for($user->currentTeam)->create();
    AudienceAttribute::factory()->for($audience)->create(['name' => 'Plan', 'key' => 'plan', 'position' => 1]);
    AudienceAttribute::factory()->for($audience)->create(['name' => 'Seats', 'key' => 'seats', 'position' => 2]);
    Subscriber::factory()->for($audience)->unsubscribed(now()->parse('2026-10-05 08:00:00'))->create([
        'email' => 'ada@example.com',
        'consent_text' => 'Yes, send me the newsletter.',
        'consented_at' => '2026-09-01 10:00:00',
        'consent_ip' => '203.0.113.9',
        'attribute_values' => ['plan' => 'pro', 'seats' => 5],
    ]);

    $lines = explode("\n", trim($this->actingAs($user)
        ->get(route('audiences.exports.show', ['current_team' => $user->currentTeam, 'audience' => $audience, 'format' => 'csv']))
        ->streamedContent()));

    expect($lines[0])->toEndWith('"Subscribed at","Unsubscribed at","Consent text","Consented at","Consent IP",plan,seats')
        ->and($lines[1])->toEndWith(',2026-10-05T08:00:00+00:00,"Yes, send me the newsletter.",2026-09-01T10:00:00+00:00,203.0.113.9,pro,5');
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

test('CSV exports neutralise a formula even when the cell holds invalid UTF-8', function () {
    $user = User::factory()->create();
    Contact::factory()->for($user->currentTeam)->create([
        'email' => 'visitor@example.com',
        'first_name' => '=HYPERLINK("https://evil.test/?'."\xff".'","Open")',
    ]);

    $content = $this->actingAs($user)
        ->get(route('contacts.exports.show', ['current_team' => $user->currentTeam, 'format' => 'csv']))
        ->streamedContent();

    expect($content)->toContain('visitor@example.com,"\'=HYPERLINK(""https://evil.test/?')
        ->and(mb_check_encoding($content, 'UTF-8'))->toBeTrue();
});

test('Excel exports stay well-formed when a name holds a control character', function () {
    $user = User::factory()->create();
    $audience = Audience::factory()->for($user->currentTeam)->create();
    Subscriber::factory()->for($audience)
        ->for(Contact::factory()->for($user->currentTeam)->state(['email' => 'visitor@example.com', 'first_name' => "Mal\x01lory"]))
        ->create(['email' => 'visitor@example.com', 'first_name' => "Mal\x01lory"]);

    $workbooks = collect([
        route('contacts.exports.show', ['current_team' => $user->currentTeam, 'format' => 'xls']),
        route('audiences.exports.show', ['current_team' => $user->currentTeam, 'audience' => $audience, 'format' => 'xls']),
    ])->map(fn (string $url): string => $this->actingAs($user)->get($url)->streamedContent());

    $workbooks->each(fn (string $content) => expect(simplexml_load_string($content))->not->toBeFalse()
        ->and($content)->toContain('Mallory'));
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

test('audience exports include and filter by subscriber language', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create(['name' => 'News']);
    Subscriber::factory()->for($audience)->create(['email' => 'budi@example.com', 'language' => SubscriberLanguage::Indonesian]);
    Subscriber::factory()->for($audience)->create(['email' => 'ada@example.com', 'language' => SubscriberLanguage::English]);

    $content = $this->actingAs($user)->get(route('audiences.exports.show', [
        'current_team' => $team,
        'audience' => $audience,
        'format' => 'csv',
        'language' => 'id',
    ]))->assertOk()->streamedContent();

    expect($content)
        ->toContain('Email,"First name","Last name",Status,Source,Language,Tags,"Subscribed at"')
        ->toContain('budi@example.com')
        ->toContain(',id,')
        ->not->toContain('ada@example.com');
});
