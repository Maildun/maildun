<?php

use App\Mcp\Servers\MaildunServer;
use App\Mcp\Tools\ApplicationStatusTool;
use App\Mcp\Tools\Automations\GetAutomationTool;
use App\Mcp\Tools\Campaigns\DeleteCampaignTool;
use App\Mcp\Tools\Campaigns\GetCampaignTool;
use App\Mcp\Tools\Campaigns\ScheduleCampaignTool;
use App\Mcp\Tools\Campaigns\SendCampaignTool;
use App\Mcp\Tools\Campaigns\SendTestCampaignTool;
use App\Mcp\Tools\Campaigns\StopCampaignTool;
use App\Mcp\Tools\Campaigns\UnscheduleCampaignTool;
use App\Mcp\Tools\Campaigns\UpdateCampaignTool;
use App\Mcp\Tools\SavedSections\ListSavedSectionsTool;
use App\Mcp\Tools\Segments\DeleteSegmentTool;
use App\Mcp\Tools\Segments\GetSegmentTool;
use App\Mcp\Tools\Segments\UpdateSegmentTool;
use App\Mcp\Tools\Tags\ListTagsTool;
use App\Mcp\Tools\TransactionalEmails\DeleteTransactionalEmailTool;
use App\Mcp\Tools\TransactionalEmails\GetTransactionalEmailTool;
use App\Mcp\Tools\TransactionalEmails\UpdateTransactionalEmailTool;
use App\Mcp\Tools\Workspaces\ListWorkspacesTool;
use App\Models\Audience;
use App\Models\Automation;
use App\Models\Email;
use App\Models\SavedSection;
use App\Models\Segment;
use App\Models\Tag;
use App\Models\Team;
use App\Models\TransactionalEmail;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * Every tool on the server that takes a workspace slug. The email builder
 * tools validate their target first, so EmailBuilderToolsTest covers them.
 *
 * @return array<string, array{class-string}>
 */
function workspaceScopedMcpTools(): array
{
    $tools = (new ReflectionClass(MaildunServer::class))->getDefaultProperties()['tools'];

    return collect($tools)
        ->reject(fn (string $tool): bool => in_array($tool, [ApplicationStatusTool::class, ListWorkspacesTool::class], true)
            || str_starts_with($tool, 'App\\Mcp\\Tools\\EmailBuilder\\'))
        ->mapWithKeys(fn (string $tool): array => [class_basename($tool) => [$tool]])
        ->all();
}

test('every workspace tool refuses a workspace the user does not belong to', function (string $tool) {
    $user = User::factory()->create();
    Team::factory()->create(['slug' => 'hidden-workspace']);

    MaildunServer::actingAs($user)
        ->tool($tool, ['workspace' => 'hidden-workspace'])
        ->assertHasErrors(['workspace was not found']);
})->with(workspaceScopedMcpTools());

test('record tools refuse a record from another workspace', function (string $tool, Closure $foreignRecord, string $resource) {
    $user = User::factory()->create();
    $record = $foreignRecord(Team::factory()->create());

    MaildunServer::actingAs($user)
        ->tool($tool, [
            'workspace' => $user->currentTeam->slug,
            'uuid' => $record->uuid,
            'name' => 'Changed',
            'confirm_name' => $record->name,
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ])
        ->assertHasErrors(["The selected {$resource} was not found in this workspace."]);

    expect($record->fresh()->name)->not->toBe('Changed');
})->with([
    'get campaign' => [GetCampaignTool::class, fn (Team $team) => Email::factory()->for($team)->create(), 'campaign'],
    'update campaign' => [UpdateCampaignTool::class, fn (Team $team) => Email::factory()->for($team)->create(), 'campaign'],
    'delete campaign' => [DeleteCampaignTool::class, fn (Team $team) => Email::factory()->for($team)->create(), 'campaign'],
    'test campaign' => [SendTestCampaignTool::class, fn (Team $team) => Email::factory()->for($team)->create(), 'campaign'],
    'send campaign' => [SendCampaignTool::class, fn (Team $team) => Email::factory()->for($team)->create(), 'campaign'],
    'schedule campaign' => [ScheduleCampaignTool::class, fn (Team $team) => Email::factory()->for($team)->create(), 'campaign'],
    'unschedule campaign' => [UnscheduleCampaignTool::class, fn (Team $team) => Email::factory()->for($team)->create(), 'campaign'],
    'stop campaign' => [StopCampaignTool::class, fn (Team $team) => Email::factory()->for($team)->create(), 'campaign'],
    'get transactional email' => [GetTransactionalEmailTool::class, fn (Team $team) => TransactionalEmail::factory()->for($team)->create(), 'transactional email'],
    'update transactional email' => [UpdateTransactionalEmailTool::class, fn (Team $team) => TransactionalEmail::factory()->for($team)->create(), 'transactional email'],
    'delete transactional email' => [DeleteTransactionalEmailTool::class, fn (Team $team) => TransactionalEmail::factory()->for($team)->create(), 'transactional email'],
    'get segment' => [GetSegmentTool::class, fn (Team $team) => Segment::factory()->for(Audience::factory()->for($team))->create(), 'segment'],
    'update segment' => [UpdateSegmentTool::class, fn (Team $team) => Segment::factory()->for(Audience::factory()->for($team))->create(), 'segment'],
    'delete segment' => [DeleteSegmentTool::class, fn (Team $team) => Segment::factory()->for(Audience::factory()->for($team))->create(), 'segment'],
    'get automation' => [GetAutomationTool::class, fn (Team $team) => Automation::factory()->for($team)->create(), 'automation'],
]);

test('lists only the workspace tags', function () {
    $team = Team::factory()->create();
    Tag::factory()->for($team)->create(['name' => 'VIP']);
    Tag::factory()->create(['name' => 'Other workspace tag']);

    MaildunServer::tool(ListTagsTool::class, ['workspace' => $team->slug])
        ->assertOk()
        ->assertDontSee('Other workspace tag')
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('count', 1)
            ->where('tags.0.name', 'VIP')
            ->etc());
});

test('lists only the workspace saved sections', function () {
    $team = Team::factory()->create();
    SavedSection::factory()->for($team)->create(['name' => 'Brand footer']);
    SavedSection::factory()->create(['name' => 'Other workspace header']);

    MaildunServer::tool(ListSavedSectionsTool::class, ['workspace' => $team->slug])
        ->assertOk()
        ->assertDontSee('Other workspace header')
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('count', 1)
            ->where('sections.0.name', 'Brand footer')
            ->etc());
});
