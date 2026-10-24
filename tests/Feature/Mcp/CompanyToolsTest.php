<?php

use App\Enums\ContactCompanyAssignmentMode;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Jobs\RelinkCompanyContacts;
use App\Jobs\ResolveCompanyFavicon;
use App\Mcp\Servers\MaildunServer;
use App\Mcp\Tools\Companies\CreateCompanyTool;
use App\Mcp\Tools\Companies\DeleteCompanyTool;
use App\Mcp\Tools\Companies\GetCompanyTool;
use App\Mcp\Tools\Companies\ListCompaniesTool;
use App\Mcp\Tools\Companies\UpdateCompanyTool;
use App\Models\Company;
use App\Models\CompanyDomain;
use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\Fluent\AssertableJson;

test('lists and searches workspace companies with their domains and contact counts', function () {
    $team = Team::factory()->create();
    $acme = Company::factory()->for($team)->create(['name' => 'Acme']);
    CompanyDomain::factory()->for($acme)->create(['domain' => 'acme.test']);
    Contact::factory()->for($team)->for($acme)->count(2)->create();
    Company::factory()->for($team)->create(['name' => 'Globex']);
    Company::factory()->create(['name' => 'Acme Elsewhere']);

    MaildunServer::tool(ListCompaniesTool::class, [
        'workspace' => $team->slug,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('count', 2)
        ->where('companies.0.name', 'Acme')
        ->where('companies.0.domains', ['acme.test'])
        ->where('companies.0.contacts_count', 2)
        ->where('companies.1.name', 'Globex')
        ->etc());

    MaildunServer::tool(ListCompaniesTool::class, [
        'workspace' => $team->slug,
        'search' => 'acme.test',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('count', 1)
        ->where('companies.0.uuid', $acme->uuid)
        ->etc());
});

test('gets one company and does not resolve one from another workspace', function () {
    $team = Team::factory()->create();
    $company = Company::factory()->for($team)->create(['name' => 'Acme']);
    $otherCompany = Company::factory()->create();

    MaildunServer::tool(GetCompanyTool::class, [
        'workspace' => $team->slug,
        'uuid' => $company->uuid,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('company.uuid', $company->uuid)
        ->where('company.name', 'Acme')
        ->where('company.contacts_count', 0)
        ->etc());

    MaildunServer::tool(GetCompanyTool::class, [
        'workspace' => $team->slug,
        'uuid' => $otherCompany->uuid,
    ])->assertHasErrors(['company was not found in this workspace']);
});

test('creates a company with normalized domains and queues the contact re-link and favicon lookup', function () {
    Queue::fake();
    $team = Team::factory()->create();

    MaildunServer::tool(CreateCompanyTool::class, [
        'workspace' => $team->slug,
        'name' => '  Acme   Inc ',
        'domains' => ['https://www.ACME.test/', 'acme-mail.test'],
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('company.name', 'Acme Inc')
        ->where('company.domains', ['acme-mail.test', 'acme.test'])
        ->where('company.contacts_count', 0)
        ->etc());

    $company = $team->companies()->sole();

    expect($company->name)->toBe('Acme Inc')
        ->and($company->domains()->orderBy('id')->pluck('domain')->all())->toBe(['acme.test', 'acme-mail.test']);
    Queue::assertPushed(RelinkCompanyContacts::class, fn (RelinkCompanyContacts $job): bool => $job->teamId === $team->id
        && $job->domains === ['acme-mail.test', 'acme.test']);
    Queue::assertPushed(ResolveCompanyFavicon::class, fn (ResolveCompanyFavicon $job): bool => $job->companyId === $company->id
        && $job->domain === 'acme.test');
});

test('updates a company name or domains and re-links only the changed domains', function () {
    Queue::fake();
    $team = Team::factory()->create();
    $company = Company::factory()->for($team)->create(['name' => 'Acme', 'favicon' => 'https://example.test/icon.png']);
    $company->domains()->create(['team_id' => $team->id, 'domain' => 'acme.test']);

    MaildunServer::tool(UpdateCompanyTool::class, [
        'workspace' => $team->slug,
        'uuid' => $company->uuid,
        'name' => 'Acme Renamed',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('company.name', 'Acme Renamed')
        ->where('company.domains', ['acme.test'])
        ->etc());

    Queue::assertNotPushed(RelinkCompanyContacts::class);
    Queue::assertNotPushed(ResolveCompanyFavicon::class);

    MaildunServer::tool(UpdateCompanyTool::class, [
        'workspace' => $team->slug,
        'uuid' => $company->uuid,
        'domains' => ['acme.test', 'ACME.example'],
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('company.name', 'Acme Renamed')
        ->where('company.domains', ['acme.example', 'acme.test'])
        ->etc());

    Queue::assertPushed(RelinkCompanyContacts::class, fn (RelinkCompanyContacts $job): bool => $job->domains === ['acme.example']);

    MaildunServer::tool(UpdateCompanyTool::class, [
        'workspace' => $team->slug,
        'uuid' => $company->uuid,
    ])->assertHasErrors(['Provide at least one field to update.']);
});

test('deletes a company after name confirmation and re-links its manually assigned contacts', function () {
    Queue::fake();
    $team = Team::factory()->create();
    $company = Company::factory()->for($team)->create(['name' => 'Acme']);
    $company->domains()->create(['team_id' => $team->id, 'domain' => 'acme.test']);
    $automatic = Contact::factory()->for($team)->for($company)->create(['email' => 'pat@acme.test']);
    $manual = Contact::factory()->for($team)->for($company)->create([
        'email' => 'sam@globex.test',
        'company_assignment_mode' => ContactCompanyAssignmentMode::Manual,
    ]);

    MaildunServer::tool(DeleteCompanyTool::class, [
        'workspace' => $team->slug,
        'uuid' => $company->uuid,
        'confirm_name' => 'acme',
    ])->assertHasErrors(['The confirmation name must exactly match the company name.']);

    expect($company->fresh())->not->toBeNull();

    MaildunServer::tool(DeleteCompanyTool::class, [
        'workspace' => $team->slug,
        'uuid' => $company->uuid,
        'confirm_name' => 'Acme',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('deleted', true)
        ->where('company.uuid', $company->uuid)
        ->where('company.name', 'Acme')
        ->etc());

    expect($company->fresh())->toBeNull()
        ->and($automatic->fresh()->company_id)->toBeNull()
        ->and($manual->fresh()->company_id)->toBeNull()
        ->and($manual->fresh()->company_assignment_mode)->toBe(ContactCompanyAssignmentMode::Automatic);
    Queue::assertPushed(RelinkCompanyContacts::class, fn (RelinkCompanyContacts $job): bool => $job->teamId === $team->id
        && $job->domains === ['globex.test']);
});

test('company writes use the web form validation', function (array $arguments, string $error) {
    $team = Team::factory()->create();
    Company::factory()->for($team)->create(['name' => 'Existing']);

    MaildunServer::tool(CreateCompanyTool::class, [
        'workspace' => $team->slug,
        ...$arguments,
    ])->assertHasErrors([$error]);

    expect($team->companies()->count())->toBe(1);
})->with([
    'missing domains' => [['name' => 'Acme'], 'The domains field is required.'],
    'invalid domain' => [['name' => 'Acme', 'domains' => ['not a domain']], 'not a domain is not a valid domain.'],
    'personal provider' => [['name' => 'Acme', 'domains' => ['Gmail.com']], 'gmail.com is a personal email provider.'],
    'repeated domain' => [['name' => 'Acme', 'domains' => ['acme.test', 'www.acme.test']], 'Each domain may only be added once.'],
    'taken name' => [['name' => ' existing ', 'domains' => ['acme.test']], 'A company with this name already exists.'],
]);

test('a domain owned by another company in the workspace is refused, but not one from another workspace', function () {
    Queue::fake();
    $team = Team::factory()->create();
    $rival = Company::factory()->for($team)->create(['name' => 'Rival']);
    $rival->domains()->create(['team_id' => $team->id, 'domain' => 'rival.test']);
    $acme = Company::factory()->for($team)->create(['name' => 'Acme']);
    $acme->domains()->create(['team_id' => $team->id, 'domain' => 'acme.test']);
    $elsewhere = Company::factory()->create(['name' => 'Elsewhere']);
    $elsewhere->domains()->create(['team_id' => $elsewhere->team_id, 'domain' => 'shared.test']);

    MaildunServer::tool(CreateCompanyTool::class, [
        'workspace' => $team->slug,
        'name' => 'Newco',
        'domains' => ['rival.test'],
    ])->assertHasErrors(['rival.test is already assigned to another company.']);

    MaildunServer::tool(UpdateCompanyTool::class, [
        'workspace' => $team->slug,
        'uuid' => $acme->uuid,
        'domains' => ['acme.test', 'rival.test'],
    ])->assertHasErrors(['rival.test is already assigned to another company.']);

    expect($acme->domains()->pluck('domain')->all())->toBe(['acme.test']);

    MaildunServer::tool(CreateCompanyTool::class, [
        'workspace' => $team->slug,
        'name' => 'Shared',
        'domains' => ['shared.test'],
    ])->assertOk();
});

test('company writes cannot reach a company in another workspace', function () {
    $team = Team::factory()->create();
    $otherCompany = Company::factory()->create(['name' => 'Other']);
    $otherCompany->domains()->create(['team_id' => $otherCompany->team_id, 'domain' => 'other.test']);

    MaildunServer::tool(UpdateCompanyTool::class, [
        'workspace' => $team->slug,
        'uuid' => $otherCompany->uuid,
        'name' => 'Hijacked',
    ])->assertHasErrors(['company was not found in this workspace']);

    MaildunServer::tool(DeleteCompanyTool::class, [
        'workspace' => $team->slug,
        'uuid' => $otherCompany->uuid,
        'confirm_name' => 'Other',
    ])->assertHasErrors(['company was not found in this workspace']);

    expect($otherCompany->fresh()->name)->toBe('Other');
});

test('company writes require the company manage permission', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignCustomRole($team, $member, [TeamPermission::ManageContact]);
    $company = Company::factory()->for($team)->create(['name' => 'Acme']);
    $company->domains()->create(['team_id' => $team->id, 'domain' => 'acme.test']);

    $unauthorizedCalls = [
        [CreateCompanyTool::class, ['workspace' => $team->slug, 'name' => 'Newco', 'domains' => ['newco.test']]],
        [UpdateCompanyTool::class, ['workspace' => $team->slug, 'uuid' => $company->uuid, 'name' => 'Renamed']],
        [DeleteCompanyTool::class, ['workspace' => $team->slug, 'uuid' => $company->uuid, 'confirm_name' => 'Acme']],
    ];

    foreach ($unauthorizedCalls as [$tool, $arguments]) {
        MaildunServer::actingAs($member)->tool($tool, $arguments)->assertHasErrors(['unauthorized']);
    }

    expect($team->companies()->pluck('name')->all())->toBe(['Acme']);

    assignCustomRole($team, $member, [TeamPermission::ManageCompany]);

    MaildunServer::actingAs($member)->tool(UpdateCompanyTool::class, [
        'workspace' => $team->slug,
        'uuid' => $company->uuid,
        'name' => 'Renamed',
    ])->assertOk();

    expect($company->fresh()->name)->toBe('Renamed');
});
