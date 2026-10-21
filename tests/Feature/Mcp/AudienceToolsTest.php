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
use App\Models\Email;
use App\Models\Team;
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
