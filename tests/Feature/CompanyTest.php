<?php

use App\Enums\ContactCompanyAssignmentMode;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Http\Requests\SaveCompanyRequest;
use App\Jobs\RelinkCompanyContacts;
use App\Jobs\ResolveCompanyFavicon;
use App\Models\Audience;
use App\Models\Company;
use App\Models\CompanyDomain;
use App\Models\Contact;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use App\Services\DiceBearAvatarGenerator;
use App\Services\ResolveContactCompany;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
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
            ->where('audiences.0.uuid', $audience->uuid)
            ->where('tags.0.uuid', $tag->uuid)
            ->reloadOnly('companies', fn (Assert $reload) => $reload->where('companies.0.uuid', $company->uuid)));
});

test('company pages offer contact Edit only to roles that can edit contacts', function (array $permissions, bool $canManage, bool $canManageContacts) {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignCustomRole($team, $member, $permissions);
    $company = Company::factory()->for($team)->create();

    $this->actingAs($member)
        ->get(route('companies.show', [$team, $company]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('companies/show')
            ->where('canManage', $canManage)
            ->where('canManageContacts', $canManageContacts));
})->with([
    'company managers' => [[TeamPermission::ManageCompany], true, false],
    'contact managers' => [[TeamPermission::ManageContact], false, true],
]);

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

    expect($company->favicon)->toBeNull();
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

    expect($company->favicon)->toBeNull();
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
    $faviconUrl = 'https://www.google.com/s2/favicons?domain=acme.test&sz=64';
    Http::fake([
        $faviconUrl => Http::response('favicon', 200, ['Content-Type' => 'image/png']),
    ]);

    (new ResolveCompanyFavicon($company->id, 'acme.test'))->handle();

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
    $faviconUrl = 'https://www.google.com/s2/favicons?domain=missing.test&sz=64';
    Http::fake([
        $faviconUrl => Http::response('', 404),
    ]);

    $company->forceFill(['favicon' => Company::faviconUrlFor('stale.test')])->save();

    (new ResolveCompanyFavicon($company->id, 'missing.test'))->handle();

    expect($company->fresh()->favicon)->toBeNull()
        ->and($company->fresh()->faviconOrFallbackUrl())->toBe(URL::signedRoute('avatars.show', [
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

/**
 * Create a company through the store route and return the favicon lookup it queued.
 */
function createCompanyAndQueuedFaviconLookup(User $user, string $domain): ResolveCompanyFavicon
{
    Queue::fake([ResolveCompanyFavicon::class]);

    test()->actingAs($user)
        ->post(route('companies.store', $user->currentTeam), [
            'name' => 'Acme Inc.',
            'domains' => [$domain],
        ])
        ->assertSessionHasNoErrors();

    return Queue::pushed(ResolveCompanyFavicon::class)->sole();
}

test('renaming a company keeps its resolved favicon without a new lookup', function () {
    Queue::fake([ResolveCompanyFavicon::class]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $company = Company::factory()->for($team)->create(['name' => 'Acme Inc.']);
    $company->domains()->create(['team_id' => $team->id, 'domain' => 'acme.test']);
    $company->forceFill(['favicon' => Company::faviconUrlFor('acme.test')])->save();

    $this->actingAs($user)
        ->patch(route('companies.update', [$team, $company]), [
            'name' => 'Acme Incorporated',
            'domains' => ['acme.test', 'brand.test'],
        ])
        ->assertSessionHasNoErrors();

    expect($company->fresh()->favicon)->toBe(Company::faviconUrlFor('acme.test'));
    Queue::assertNotPushed(ResolveCompanyFavicon::class);
});

test('company favicon follows the first stored domain rather than the submitted order', function () {
    Queue::fake([ResolveCompanyFavicon::class]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $company = Company::factory()->for($team)->create(['name' => 'Acme Inc.']);
    $company->domains()->create(['team_id' => $team->id, 'domain' => 'acme.test']);
    $company->forceFill(['favicon' => Company::faviconUrlFor('acme.test')])->save();

    $this->actingAs($user)
        ->patch(route('companies.update', [$team, $company]), [
            'name' => 'Acme Inc.',
            'domains' => ['brand.test', 'acme.test'],
        ])
        ->assertSessionHasNoErrors();

    expect($company->fresh()->favicon)->toBe(Company::faviconUrlFor('acme.test'));
    Queue::assertNotPushed(ResolveCompanyFavicon::class);

    $this->actingAs($user)
        ->get(route('companies.show', [$team, $company]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('company.domains', ['acme.test', 'brand.test'])
            ->where('company.favicon', Company::faviconUrlFor('acme.test')));
});

test('an outdated favicon lookup does not overwrite the favicon of the current first domain', function () {
    Http::preventStrayRequests();
    Http::fake([
        'www.google.com/s2/favicons*' => Http::response('png', 200, ['Content-Type' => 'image/png']),
    ]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $outdatedLookup = createCompanyAndQueuedFaviconLookup($user, 'old-acme.test');
    $company = $team->companies()->sole();

    $this->travel(5)->seconds();

    $this->actingAs($user)
        ->patch(route('companies.update', [$team, $company]), [
            'name' => 'Acme Inc.',
            'domains' => ['new-acme.test'],
        ])
        ->assertSessionHasNoErrors();

    $currentLookup = Queue::pushed(ResolveCompanyFavicon::class)->last();

    $this->travel(1)->seconds();
    $outdatedLookup->handle();
    $currentLookup->handle();

    expect($company->fresh()->favicon)->toBe(Company::faviconUrlFor('new-acme.test'));
});

test('favicon lookup falls back to the placeholder on a redirect without a location', function () {
    config()->set('inertia.ssr.enabled', false);
    Http::preventStrayRequests();
    Http::fake([
        'www.google.com/s2/favicons*' => Http::response('', 302),
    ]);

    $user = User::factory()->create();
    $lookup = createCompanyAndQueuedFaviconLookup($user, 'acme.test');
    $company = $user->currentTeam->companies()->sole();

    $lookup->handle();

    $this->actingAs($user)
        ->get(route('companies.show', [$user->currentTeam, $company]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('company.favicon', $company->fallbackFaviconUrl()));
});

test('company placeholder icons keep loading after the app key changes', function () {
    config()->set('inertia.ssr.enabled', false);
    Http::preventStrayRequests();
    Http::fake([
        'www.google.com/s2/favicons*' => Http::response('', 404),
    ]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    createCompanyAndQueuedFaviconLookup($user, 'acme.test')->handle();
    $company = $team->companies()->sole();

    config(['app.key' => 'base64:'.base64_encode(random_bytes(32)), 'app.previous_keys' => []]);

    $favicon = null;
    $this->actingAs($user)
        ->get(route('companies.show', [$team, $company]))
        ->assertInertia(function (Assert $page) use (&$favicon): void {
            $favicon = $page->toArray()['props']['company']['favicon'];
        });

    $this->get($favicon)->assertOk();
});

test('a domain claimed by another company during a save is a validation error and saves nothing', function () {
    Queue::fake([ResolveCompanyFavicon::class]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $rival = Company::factory()->for($team)->create(['name' => 'Rival']);
    $acme = Company::factory()->for($team)->create(['name' => 'Acme']);
    $acme->domains()->create(['team_id' => $team->id, 'domain' => 'old.test']);

    $this->app->afterResolving(SaveCompanyRequest::class, function () use ($rival, $team): void {
        $rival->domains()->create(['team_id' => $team->id, 'domain' => 'new.test']);
    });

    $this->actingAs($user)
        ->post(route('companies.store', $team), [
            'name' => 'Newco',
            'domains' => ['new.test'],
        ])
        ->assertInvalid(['domains' => 'new.test is already assigned to another company.']);

    $this->actingAs($user)
        ->patch(route('companies.update', [$team, $acme]), [
            'name' => 'Acme Renamed',
            'domains' => ['new.test'],
        ])
        ->assertInvalid(['domains' => 'new.test is already assigned to another company.']);

    expect($team->companies()->where('name', 'Newco')->exists())->toBeFalse()
        ->and($acme->fresh()->name)->toBe('Acme')
        ->and($acme->domains()->pluck('domain')->all())->toBe(['old.test']);
});

test('a unique-key race the conflict check cannot see is still a validation error', function () {
    Queue::fake([ResolveCompanyFavicon::class]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $rival = Company::factory()->for($team)->create(['name' => 'Rival']);

    CompanyDomain::creating(function (CompanyDomain $domain) use ($rival): void {
        if ($domain->company_id !== $rival->id) {
            DB::table('company_domains')->insert([
                'uuid' => (string) Str::uuid(),
                'team_id' => $rival->team_id,
                'company_id' => $rival->id,
                'domain' => $domain->domain,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    });

    $this->actingAs($user)
        ->post(route('companies.store', $team), [
            'name' => 'Acme',
            'domains' => ['acme.test'],
        ])
        ->assertInvalid(['domains' => 'Someone else changed these companies at the same time. Try again.']);

    expect($team->companies()->where('name', 'Acme')->exists())->toBeFalse();
});

test('a company name claimed during a save is a validation error', function () {
    Queue::fake([ResolveCompanyFavicon::class]);

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->app->afterResolving(SaveCompanyRequest::class, function () use ($team): void {
        $team->companies()->create(['name' => 'acme']);
    });

    $this->actingAs($user)
        ->post(route('companies.store', $team), [
            'name' => 'Acme',
            'domains' => ['acme.test'],
        ])
        ->assertInvalid(['name' => 'A company with this name already exists.']);

    expect($team->companies()->count())->toBe(1);
});

test('re-resolving a contact does not overwrite a manual company chosen meanwhile', function () {
    $team = Team::factory()->create();
    $acme = Company::factory()->for($team)->create();
    $acme->domains()->create(['team_id' => $team->id, 'domain' => 'acme.test']);
    $chosen = Company::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->create(['email' => 'pat@acme.test']);

    Contact::query()->whereKey($contact->id)->update([
        'company_assignment_mode' => ContactCompanyAssignmentMode::Manual,
        'company_id' => $chosen->id,
    ]);

    app(ResolveContactCompany::class)->handle($contact);

    expect($contact->fresh()->company_id)->toBe($chosen->id);
});

test('company names keep their length when lowercased', function () {
    Queue::fake([ResolveCompanyFavicon::class]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $name = str_repeat('İ', 255);

    $this->actingAs($user)
        ->post(route('companies.store', $team), [
            'name' => $name,
            'domains' => ['acme.test'],
        ])
        ->assertSessionHasNoErrors();

    expect(mb_strlen($team->companies()->sole()->normalized_name))->toBe(255);

    $this->actingAs($user)
        ->post(route('companies.store', $team), [
            'name' => $name,
            'domains' => ['other.test'],
        ])
        ->assertInvalid(['name' => 'A company with this name already exists.']);
});

test('renaming a company does not re-resolve every contact in the workspace', function () {
    Queue::fake([ResolveCompanyFavicon::class]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $company = Company::factory()->for($team)->create(['name' => 'Acme']);
    $company->domains()->create(['team_id' => $team->id, 'domain' => 'acme.test']);
    Contact::factory()->for($team)->count(60)->create();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->actingAs($user)
        ->patch(route('companies.update', [$team, $company]), [
            'name' => 'Acme Renamed',
            'domains' => ['acme.test'],
        ])
        ->assertSessionHasNoErrors();

    expect($queries)->toBeLessThan(40);
});

test('company domain changes re-link only contacts on the added and removed domains in a queued job', function () {
    Queue::fake([RelinkCompanyContacts::class, ResolveCompanyFavicon::class]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $company = Company::factory()->for($team)->create(['name' => 'Acme']);
    $company->domains()->create(['team_id' => $team->id, 'domain' => 'keep.test']);
    $company->domains()->create(['team_id' => $team->id, 'domain' => 'old.test']);
    $leaving = Contact::factory()->for($team)->for($company)->create(['email' => 'leaving@old.test']);
    $staying = Contact::factory()->for($team)->for($company)->create(['email' => 'staying@keep.test']);
    $joining = Contact::factory()->for($team)->create(['email' => 'joining@new.test']);
    $manual = Contact::factory()->for($team)->create([
        'email' => 'manual@new.test',
        'company_assignment_mode' => ContactCompanyAssignmentMode::Manual,
    ]);

    $this->actingAs($user)
        ->patch(route('companies.update', [$team, $company]), [
            'name' => 'Acme',
            'domains' => ['keep.test', 'new.test'],
        ])
        ->assertSessionHasNoErrors();

    expect($joining->fresh()->company_id)->toBeNull();

    $relink = Queue::pushed(RelinkCompanyContacts::class)->sole();

    expect($relink->teamId)->toBe($team->id)
        ->and($relink->domains)->toBe(['new.test', 'old.test']);

    $relink->handle(app(ResolveContactCompany::class));

    expect($leaving->fresh()->company_id)->toBeNull()
        ->and($staying->fresh()->company_id)->toBe($company->id)
        ->and($joining->fresh()->company_id)->toBe($company->id)
        ->and($manual->fresh()->company_id)->toBeNull();
});

test('deleting a company re-links its former contacts to another matching company', function () {
    Queue::fake([ResolveCompanyFavicon::class]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $deleted = Company::factory()->for($team)->create();
    $deleted->domains()->create(['team_id' => $team->id, 'domain' => 'acme.test']);
    $other = Company::factory()->for($team)->create();
    $other->domains()->create(['team_id' => $team->id, 'domain' => 'other.test']);
    $automatic = Contact::factory()->for($team)->for($deleted)->create(['email' => 'auto@acme.test']);
    $manual = Contact::factory()->for($team)->for($deleted)->create([
        'email' => 'manual@other.test',
        'company_assignment_mode' => ContactCompanyAssignmentMode::Manual,
    ]);

    $this->actingAs($user)
        ->delete(route('companies.destroy', [$team, $deleted]))
        ->assertRedirect(route('companies.index', $team));

    expect($automatic->fresh()->company_id)->toBeNull()
        ->and($manual->fresh()->company_id)->toBe($other->id)
        ->and($manual->fresh()->company_assignment_mode)->toBe(ContactCompanyAssignmentMode::Automatic);
});

test('list-shaped company filters fall back to no filter', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $company = Company::factory()->for($team)->create();

    $this->actingAs($user)
        ->get(route('companies.index', ['current_team' => $team, 'search' => ['x'], 'sort' => ['name']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('filters', ['search' => '', 'sort' => 'newest']));

    $this->actingAs($user)
        ->get(route('companies.show', ['current_team' => $team, 'company' => $company, 'search' => ['x'], 'audience' => ['x']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('filters', ['search' => '', 'audience' => '']));
});

test('a company audience filter that is not an audience id is ignored', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $company = Company::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->for($company)->create();

    $this->actingAs($user)
        ->get(route('companies.show', ['current_team' => $team, 'company' => $company, 'audience' => 'not-an-id']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.audience', '')
            ->where('company.contacts.data.0.uuid', $contact->uuid));
});

test('a list-shaped company name is a validation error', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('companies.store', $user->currentTeam), [
            'name' => ['Acme'],
            'domains' => ['acme.com'],
        ])
        ->assertSessionHasErrors(['name' => 'The name field must be a string.']);

    expect(Company::query()->where('team_id', $user->currentTeam->id)->exists())->toBeFalse();
});
