<?php

use App\Enums\EmailDeliveryStatus;
use App\Enums\SubscriberLanguage;
use App\Models\Audience;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\Subscriber;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the audience and language filters must match the same membership', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $newsletter = Audience::factory()->for($team)->create();
    $events = Audience::factory()->for($team)->create();
    $match = Contact::factory()->for($team)->create(['email' => 'match@example.com']);
    Subscriber::factory()->for($newsletter)->for($match)->create(['email' => $match->email, 'language' => SubscriberLanguage::French]);
    $split = Contact::factory()->for($team)->create(['email' => 'split@example.com']);
    Subscriber::factory()->for($newsletter)->for($split)->create(['email' => $split->email, 'language' => SubscriberLanguage::English]);
    Subscriber::factory()->for($events)->for($split)->create(['email' => $split->email, 'language' => SubscriberLanguage::French]);
    $filters = ['audience' => $newsletter->uuid, 'language' => SubscriberLanguage::French->value];

    $this->actingAs($user)
        ->get(route('contacts.index', ['current_team' => $team, ...$filters]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('contacts.data', 1)
            ->where('contacts.data.0.uuid', $match->uuid));

    expect($this->actingAs($user)
        ->get(route('contacts.exports.show', ['current_team' => $team, 'format' => 'csv', ...$filters]))
        ->streamedContent())
        ->toContain('match@example.com')
        ->not->toContain('split@example.com');
});

test('a contact with a quoted email address can be deleted from its profile', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $contact = Contact::factory()->for($team)->create(['email' => '"ada,lovelace"@example.com']);

    $this->actingAs($user)
        ->delete(route('contacts.destroy', [$team, $contact]), ['confirmation' => '"ada,lovelace"@example.com'])
        ->assertSessionHasNoErrors();

    expect($contact->fresh())->toBeNull();
});

test('the contact profile counts only delivered emails as received', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $contact = Contact::factory()->for($team)->create();
    $email = Email::factory()->for($team)->create();

    foreach (EmailDeliveryStatus::cases() as $status) {
        EmailDelivery::factory()->for($email)->for($contact)->create(['status' => $status]);
    }

    $this->actingAs($user)
        ->get(route('contacts.show', [$team, $contact]))
        ->assertInertia(fn (Assert $page) => $page->where('activity.received', 4));
});

test('contact searches match a full name', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $company = Company::factory()->for($team)->create();
    $match = Contact::factory()->for($team)->create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'company_id' => $company->id]);
    Contact::factory()->for($team)->create(['first_name' => 'Ada', 'last_name' => 'Byron', 'company_id' => $company->id]);

    $this->actingAs($user)
        ->get(route('contacts.index', ['current_team' => $team, 'search' => 'ada lovelace']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('contacts.data', 1)
            ->where('contacts.data.0.uuid', $match->uuid));

    $this->actingAs($user)
        ->get(route('companies.show', [$team, $company, 'search' => 'Ada Lovelace']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('company.contacts.data', 1)
            ->where('company.contacts.data.0.uuid', $match->uuid));
});

test('profile pages load the company list only when the contact dialog asks for it', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $company = Company::factory()->for($team)->create(['name' => 'Acme']);
    $contact = Contact::factory()->for($team)->create(['company_id' => $company->id]);
    $audience = Audience::factory()->for($team)->create();

    foreach ([
        route('contacts.show', [$team, $contact]),
        route('companies.show', [$team, $company]),
        route('audiences.show', [$team, $audience]),
    ] as $url) {
        $this->actingAs($user)
            ->get($url)
            ->assertInertia(fn (Assert $page) => $page
                ->missing('companies')
                ->reloadOnly('companies', fn (Assert $reload) => $reload->where('companies.0.name', 'Acme')));
    }
});
