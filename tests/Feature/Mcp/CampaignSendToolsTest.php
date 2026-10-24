<?php

use App\Enums\EmailStatus;
use App\Enums\TestSendStatus;
use App\Jobs\SendCampaignTestEmail;
use App\Mcp\Servers\MaildunServer;
use App\Mcp\Tools\Campaigns\ScheduleCampaignTool;
use App\Mcp\Tools\Campaigns\SendCampaignTool;
use App\Mcp\Tools\Campaigns\SendTestCampaignTool;
use App\Mcp\Tools\Campaigns\StopCampaignTool;
use App\Mcp\Tools\Campaigns\UnscheduleCampaignTool;
use App\Models\Audience;
use App\Models\Email;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\Fluent\AssertableJson;

function mcpSendableCampaign(Team $team, array $attributes = []): Email
{
    TeamEmailIntegration::factory()->for($team)->create();
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->count(2)->for($audience)->create();

    return Email::factory()->for($team)->create([
        'name' => 'October Launch',
        'audience_id' => $audience->id,
        'subject' => 'Hello',
        'html' => '<p>Hi</p>',
        ...$attributes,
    ]);
}

test('queues a test copy of a draft campaign', function () {
    Queue::fake();
    $team = Team::factory()->create();
    $campaign = mcpSendableCampaign($team);

    MaildunServer::tool(SendTestCampaignTool::class, [
        'workspace' => $team->slug,
        'uuid' => $campaign->uuid,
        'to' => 'reviewer@example.com',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('queued', true)
        ->where('to', 'reviewer@example.com')
        ->etc());

    Queue::assertPushed(SendCampaignTestEmail::class, fn (SendCampaignTestEmail $job): bool => $job->recipient === 'reviewer@example.com');
    expect($campaign->refresh())
        ->last_test_status->toBe(TestSendStatus::Queued)
        ->last_test_recipient->toBe('reviewer@example.com');
});

test('refuses a test copy when the workspace has no email provider', function () {
    Queue::fake();
    $team = Team::factory()->create();
    $campaign = Email::factory()->for($team)->create();

    MaildunServer::tool(SendTestCampaignTool::class, [
        'workspace' => $team->slug,
        'uuid' => $campaign->uuid,
        'to' => 'reviewer@example.com',
    ])->assertHasErrors(['Connect and test an email provider for this workspace.']);

    Queue::assertNothingPushed();
    expect($campaign->refresh()->last_test_status)->toBeNull();
});

test('sends a campaign only after exact-name confirmation', function () {
    Bus::fake();
    $team = Team::factory()->create();
    $campaign = mcpSendableCampaign($team);

    MaildunServer::tool(SendCampaignTool::class, [
        'workspace' => $team->slug,
        'uuid' => $campaign->uuid,
        'confirm_name' => 'october launch',
    ])->assertHasErrors(['must exactly match']);

    expect($campaign->refresh()->status)->toBe(EmailStatus::Draft);

    MaildunServer::tool(SendCampaignTool::class, [
        'workspace' => $team->slug,
        'uuid' => $campaign->uuid,
        'confirm_name' => 'October Launch',
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('queued', true)
        ->where('campaign.status', 'queued')
        ->where('campaign.recipient_count', 2)
        ->etc());

    Bus::assertBatchCount(1);

    MaildunServer::tool(SendCampaignTool::class, [
        'workspace' => $team->slug,
        'uuid' => $campaign->uuid,
        'confirm_name' => 'October Launch',
    ])->assertHasErrors(['already been queued']);
});

test('refuses to send a campaign that is not ready', function () {
    Bus::fake();
    $team = Team::factory()->create();
    $campaign = mcpSendableCampaign($team, ['html' => null]);

    MaildunServer::tool(SendCampaignTool::class, [
        'workspace' => $team->slug,
        'uuid' => $campaign->uuid,
        'confirm_name' => $campaign->name,
    ])->assertHasErrors(['complete the subject']);

    Bus::assertNothingBatched();
    expect($campaign->refresh()->status)->toBe(EmailStatus::Draft);
});

test('schedules and unschedules a draft campaign', function () {
    $team = Team::factory()->create();
    $campaign = mcpSendableCampaign($team);
    $sendAt = now()->addDay()->startOfMinute();

    MaildunServer::tool(ScheduleCampaignTool::class, [
        'workspace' => $team->slug,
        'uuid' => $campaign->uuid,
        'scheduled_at' => now()->subHour()->toIso8601String(),
        'confirm_name' => $campaign->name,
    ])->assertHasErrors(['at least a minute from now']);

    MaildunServer::tool(ScheduleCampaignTool::class, [
        'workspace' => $team->slug,
        'uuid' => $campaign->uuid,
        'scheduled_at' => $sendAt->toIso8601String(),
        'confirm_name' => $campaign->name,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('campaign.status', 'draft')
        ->where('campaign.scheduled_at', $sendAt->toISOString())
        ->etc());

    MaildunServer::tool(UnscheduleCampaignTool::class, [
        'workspace' => $team->slug,
        'uuid' => $campaign->uuid,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('campaign.scheduled_at', null)
        ->etc());
});

test('stops a sending campaign', function () {
    $team = Team::factory()->create();
    $campaign = mcpSendableCampaign($team, ['status' => EmailStatus::Sending]);

    MaildunServer::tool(StopCampaignTool::class, [
        'workspace' => $team->slug,
        'uuid' => $campaign->uuid,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('cancelled_recipients', 0)
        ->where('campaign.status', 'stopped')
        ->etc());

    MaildunServer::tool(StopCampaignTool::class, [
        'workspace' => $team->slug,
        'uuid' => $campaign->uuid,
    ])->assertHasErrors(['not sending']);
});
