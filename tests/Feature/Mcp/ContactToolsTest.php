<?php

use App\Enums\ContactCompanyAssignmentMode;
use App\Mcp\Servers\MaildunServer;
use App\Mcp\Tools\Contacts\CreateContactTool;
use App\Mcp\Tools\Contacts\DeleteContactTool;
use App\Mcp\Tools\Contacts\GetContactTool;
use App\Mcp\Tools\Contacts\ListContactsTool;
use App\Mcp\Tools\Contacts\UpdateContactTool;
use App\Mcp\Tools\Tags\ListTagsTool;
use App\Models\Audience;
use App\Models\Company;
use App\Models\CompanyDomain;
use App\Models\Contact;
use App\Models\Subscriber;
use App\Models\Team;
use Illuminate\Testing\Fluent\AssertableJson;

test('manages a contact through its MCP lifecycle', function () {
    $team = Team::factory()->create();
    $audience = Audience::factory()->for($team)->create();

    MaildunServer::tool(CreateContactTool::class, [
        'workspace' => $team->slug,
        'email' => ' Ada@Example.com ',
        'first_name' => 'Ada',
        'tags' => ['VIP', 'Beta'],
        'audience_uuids' => [$audience->uuid],
        'consent_confirmed' => true,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('created', true)
        ->where('subscribed_audiences', 1)
        ->where('contact.email', 'ada@example.com')
        ->where('contact.memberships.0.audience.uuid', $audience->uuid)
        ->where('contact.memberships.0.status', 'subscribed')
        ->etc());

    $contact = Contact::query()->whereBelongsTo($team)->where('email', 'ada@example.com')->firstOrFail();

    MaildunServer::tool(GetContactTool::class, [
        'workspace' => $team->slug,
        'email' => 'ADA@example.com',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('contact.uuid', $contact->uuid)
        ->where('contact.tags', ['Beta', 'VIP'])
        ->etc());

    MaildunServer::tool(UpdateContactTool::class, [
        'workspace' => $team->slug,
        'uuid' => $contact->uuid,
        'email' => 'ada.lovelace@example.com',
        'tags' => ['VIP'],
    ])->assertOk();

    expect(Subscriber::query()->whereBelongsTo($audience)->value('email'))->toBe('ada.lovelace@example.com')
        ->and($contact->refresh()->tags->pluck('name')->all())->toBe(['VIP']);

    MaildunServer::tool(ListContactsTool::class, [
        'workspace' => $team->slug,
        'tag' => 'vip',
        'audience_uuid' => $audience->uuid,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('count', 1)
        ->where('contacts.0.uuid', $contact->uuid)
        ->etc());

    MaildunServer::tool(ListTagsTool::class, ['workspace' => $team->slug])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('count', 2)
            ->where('tags.1.name', 'VIP')
            ->where('tags.1.contacts_count', 1)
            ->etc());

    MaildunServer::tool(DeleteContactTool::class, [
        'workspace' => $team->slug,
        'uuid' => $contact->uuid,
        'confirm_email' => 'ada@example.com',
    ])->assertHasErrors(['must exactly match']);

    MaildunServer::tool(DeleteContactTool::class, [
        'workspace' => $team->slug,
        'uuid' => $contact->uuid,
        'confirm_email' => 'ada.lovelace@example.com',
    ])->assertOk();

    expect(Contact::query()->whereKey($contact->id)->exists())->toBeFalse()
        ->and(Subscriber::query()->whereBelongsTo($audience)->exists())->toBeFalse();
});

test('requires consent before a new contact joins audiences', function () {
    $team = Team::factory()->create();
    $audience = Audience::factory()->for($team)->create();

    MaildunServer::tool(CreateContactTool::class, [
        'workspace' => $team->slug,
        'email' => 'ada@example.com',
        'audience_uuids' => [$audience->uuid],
    ])->assertHasErrors(['consented to marketing email']);

    expect($team->contacts()->exists())->toBeFalse();
});

test('adds an existing contact to audiences without changing its profile', function () {
    $team = Team::factory()->create();
    $contact = Contact::factory()->for($team)->create(['email' => 'ada@example.com', 'first_name' => 'Ada']);
    $audience = Audience::factory()->for($team)->create();

    MaildunServer::tool(CreateContactTool::class, [
        'workspace' => $team->slug,
        'email' => 'ada@example.com',
    ])->assertHasErrors(['already exists']);

    MaildunServer::tool(CreateContactTool::class, [
        'workspace' => $team->slug,
        'email' => 'ada@example.com',
        'first_name' => 'Someone Else',
        'tags' => ['Overwritten'],
        'audience_uuids' => [$audience->uuid],
        'consent_confirmed' => true,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('created', false)
        ->where('contact.first_name', 'Ada')
        ->where('contact.tags', [])
        ->etc());

    expect($contact->subscribers()->whereBelongsTo($audience)->exists())->toBeTrue();
});

test('does not resolve contacts from another workspace', function () {
    $team = Team::factory()->create();
    $otherContact = Contact::factory()->create();

    MaildunServer::tool(GetContactTool::class, [
        'workspace' => $team->slug,
        'uuid' => $otherContact->uuid,
    ])->assertHasErrors(['contact was not found in this workspace']);
});

test('trims the names contact tools save', function () {
    $team = Team::factory()->create();

    MaildunServer::tool(CreateContactTool::class, [
        'workspace' => $team->slug,
        'email' => 'ada@example.com',
        'first_name' => '  Ada ',
        'last_name' => ' Lovelace  ',
    ])->assertOk();

    $contact = $team->contacts()->sole();

    expect($contact->only(['first_name', 'last_name']))->toBe(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

    MaildunServer::tool(UpdateContactTool::class, [
        'workspace' => $team->slug,
        'uuid' => $contact->uuid,
        'first_name' => ' Augusta Ada ',
        'last_name' => '   ',
    ])->assertOk();

    expect($contact->fresh()->only(['first_name', 'last_name']))->toBe(['first_name' => 'Augusta Ada', 'last_name' => null]);
});

test('pins, clears and releases a contact company through MCP', function () {
    $team = Team::factory()->create();
    $acme = Company::factory()->for($team)->create(['name' => 'Acme']);
    CompanyDomain::factory()->for($acme)->create(['domain' => 'acme.test']);
    $globex = Company::factory()->for($team)->create(['name' => 'Globex']);

    MaildunServer::tool(CreateContactTool::class, [
        'workspace' => $team->slug,
        'email' => 'ada@acme.test',
        'company_uuid' => $globex->uuid,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('contact.company.uuid', $globex->uuid)
        ->where('contact.company_assignment_mode', 'manual')
        ->etc());

    $contact = $team->contacts()->sole();

    MaildunServer::tool(UpdateContactTool::class, [
        'workspace' => $team->slug,
        'uuid' => $contact->uuid,
        'company_uuid' => null,
    ])->assertOk();

    expect($contact->fresh()->only(['company_id', 'company_assignment_mode']))
        ->toBe(['company_id' => null, 'company_assignment_mode' => ContactCompanyAssignmentMode::Manual]);

    MaildunServer::tool(UpdateContactTool::class, [
        'workspace' => $team->slug,
        'uuid' => $contact->uuid,
        'company_assignment_mode' => 'automatic',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('contact.company.uuid', $acme->uuid)
        ->where('contact.company_assignment_mode', 'automatic')
        ->etc());
});

test('rejects a company from another workspace or alongside automatic assignment', function () {
    $team = Team::factory()->create();
    $contact = Contact::factory()->for($team)->create();
    $company = Company::factory()->for($team)->create();

    MaildunServer::tool(UpdateContactTool::class, [
        'workspace' => $team->slug,
        'uuid' => $contact->uuid,
        'company_uuid' => Company::factory()->create()->uuid,
    ])->assertHasErrors(['The selected company was not found in this workspace.']);

    MaildunServer::tool(UpdateContactTool::class, [
        'workspace' => $team->slug,
        'uuid' => $contact->uuid,
        'company_assignment_mode' => 'automatic',
        'company_uuid' => $company->uuid,
    ])->assertHasErrors(['Leave company_uuid out when company_assignment_mode is automatic']);

    expect($contact->fresh()->company_id)->toBeNull();
});

test('filters contacts by company', function () {
    $team = Team::factory()->create();
    $company = Company::factory()->for($team)->create();
    $employee = Contact::factory()->for($team)->for($company)->create();
    $other = Contact::factory()->for($team)->create(['company_id' => null]);

    MaildunServer::tool(ListContactsTool::class, ['workspace' => $team->slug, 'company' => $company->uuid])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('count', 1)
            ->where('contacts.0.uuid', $employee->uuid)
            ->etc());

    MaildunServer::tool(ListContactsTool::class, ['workspace' => $team->slug, 'company' => 'unassigned'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('count', 1)
            ->where('contacts.0.uuid', $other->uuid)
            ->etc());

    MaildunServer::tool(ListContactsTool::class, ['workspace' => $team->slug, 'company' => 'someone'])
        ->assertHasErrors(['Use assigned, unassigned, or a company UUID from this workspace.']);
});

test('rejects a blank email without erasing the contact address', function () {
    $team = Team::factory()->create();
    $audience = Audience::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->create(['email' => 'ada@example.com']);
    Subscriber::factory()->for($audience)->for($contact)->create(['email' => 'ada@example.com']);

    foreach (['', '   '] as $email) {
        MaildunServer::tool(UpdateContactTool::class, [
            'workspace' => $team->slug,
            'uuid' => $contact->uuid,
            'email' => $email,
        ])->assertHasErrors();
    }

    expect($contact->fresh()->email)->toBe('ada@example.com')
        ->and($contact->subscribers()->sole()->email)->toBe('ada@example.com');
});
