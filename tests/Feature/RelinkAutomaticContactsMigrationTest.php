<?php

use App\Enums\ContactCompanyAssignmentMode;
use App\Models\Company;
use App\Models\CompanyDomain;
use App\Models\Contact;
use App\Models\Team;

test('links backfilled automatic contacts to the company of their email domain', function () {
    $team = Team::factory()->create();
    $company = Company::factory()->for($team)->create();
    CompanyDomain::factory()->for($company)->create(['domain' => 'acme.test']);
    $automatic = Contact::factory()->for($team)->create(['email' => 'ada@acme.test']);
    $manual = Contact::factory()->for($team)->create([
        'email' => 'grace@acme.test',
        'company_assignment_mode' => ContactCompanyAssignmentMode::Manual,
    ]);
    $elsewhere = Contact::factory()->for(Team::factory())->create(['email' => 'linus@acme.test']);

    (require base_path('database/migrations/2026_10_21_035001_relink_automatic_contacts_to_company_domains.php'))->up();

    expect($automatic->fresh()->company_id)->toBe($company->id)
        ->and($manual->fresh()->company_id)->toBeNull()
        ->and($elsewhere->fresh()->company_id)->toBeNull();
});
