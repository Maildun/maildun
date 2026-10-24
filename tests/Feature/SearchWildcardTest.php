<?php

use App\Actions\Audiences\ApplySegmentRules;
use App\Enums\SegmentMatchType;
use App\Mcp\Servers\MaildunServer;
use App\Mcp\Tools\Contacts\ListContactsTool;
use App\Models\Audience;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Email;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Inertia\Testing\AssertableInertia as Assert;

test('audience search matches percent signs literally', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $organic = Audience::factory()->for($team)->create(['name' => '100% organic']);
    Audience::factory()->for($team)->create(['name' => '1000 club']);

    $this->actingAs($user)
        ->get(route('audiences.index', ['current_team' => $team, 'search' => '100%']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('audiences.data', 1)
            ->where('audiences.data.0.uuid', $organic->uuid));
});

test('subscriber search matches underscores literally', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $match = Subscriber::factory()->for($audience)->create(['email' => 'a_b@example.com']);
    Subscriber::factory()->for($audience)->create(['email' => 'axb@example.com']);

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience, 'search' => 'a_b']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('subscribers.data', 1)
            ->where('subscribers.data.0.uuid', $match->uuid));
});

test('contact search matches underscores literally', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $match = Contact::factory()->for($team)->create(['email' => 'a_b@example.com']);
    Contact::factory()->for($team)->create(['email' => 'axb@example.com']);

    $this->actingAs($user)
        ->get(route('contacts.index', ['current_team' => $team, 'search' => 'a_b']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('contacts.data', 1)
            ->where('contacts.data.0.uuid', $match->uuid));
});

test('company search matches percent signs literally', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $match = Company::factory()->for($team)->create(['name' => '50% Labs']);
    Company::factory()->for($team)->create(['name' => '500 Labs']);

    $this->actingAs($user)
        ->get(route('companies.index', ['current_team' => $team, 'search' => '50%']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('companies.data', 1)
            ->where('companies.data.0.uuid', $match->uuid));
});

test('campaign search finds terms containing underscores', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $match = Email::factory()->for($team)->create(['name' => 'promo_june']);
    Email::factory()->for($team)->create(['name' => 'promoxjune']);

    $this->actingAs($user)
        ->get(route('emails.index', ['current_team' => $team, 'q' => 'promo_june']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('emails.data', 1)
            ->where('emails.data.0.uuid', $match->uuid));
});

test('segment contains rules match wildcard characters literally', function (string $operator, string $expectedEmail) {
    $audience = Audience::factory()->create();
    Subscriber::factory()->for($audience)->create(['email' => 'a_b@example.com']);
    Subscriber::factory()->for($audience)->create(['email' => 'axb@example.com']);

    $matches = app(ApplySegmentRules::class)
        ->handle($audience, $audience->subscribers()->getQuery(), [
            ['field' => 'email', 'operator' => $operator, 'value' => 'a_b'],
        ], SegmentMatchType::All)
        ->pluck('email')
        ->all();

    expect($matches)->toBe([$expectedEmail]);
})->with([
    'contains' => ['contains', 'a_b@example.com'],
    'does not contain' => ['does_not_contain', 'axb@example.com'],
]);

test('MCP contact search finds terms containing underscores', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $match = Contact::factory()->for($team)->create(['email' => 'a_b@example.com']);
    Contact::factory()->for($team)->create(['email' => 'axb@example.com']);

    MaildunServer::tool(ListContactsTool::class, [
        'workspace' => $team->slug,
        'search' => 'a_b',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('count', 1)
        ->where('contacts.0.uuid', $match->uuid)
        ->etc());
});
