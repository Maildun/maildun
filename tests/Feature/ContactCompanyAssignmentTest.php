<?php

use App\Http\Requests\SaveContactRequest;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('editing an automatic contact ignores a company that was deleted meanwhile', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $deletedCompany = Company::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->create(['email' => 'jane@acme.test']);
    $deletedCompanyUuid = $deletedCompany->uuid;
    $deletedCompany->delete();

    $this->actingAs($user)
        ->patch(route('contacts.update', [$team, $contact]), [
            'email' => $contact->email,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'company_assignment_mode' => 'automatic',
            'company_uuid' => $deletedCompanyUuid,
            'tags' => [],
        ])
        ->assertSessionHasNoErrors();

    expect($contact->fresh()->first_name)->toBe('Jane');
});

test('assigning a contact to a deleted company asks for another company', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $deletedCompany = Company::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->create();
    $deletedCompanyUuid = $deletedCompany->uuid;
    $deletedCompany->delete();

    $this->actingAs($user)
        ->patch(route('contacts.update', [$team, $contact]), [
            'email' => $contact->email,
            'company_assignment_mode' => 'manual',
            'company_uuid' => $deletedCompanyUuid,
            'tags' => [],
        ])
        ->assertInvalid(['company_uuid' => 'That company no longer exists. Choose another company.']);
});

test('a company deleted while its contact is being saved is not replaced by no company', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $company = Company::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->create(['company_assignment_mode' => 'automatic']);

    $this->app->afterResolving(SaveContactRequest::class, function () use ($company): void {
        $company->delete();
    });

    $this->actingAs($user)
        ->patch(route('contacts.update', [$team, $contact]), [
            'email' => $contact->email,
            'company_assignment_mode' => 'manual',
            'company_uuid' => $company->uuid,
            'tags' => [],
        ])
        ->assertInvalid(['company_uuid' => 'That company no longer exists. Choose another company.']);

    expect($contact->fresh()->company_assignment_mode->value)->toBe('automatic');
});

test('a contacts filter for a deleted company is cleared', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $deletedCompany = Company::factory()->for($team)->create();
    Contact::factory()->for($team)->create(['email' => 'jane@acme.test']);
    $deletedCompanyUuid = $deletedCompany->uuid;
    $deletedCompany->delete();

    $this->actingAs($user)
        ->get(route('contacts.index', ['current_team' => $team, 'company' => $deletedCompanyUuid]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.company', '')
            ->has('contacts.data', 1));

    $export = $this->actingAs($user)
        ->get(route('contacts.exports.show', [
            'current_team' => $team,
            'format' => 'csv',
            'company' => $deletedCompanyUuid,
        ]));

    expect($export->streamedContent())->toContain('jane@acme.test');
});
