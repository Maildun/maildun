<?php

use App\Jobs\ResolveCompanyFavicon;
use App\Models\Audience;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use App\Services\DiceBearAvatarGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

test('company contacts include the fields required by the shared hover card', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $company = Company::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->for($company)->create([
        'company_assignment_mode' => 'manual',
    ]);
    $tag = Tag::factory()->for($team)->create(['name' => 'VIP']);
    $contact->tags()->attach($tag);
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->create([
        'contact_id' => $contact->id,
        'email' => $contact->email,
    ]);

    $this->actingAs($user)
        ->get(route('companies.show', [$team, $company]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('companies/show')
            ->where('company.contacts.data.0.uuid', $contact->uuid)
            ->where('company.contacts.data.0.company_assignment_mode', 'manual')
            ->where('company.contacts.data.0.company.uuid', $company->uuid)
            ->where('company.contacts.data.0.tags.0.name', 'VIP')
            ->where('company.contacts.data.0.audiences_count', 1)
            ->where('company.contacts.data.0.audiences.0.uuid', $audience->uuid)
            ->where('company.contacts.data.0.audiences.0.name', $audience->name)
            ->where('companies.0.uuid', $company->uuid)
            ->where('audiences.0.uuid', $audience->uuid)
            ->where('tags.0.uuid', $tag->uuid));
});

test('company contacts can be filtered by audience', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $company = Company::factory()->for($team)->create();
    $newsletter = Audience::factory()->for($team)->create(['name' => 'Newsletter']);
    $customers = Audience::factory()->for($team)->create(['name' => 'Customers']);
    $newsletterContact = Contact::factory()->for($team)->for($company)->create();
    $customerContact = Contact::factory()->for($team)->for($company)->create();
    Subscriber::factory()->for($newsletter)->create([
        'contact_id' => $newsletterContact->id,
        'email' => $newsletterContact->email,
    ]);
    Subscriber::factory()->for($customers)->create([
        'contact_id' => $customerContact->id,
        'email' => $customerContact->email,
    ]);

    $this->actingAs($user)
        ->get(route('companies.show', [
            'current_team' => $team,
            'company' => $company,
            'audience' => $newsletter->uuid,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('companies/show')
            ->has('company.contacts.data', 1)
            ->where('company.contacts.data.0.uuid', $newsletterContact->uuid)
            ->where('filters.search', '')
            ->where('filters.audience', $newsletter->uuid));
});

test('company domains automatically associate matching business contacts', function () {
    Queue::fake([ResolveCompanyFavicon::class]);

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('companies.store', $team), [
            'name' => 'Acme Inc.',
            'domains' => ['acme.test', 'acme.co.test'],
        ])
        ->assertRedirect();

    $company = $team->companies()->sole();
    $fallbackFavicon = URL::signedRoute('avatars.show', [
        'style' => DiceBearAvatarGenerator::LOOPS,
        'seed' => $company->uuid,
    ], absolute: false);

    expect($company->favicon)->toBe($fallbackFavicon);
    Queue::assertPushed(ResolveCompanyFavicon::class, function (ResolveCompanyFavicon $job) use ($company): bool {
        return $job->companyId === $company->id && $job->domain === 'acme.test';
    });

    $this->actingAs($user)
        ->post(route('contacts.store', $team), [
            'email' => 'taylor@acme.co.test',
            'first_name' => 'Taylor',
            'last_name' => 'Otwell',
            'company_assignment_mode' => 'automatic',
            'company_uuid' => '',
            'tags' => [],
        ])
        ->assertRedirect();

    $contact = $team->contacts()->sole();

    expect($contact->company_id)->toBe($company->id);

    $this->actingAs($user)
        ->patch(route('contacts.update', [$team, $contact]), [
            'email' => $contact->email,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'company_assignment_mode' => 'manual',
            'company_uuid' => '',
            'tags' => [],
        ])
        ->assertRedirect();

    expect($contact->fresh()->company_id)->toBeNull()
        ->and($contact->fresh()->company_assignment_mode->value)->toBe('manual');

    $this->actingAs($user)
        ->get(route('companies.show', [$team, $company]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('companies/show')
            ->where('company.uuid', $company->uuid)
            ->where('company.favicon', $fallbackFavicon)
            ->where('company.domains', ['acme.test', 'acme.co.test']));
});

test('company favicon follows its first submitted domain', function () {
    Queue::fake([ResolveCompanyFavicon::class]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $company = Company::factory()->for($team)->create(['name' => 'Acme Inc.']);
    $company->domains()->create([
        'team_id' => $team->id,
        'domain' => 'acme.test',
    ]);

    $this->actingAs($user)
        ->patch(route('companies.update', [$team, $company]), [
            'name' => $company->name,
            'domains' => ['new-acme.test'],
        ])
        ->assertRedirect();

    $company->refresh();

    expect($company->favicon)->toBe(URL::signedRoute('avatars.show', [
        'style' => DiceBearAvatarGenerator::LOOPS,
        'seed' => $company->uuid,
    ], absolute: false));
    Queue::assertPushed(ResolveCompanyFavicon::class, function (ResolveCompanyFavicon $job) use ($company): bool {
        return $job->companyId === $company->id && $job->domain === 'new-acme.test';
    });
});

test('background company favicon lookup persists Google favicon', function () {
    Http::preventStrayRequests();

    $company = Company::factory()->create();
    $company->domains()->create([
        'team_id' => $company->team_id,
        'domain' => 'acme.test',
    ]);
    $updatedAt = $company->updated_at->toISOString();
    $faviconUrl = 'https://www.google.com/s2/favicons?domain=acme.test&sz=64';
    Http::fake([
        $faviconUrl => Http::response('favicon', 200, ['Content-Type' => 'image/png']),
    ]);

    (new ResolveCompanyFavicon($company->id, 'acme.test', $updatedAt))->handle();

    expect($company->fresh()->favicon)->toBe($faviconUrl);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && $request->url() === $faviconUrl);
});

test('background company favicon lookup falls back to local DiceBear when Google has no favicon', function () {
    Http::preventStrayRequests();

    $company = Company::factory()->create();
    $company->domains()->create([
        'team_id' => $company->team_id,
        'domain' => 'missing.test',
    ]);
    $updatedAt = $company->updated_at->toISOString();
    $faviconUrl = 'https://www.google.com/s2/favicons?domain=missing.test&sz=64';
    Http::fake([
        $faviconUrl => Http::response('', 404),
    ]);

    (new ResolveCompanyFavicon($company->id, 'missing.test', $updatedAt))->handle();

    expect($company->fresh()->favicon)->toBe(URL::signedRoute('avatars.show', [
        'style' => DiceBearAvatarGenerator::LOOPS,
        'seed' => $company->uuid,
    ], absolute: false));
});

test('company domains reject personal providers and other companies domain collisions', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $company = Company::factory()->for($team)->create(['name' => 'Existing']);
    $company->domains()->create([
        'team_id' => $team->id,
        'domain' => 'existing.test',
    ]);

    $this->actingAs($user)
        ->post(route('companies.store', $team), [
            'name' => 'Another company',
            'domains' => ['gmail.com', 'existing.test'],
        ])
        ->assertInvalid(['domains.0', 'domains']);

    expect(Contact::query()->where('team_id', $team->id)->count())->toBe(0);
});

test('company routes return 404 for missing or malformed company UUIDs', function (string $companyUuid) {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->actingAs($user)
        ->get(route('companies.show', [$team, $companyUuid]))
        ->assertNotFound();

    $response->assertSee('Not Found');
    $response->assertSee('Back to dashboard');
    $response->assertSee('href="'.url('/').'"', false);
})->with([
    'missing UUID' => '00000000-0000-0000-0000-000000000000',
    'malformed UUID' => '60e1ce6a-7e40-43be-abbb-',
]);

test('company domains are normalized so they match contact email domains', function (string $enteredDomain) {
    Queue::fake([ResolveCompanyFavicon::class]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $contact = Contact::factory()->for($team)->create(['email' => 'jane@acme.test']);

    $this->actingAs($user)
        ->post(route('companies.store', $team), [
            'name' => 'Acme Inc.',
            'domains' => [$enteredDomain],
        ])
        ->assertSessionHasNoErrors();

    $company = $team->companies()->sole();

    expect($company->domains()->pluck('domain')->all())->toBe(['acme.test'])
        ->and($contact->fresh()->company_id)->toBe($company->id);
})->with([
    'www prefix' => 'www.acme.test',
    'scheme and trailing slash' => 'https://www.ACME.test/',
]);

test('company domains with international characters match contacts in either form', function (string $enteredDomain, string $contactEmail) {
    Queue::fake([ResolveCompanyFavicon::class]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $contact = Contact::factory()->for($team)->create(['email' => $contactEmail]);

    $this->actingAs($user)
        ->post(route('companies.store', $team), [
            'name' => 'München GmbH',
            'domains' => [$enteredDomain],
        ])
        ->assertSessionHasNoErrors();

    $company = $team->companies()->sole();

    expect($company->domains()->pluck('domain')->all())->toBe(['xn--mnchen-3ya.de'])
        ->and($contact->fresh()->company_id)->toBe($company->id);
})->with([
    'unicode domain, unicode email' => ['münchen.de', 'anna@münchen.de'],
    'punycode domain, unicode email' => ['xn--mnchen-3ya.de', 'anna@münchen.de'],
    'unicode domain, punycode email' => ['münchen.de', 'anna@xn--mnchen-3ya.de'],
]);

test('company domains accept international top-level domains', function () {
    Queue::fake([ResolveCompanyFavicon::class]);

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('companies.store', $team), [
            'name' => 'Example RF',
            'domains' => ['example.xn--p1ai'],
        ])
        ->assertSessionHasNoErrors();

    expect($team->companies()->sole()->domains()->pluck('domain')->all())->toBe(['example.xn--p1ai']);
});

test('company domains reject every known consumer email provider', function (string $domain) {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('companies.store', $team), [
            'name' => 'Not a company',
            'domains' => [$domain],
        ])
        ->assertInvalid(['domains.0' => $domain]);

    expect($team->companies()->count())->toBe(0);
})->with(['googlemail.com', 'me.com', 'yahoo.co.uk', 'gmx.de']);

test('company domain errors name the domain that failed', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $existing = Company::factory()->for($team)->create(['name' => 'Existing']);
    $existing->domains()->create([
        'team_id' => $team->id,
        'domain' => 'taken.test',
    ]);

    $this->actingAs($user)
        ->post(route('companies.store', $team), [
            'name' => 'Acme',
            'domains' => ['acme.test', 'not a domain'],
        ])
        ->assertInvalid(['domains.1' => 'not a domain is not a valid domain.']);

    $this->actingAs($user)
        ->post(route('companies.store', $team), [
            'name' => 'Acme',
            'domains' => ['acme.test', 'taken.test'],
        ])
        ->assertInvalid(['domains' => 'taken.test is already assigned to another company.']);
});
