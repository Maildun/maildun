<?php

use App\Enums\EmailStatus;
use App\Enums\SubscriberLanguage;
use App\Mcp\Servers\MaildunServer;
use App\Mcp\Tools\Audiences\CreateAudienceTool;
use App\Mcp\Tools\Audiences\DeleteAudienceTool;
use App\Mcp\Tools\Audiences\GetAudienceTool;
use App\Mcp\Tools\Audiences\ListAudiencesTool;
use App\Mcp\Tools\Audiences\UpdateAudienceTool;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Email;
use App\Models\Team;
use App\Models\TransactionalEmail;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

test('manages an audience through its MCP lifecycle', function () {
    $team = Team::factory()->create(['slug' => 'growth']);

    MaildunServer::tool(CreateAudienceTool::class, [
        'workspace' => $team->slug,
        'name' => 'Product Updates',
        'description' => 'Customers interested in product news.',
        'first_name_mode' => 'required',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('workspace.slug', 'growth')
        ->where('audience.name', 'Product Updates')
        ->where('audience.first_name_mode', 'required')
        ->etc());

    $audience = Audience::query()->whereBelongsTo($team)->where('name', 'Product Updates')->firstOrFail();

    MaildunServer::tool(GetAudienceTool::class, [
        'workspace' => $team->slug,
        'uuid' => $audience->uuid,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('audience.uuid', $audience->uuid)
        ->where('audience.subscribers_count', 0)
        ->etc());

    MaildunServer::tool(UpdateAudienceTool::class, [
        'workspace' => $team->slug,
        'uuid' => $audience->uuid,
        'name' => 'Release Notes',
        'description' => null,
    ])->assertOk();

    expect($audience->refresh())
        ->name->toBe('Release Notes')
        ->description->toBeNull();

    MaildunServer::tool(ListAudiencesTool::class, [
        'workspace' => $team->slug,
        'search' => 'Release',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('count', 1)
        ->where('audiences.0.uuid', $audience->uuid)
        ->etc());

    MaildunServer::tool(DeleteAudienceTool::class, [
        'workspace' => $team->slug,
        'uuid' => $audience->uuid,
        'confirm_name' => 'Release Notes',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('deleted', true)
        ->where('audience.uuid', $audience->uuid)
        ->etc());

    expect(Audience::query()->whereKey($audience->id)->exists())->toBeFalse();
});

test('does not expose an audience through another workspace', function () {
    $owner = Team::factory()->create(['slug' => 'owner']);
    $other = Team::factory()->create(['slug' => 'other']);
    $audience = Audience::factory()->for($owner)->create();

    MaildunServer::tool(GetAudienceTool::class, [
        'workspace' => $other->slug,
        'uuid' => $audience->uuid,
    ])->assertHasErrors(['not found in this workspace']);
});

test('configures audience languages through MCP', function () {
    $team = Team::factory()->create();

    MaildunServer::tool(CreateAudienceTool::class, [
        'workspace' => $team->slug,
        'name' => 'Global',
        'language_mode' => 'optional',
    ])->assertHasErrors(['Allow at least one language']);

    MaildunServer::tool(CreateAudienceTool::class, [
        'workspace' => $team->slug,
        'name' => 'Global',
        'language_mode' => 'optional',
        'allowed_languages' => ['en', 'id'],
        'default_language' => 'en',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('audience.language_mode', 'optional')
        ->where('audience.allowed_languages', ['en', 'id'])
        ->where('audience.default_language', 'en')
        ->etc());

    $audience = Audience::query()->whereBelongsTo($team)->sole();

    MaildunServer::tool(UpdateAudienceTool::class, [
        'workspace' => $team->slug,
        'uuid' => $audience->uuid,
        'allowed_languages' => ['id'],
    ])->assertHasErrors(['default_language must be one of allowed_languages']);

    MaildunServer::tool(UpdateAudienceTool::class, [
        'workspace' => $team->slug,
        'uuid' => $audience->uuid,
        'allowed_languages' => ['id'],
        'default_language' => 'id',
    ])->assertOk();

    expect($audience->refresh())
        ->language_mode->value->toBe('optional')
        ->allowed_languages->toBe(['id'])
        ->default_language->toBe(SubscriberLanguage::Indonesian);
});

test('rejects a null name field mode when creating an audience', function () {
    $team = Team::factory()->create();

    MaildunServer::tool(CreateAudienceTool::class, [
        'workspace' => $team->slug,
        'name' => 'Product Updates',
        'first_name_mode' => null,
    ])->assertHasErrors(['first name mode']);

    expect($team->audiences()->exists())->toBeFalse();
});

test('refuses to delete an audience a sending campaign targets', function () {
    $team = Team::factory()->create();
    $audience = Audience::factory()->for($team)->create(['name' => 'Customers']);
    Email::factory()->for($team)->create(['audience_id' => $audience->id, 'name' => 'Launch', 'status' => EmailStatus::Sending]);

    MaildunServer::tool(DeleteAudienceTool::class, [
        'workspace' => $team->slug,
        'uuid' => $audience->uuid,
        'confirm_name' => 'Customers',
    ])->assertHasErrors(['This audience is used by scheduled or sending campaigns: Launch.']);

    $this->assertModelExists($audience);
});

test('trims audience names so the web delete confirmation matches them', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    MaildunServer::tool(CreateAudienceTool::class, [
        'workspace' => $team->slug,
        'name' => '  Customers ',
    ])->assertOk();

    $audience = $team->audiences()->sole();

    expect($audience->name)->toBe('Customers');

    MaildunServer::tool(UpdateAudienceTool::class, [
        'workspace' => $team->slug,
        'uuid' => $audience->uuid,
        'name' => ' Clients  ',
    ])->assertOk();

    $this->actingAs($user)
        ->delete(route('audiences.destroy', [$team, $audience]), ['name' => 'Clients'])
        ->assertRedirect(route('audiences.index', $team));

    $this->assertModelMissing($audience);
});

test('shows double opt-in and custom attributes on an audience', function () {
    $team = Team::factory()->create();
    $confirmation = TransactionalEmail::factory()->for($team)->published()->create(['name' => 'Confirm signup']);
    $audience = Audience::factory()->for($team)->create([
        'double_opt_in' => true,
        'double_opt_in_email_id' => $confirmation->id,
    ]);
    AudienceAttribute::factory()->for($audience)->create(['name' => 'Plan', 'key' => 'plan', 'required' => true, 'position' => 2]);
    AudienceAttribute::factory()->for($audience)->number()->create(['name' => 'Seats', 'key' => 'seats', 'required' => false, 'position' => 1]);

    MaildunServer::tool(GetAudienceTool::class, [
        'workspace' => $team->slug,
        'uuid' => $audience->uuid,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('audience.double_opt_in', true)
        ->where('audience.double_opt_in_email', ['uuid' => $confirmation->uuid, 'name' => 'Confirm signup'])
        ->where('audience.attributes.0.key', 'seats')
        ->where('audience.attributes.0.type', 'number')
        ->where('audience.attributes.0.required', false)
        ->where('audience.attributes.1.key', 'plan')
        ->where('audience.attributes.1.required', true)
        ->etc());
});

test('turns double opt-in on and off through MCP', function () {
    $team = Team::factory()->create();
    $audience = Audience::factory()->for($team)->create(['double_opt_in' => false]);
    $confirmation = TransactionalEmail::factory()->for($team)->published()->create();
    $draft = TransactionalEmail::factory()->for($team)->create();

    MaildunServer::tool(UpdateAudienceTool::class, [
        'workspace' => $team->slug,
        'uuid' => $audience->uuid,
        'double_opt_in' => true,
    ])->assertHasErrors(['Double opt-in needs a published transactional email for the confirmation link.']);

    MaildunServer::tool(UpdateAudienceTool::class, [
        'workspace' => $team->slug,
        'uuid' => $audience->uuid,
        'double_opt_in' => true,
        'double_opt_in_email_uuid' => $draft->uuid,
    ])->assertHasErrors(['Choose a published transactional email from this workspace.']);

    expect($audience->fresh()->double_opt_in)->toBeFalse();

    MaildunServer::tool(UpdateAudienceTool::class, [
        'workspace' => $team->slug,
        'uuid' => $audience->uuid,
        'double_opt_in' => true,
        'double_opt_in_email_uuid' => $confirmation->uuid,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('audience.double_opt_in', true)
        ->where('audience.double_opt_in_email.uuid', $confirmation->uuid)
        ->etc());

    expect($audience->fresh()->double_opt_in_email_id)->toBe($confirmation->id);

    MaildunServer::tool(UpdateAudienceTool::class, [
        'workspace' => $team->slug,
        'uuid' => $audience->uuid,
        'double_opt_in' => false,
    ])->assertOk();

    expect($audience->fresh()->only(['double_opt_in', 'double_opt_in_email_id']))
        ->toBe(['double_opt_in' => false, 'double_opt_in_email_id' => null]);
});
