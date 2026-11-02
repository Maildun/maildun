<?php

use App\Enums\ContactCompanyAssignmentMode;
use App\Jobs\ResolveCompanyFavicon;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake([ResolveCompanyFavicon::class]);
});

test('a company can be created, renamed and deleted', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('companies.store', $team), [
            'name' => 'Acme Inc.',
            'domains' => ['acme.test'],
        ])
        ->assertSessionHasNoErrors();

    $company = $team->companies()->sole();

    $this->actingAs($user)
        ->patch(route('companies.update', [$team, $company]), [
            'name' => 'Acme Corp',
            'domains' => ['acme.test'],
        ])
        ->assertSessionHasNoErrors();

    expect($company->fresh()->name)->toBe('Acme Corp');

    $this->actingAs($user)
        ->delete(route('companies.destroy', [$team, $company]))
        ->assertRedirect(route('companies.index', $team));

    $this->assertModelMissing($company);
});

test('the company list shows the workspace companies with contact counts and searches by name or domain', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $acme = Company::factory()->for($team)->create(['name' => 'Acme Inc.']);
    $acme->domains()->create(['team_id' => $team->id, 'domain' => 'acme.test']);
    Contact::factory()->for($team)->for($acme)->count(2)->create();
    $globex = Company::factory()->for($team)->create(['name' => 'Globex']);
    $globex->domains()->create(['team_id' => $team->id, 'domain' => 'globex.test']);
    Company::factory()->create(['name' => 'Acme Elsewhere']);

    $this->actingAs($user)
        ->get(route('companies.index', ['current_team' => $team, 'sort' => 'name']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('companies/index')
            ->has('companies.data', 2)
            ->where('companies.data.0.uuid', $acme->uuid)
            ->where('companies.data.0.contacts_count', 2)
            ->where('companies.data.1.uuid', $globex->uuid));

    $this->actingAs($user)
        ->get(route('companies.index', ['current_team' => $team, 'search' => 'globex.test']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('companies.data', 1)
            ->where('companies.data.0.uuid', $globex->uuid));
});

test('a manual assignment keeps the chosen company and switching back to automatic follows the email domain', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $acme = Company::factory()->for($team)->create();
    $acme->domains()->create(['team_id' => $team->id, 'domain' => 'acme.test']);
    $globex = Company::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->for($acme)->create(['email' => 'jane@acme.test']);

    $this->actingAs($user)
        ->patch(route('contacts.update', [$team, $contact]), [
            'email' => $contact->email,
            'company_assignment_mode' => 'manual',
            'company_uuid' => $globex->uuid,
            'tags' => [],
        ])
        ->assertSessionHasNoErrors();

    expect($contact->fresh()->company_id)->toBe($globex->id)
        ->and($contact->fresh()->company_assignment_mode)->toBe(ContactCompanyAssignmentMode::Manual);

    $this->actingAs($user)
        ->patch(route('contacts.update', [$team, $contact]), [
            'email' => $contact->email,
            'company_assignment_mode' => 'automatic',
            'company_uuid' => $globex->uuid,
            'tags' => [],
        ])
        ->assertSessionHasNoErrors();

    expect($contact->fresh()->company_id)->toBe($acme->id)
        ->and($contact->fresh()->company_assignment_mode)->toBe(ContactCompanyAssignmentMode::Automatic);
});

test('a contact cannot be assigned to a company in another workspace', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $contact = Contact::factory()->for($team)->create();
    $foreignCompany = Company::factory()->create();

    $this->actingAs($user)
        ->patch(route('contacts.update', [$team, $contact]), [
            'email' => $contact->email,
            'company_assignment_mode' => 'manual',
            'company_uuid' => $foreignCompany->uuid,
            'tags' => [],
        ])
        ->assertInvalid(['company_uuid']);

    expect($contact->fresh()->company_id)->toBeNull();
});

test('the company detail lists only its own contacts', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $company = Company::factory()->for($team)->create();
    $member = Contact::factory()->for($team)->for($company)->create();
    Contact::factory()->for($team)->for(Company::factory()->for($team))->create();
    Contact::factory()->for($team)->create();

    $this->actingAs($user)
        ->get(route('companies.show', [$team, $company]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('companies/show')
            ->where('company.contacts_count', 1)
            ->has('company.contacts.data', 1)
            ->where('company.contacts.data.0.uuid', $member->uuid));
});

test('a company from another workspace cannot be viewed, edited or deleted', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $foreignCompany = Company::factory()->create(['name' => 'Foreign']);

    $this->actingAs($user)
        ->get(route('companies.show', [$team, $foreignCompany]))
        ->assertNotFound();

    $this->actingAs($user)
        ->patch(route('companies.update', [$team, $foreignCompany]), [
            'name' => 'Hijacked',
            'domains' => [],
        ])
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('companies.destroy', [$team, $foreignCompany]))
        ->assertNotFound();

    expect($foreignCompany->fresh()->name)->toBe('Foreign');
});
