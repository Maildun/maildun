<?php

use App\Enums\EmailEditor;
use App\Enums\EmailStatus;
use App\Mcp\Servers\MaildunServer;
use App\Mcp\Tools\Campaigns\CreateCampaignTool;
use App\Mcp\Tools\Campaigns\DeleteCampaignTool;
use App\Mcp\Tools\Campaigns\GetCampaignTool;
use App\Mcp\Tools\Campaigns\ListCampaignsTool;
use App\Mcp\Tools\Campaigns\UpdateCampaignTool;
use App\Models\Audience;
use App\Models\Email;
use App\Models\Segment;
use App\Models\Team;
use Illuminate\Testing\Fluent\AssertableJson;

test('manages a campaign through its MCP lifecycle', function () {
    $team = Team::factory()->create(['slug' => 'marketing']);
    $audience = Audience::factory()->for($team)->create();
    $segment = Segment::factory()->for($audience)->create();

    MaildunServer::tool(CreateCampaignTool::class, [
        'workspace' => $team->slug,
        'name' => 'September Launch',
        'subject' => 'Our September launch',
        'html' => '<p>Hello world</p>',
        'audience_uuid' => $audience->uuid,
        'segment_uuid' => $segment->uuid,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('campaign.name', 'September Launch')
        ->where('campaign.status', 'draft')
        ->where('campaign.audience.uuid', $audience->uuid)
        ->where('campaign.segment.uuid', $segment->uuid)
        ->etc());

    $campaign = Email::query()->whereBelongsTo($team)->where('name', 'September Launch')->firstOrFail();

    MaildunServer::tool(GetCampaignTool::class, [
        'workspace' => $team->slug,
        'uuid' => $campaign->uuid,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('campaign.uuid', $campaign->uuid)
        ->where('campaign.html', '<p>Hello world</p>')
        ->etc());

    MaildunServer::tool(UpdateCampaignTool::class, [
        'workspace' => $team->slug,
        'uuid' => $campaign->uuid,
        'subject' => 'Updated launch subject',
        'segment_uuid' => null,
    ])->assertOk();

    expect($campaign->refresh())
        ->subject->toBe('Updated launch subject')
        ->segment_id->toBeNull();

    MaildunServer::tool(ListCampaignsTool::class, [
        'workspace' => $team->slug,
        'status' => 'draft',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('count', 1)
        ->where('campaigns.0.uuid', $campaign->uuid)
        ->etc());

    $campaign->update(['status' => EmailStatus::Sent]);

    MaildunServer::tool(UpdateCampaignTool::class, [
        'workspace' => $team->slug,
        'uuid' => $campaign->uuid,
        'subject' => 'Too late',
    ])->assertHasErrors(['Only draft campaigns']);

    MaildunServer::tool(DeleteCampaignTool::class, [
        'workspace' => $team->slug,
        'uuid' => $campaign->uuid,
        'confirm_name' => $campaign->name,
    ])->assertOk();

    expect($campaign->refresh()->trashed())->toBeTrue();
});

test('rejects a campaign segment from another audience', function () {
    $team = Team::factory()->create(['slug' => 'segments']);
    $audience = Audience::factory()->for($team)->create();
    $otherAudience = Audience::factory()->for($team)->create();
    $segment = Segment::factory()->for($otherAudience)->create();

    MaildunServer::tool(CreateCampaignTool::class, [
        'workspace' => $team->slug,
        'name' => 'Invalid selection',
        'audience_uuid' => $audience->uuid,
        'segment_uuid' => $segment->uuid,
    ])->assertHasErrors(['not part of that audience']);
});

test('keeps plain text source when campaigns are managed through MCP', function () {
    $team = Team::factory()->create([
        'slug' => 'plain-campaigns',
        'email_editor' => EmailEditor::PlainText,
    ]);

    MaildunServer::tool(CreateCampaignTool::class, [
        'workspace' => $team->slug,
        'name' => 'Plain update',
        'source' => "Hello there\nSecond line",
        'html' => '<div>Hello there<br>Second line</div>',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('campaign.editor', EmailEditor::PlainText->value)
        ->where('campaign.source', "Hello there\nSecond line")
        ->where('campaign.plain_text', "Hello there\nSecond line")
        ->etc());
});

test('drafts a source-based campaign through MCP before its body is written', function () {
    $team = Team::factory()->create([
        'slug' => 'plain-drafts',
        'email_editor' => EmailEditor::PlainText,
    ]);

    MaildunServer::tool(CreateCampaignTool::class, [
        'workspace' => $team->slug,
        'name' => 'Empty draft',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('campaign.editor', EmailEditor::PlainText->value)
        ->where('campaign.status', 'draft')
        ->where('campaign.source', '')
        ->where('campaign.plain_text', null)
        ->etc());

    $campaign = Email::query()->whereBelongsTo($team)->where('name', 'Empty draft')->firstOrFail();

    MaildunServer::tool(UpdateCampaignTool::class, [
        'workspace' => $team->slug,
        'uuid' => $campaign->uuid,
        'source' => 'Hello there',
        'html' => '<div>Hello there</div>',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('campaign.source', 'Hello there')
        ->where('campaign.plain_text', 'Hello there')
        ->etc());

    MaildunServer::tool(UpdateCampaignTool::class, [
        'workspace' => $team->slug,
        'uuid' => $campaign->uuid,
        'subject' => 'Renamed while empty',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('campaign.subject', 'Renamed while empty')
        ->where('campaign.source', 'Hello there')
        ->etc());

    MaildunServer::tool(UpdateCampaignTool::class, [
        'workspace' => $team->slug,
        'uuid' => $campaign->uuid,
        'source' => '',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('campaign.source', null)
        ->where('campaign.plain_text', null)
        ->etc());
});

test('refuses to delete a campaign that is still sending', function () {
    $team = Team::factory()->create(['slug' => 'sending-workspace']);
    $campaign = Email::factory()->for($team)->create(['status' => EmailStatus::Sending]);

    MaildunServer::tool(DeleteCampaignTool::class, [
        'workspace' => $team->slug,
        'uuid' => $campaign->uuid,
        'confirm_name' => $campaign->name,
    ])->assertHasErrors(['Wait until this campaign finishes sending']);

    $this->assertNotSoftDeleted($campaign);
});
