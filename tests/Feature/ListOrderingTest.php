<?php

use App\Enums\SegmentMatchType;
use App\Models\Audience;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Contacts that sort by name ignoring case, with the nameless ones last.
 *
 * @return list<Contact>
 */
function contactsInNameOrder(array $attributes = []): array
{
    return [
        Contact::factory()->create([...$attributes, 'first_name' => 'Ada', 'last_name' => 'apple']),
        Contact::factory()->create([...$attributes, 'first_name' => 'Ben', 'last_name' => 'Banana']),
        Contact::factory()->create([...$attributes, 'first_name' => 'Cy', 'last_name' => null]),
        Contact::factory()->create([...$attributes, 'first_name' => null, 'last_name' => null]),
    ];
}

test('sorting contacts by name ignores case and puts nameless contacts last', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $contacts = contactsInNameOrder(['team_id' => $team->id]);
    $expected = array_map(fn (Contact $contact): string => $contact->uuid, $contacts);

    $this->actingAs($user)
        ->get(route('contacts.index', ['current_team' => $team, 'sort' => 'name']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('contacts.data', fn ($contacts): bool => collect($contacts)->pluck('uuid')->all() === $expected));
});

test('company contacts are listed by name ignoring case with nameless contacts last', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $company = Company::factory()->for($team)->create();
    $contacts = contactsInNameOrder(['team_id' => $team->id, 'company_id' => $company->id]);
    $expected = array_map(fn (Contact $contact): string => $contact->uuid, $contacts);

    $this->actingAs($user)
        ->get(route('companies.show', [$team, $company]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('company.contacts.data', fn ($rows): bool => collect($rows)->pluck('uuid')->all() === $expected));
});

test('audience and segment subscribers who joined in the same second list the newest first', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $this->freezeSecond();
    $subscribers = Subscriber::factory()->for($audience)->count(3)->create();
    $segment = Segment::factory()->for($audience)->create(['match_type' => SegmentMatchType::All, 'rules' => []]);
    $segment->subscribers()->sync($subscribers->modelKeys());
    $newest = $subscribers->sortByDesc('id')->first();

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience]))
        ->assertInertia(fn (Assert $page) => $page->where('subscribers.data.0.uuid', $newest->uuid));

    $this->actingAs($user)
        ->get(route('audiences.segments.show', [$team, $audience, $segment]))
        ->assertInertia(fn (Assert $page) => $page->where('subscribers.data.0.uuid', $newest->uuid));
});

test('company options are sorted ignoring capitalisation', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    Company::factory()->for($team)->create(['name' => 'Banana Corp']);
    Company::factory()->for($team)->create(['name' => 'apple Labs']);
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->get(route('contacts.index', $team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('companies.0.name', 'apple Labs')
            ->where('companies.1.name', 'Banana Corp'));

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience]))
        ->assertInertia(fn (Assert $page) => $page->where('companies.0.name', 'apple Labs'));
});

test('the contacts export follows the list sort', function (string $sort, array $expectedEmails) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    Contact::factory()->for($team)->create(['email' => 'first@example.com', 'last_name' => 'zulu']);
    Contact::factory()->for($team)->create(['email' => 'second@example.com', 'last_name' => 'Alpha']);
    Contact::factory()->for($team)->create(['email' => 'third@example.com', 'last_name' => 'mike']);

    $content = $this->actingAs($user)
        ->get(route('contacts.exports.show', ['current_team' => $team, 'format' => 'csv', 'sort' => $sort]))
        ->streamedContent();

    preg_match_all('/^(\w+)@example\.com/m', $content, $matches);

    expect(array_map(fn (string $name): string => $name.'@example.com', $matches[1]))->toBe($expectedEmails);
})->with([
    'newest' => ['newest', ['third@example.com', 'second@example.com', 'first@example.com']],
    'oldest' => ['oldest', ['first@example.com', 'second@example.com', 'third@example.com']],
    'name' => ['name', ['second@example.com', 'third@example.com', 'first@example.com']],
]);

test('a contacts page past the end redirects to the last page and keeps the filters', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    Contact::factory()->for($team)->count(21)->create(['first_name' => 'Ada']);

    $this->actingAs($user)
        ->get(route('contacts.index', ['current_team' => $team, 'search' => 'Ada', 'page' => 5]))
        ->assertRedirect(route('contacts.index', ['current_team' => $team, 'search' => 'Ada', 'page' => 2]));
});

test('a company contacts page past the end redirects to the last page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $company = Company::factory()->for($team)->create();
    Contact::factory()->for($team)->create(['company_id' => $company->id]);

    $this->actingAs($user)
        ->get(route('companies.show', [$team, $company, 'page' => 2]))
        ->assertRedirect(route('companies.show', [$team, $company, 'page' => 1]));
});
