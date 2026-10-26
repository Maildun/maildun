<?php

use App\Actions\Audiences\ApplySegmentRules;
use App\Enums\SegmentMatchType;
use App\Mcp\Servers\MaildunServer;
use App\Mcp\Tools\Contacts\ListContactsTool;
use App\Models\Audience;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Inertia\Testing\AssertableInertia as Assert;

test('name rules ignore the case of accented letters', function (string $operator, string $value) {
    $audience = Audience::factory()->create();
    Subscriber::factory()->for($audience)->create(['email' => 'elodie@example.com', 'first_name' => 'Élodie']);
    Subscriber::factory()->for($audience)->create(['email' => 'other@example.com', 'first_name' => 'Marc']);

    $matches = app(ApplySegmentRules::class)
        ->handle($audience, $audience->subscribers()->getQuery(), [
            ['field' => 'first_name', 'operator' => $operator, 'value' => $value],
        ], SegmentMatchType::All)
        ->pluck('email')
        ->all();

    expect($matches)->toBe(['elodie@example.com']);
})->with([
    'equals' => ['equals', 'élodie'],
    'contains' => ['contains', 'ÉLO'],
]);

test('contact search ignores the case of accented letters', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $match = Contact::factory()->for($team)->create(['first_name' => 'Ørjan', 'email' => 'o@example.com']);
    Contact::factory()->for($team)->create(['first_name' => 'Marc', 'email' => 'm@example.com']);

    $this->actingAs($user)
        ->get(route('contacts.index', ['current_team' => $team, 'search' => 'ørjan']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('contacts.data', 1)
            ->where('contacts.data.0.uuid', $match->uuid));
});

test('company search ignores the case of accented letters', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $match = Company::factory()->for($team)->create(['name' => 'Éclair Studio']);
    Company::factory()->for($team)->create(['name' => 'Northwind']);

    $this->actingAs($user)
        ->get(route('companies.index', ['current_team' => $team, 'search' => 'éclair']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('companies.data', 1)
            ->where('companies.data.0.uuid', $match->uuid));
});

test('the MCP contact tag filter ignores the case of accented letters', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $tag = Tag::factory()->for($team)->create(['name' => 'Été']);
    $match = Contact::factory()->for($team)->create();
    $match->tags()->attach($tag);
    Contact::factory()->for($team)->create();

    MaildunServer::tool(ListContactsTool::class, [
        'workspace' => $team->slug,
        'tag' => 'été',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('count', 1)
        ->where('contacts.0.uuid', $match->uuid)
        ->etc());
});
