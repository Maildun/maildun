<?php

use App\Mcp\Servers\MaildunServer;
use App\Mcp\Tools\Companies\GetCompanyTool;
use App\Mcp\Tools\Companies\ListCompaniesTool;
use App\Models\Company;
use App\Models\CompanyDomain;
use App\Models\Contact;
use App\Models\Team;
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
