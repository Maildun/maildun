<?php

use App\Enums\EmailStatus;
use App\Mcp\Servers\MaildunServer;
use App\Mcp\Tools\Segments\CreateSegmentTool;
use App\Mcp\Tools\Segments\DeleteSegmentTool;
use App\Mcp\Tools\Segments\GetSegmentTool;
use App\Mcp\Tools\Segments\ListSegmentsTool;
use App\Mcp\Tools\Segments\UpdateSegmentTool;
use App\Mcp\Tools\Subscribers\ListSubscribersTool;
use App\Models\Audience;
use App\Models\Email;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\Team;
use Illuminate\Testing\Fluent\AssertableJson;

test('manages a segment through its MCP lifecycle', function () {
    $team = Team::factory()->create();
    $audience = Audience::factory()->for($team)->create();
    $member = Subscriber::factory()->for($audience)->create(['email' => 'ada@example.com']);
    Subscriber::factory()->for($audience)->create(['email' => 'grace@other.test']);

    MaildunServer::tool(CreateSegmentTool::class, [
        'workspace' => $team->slug,
        'audience_uuid' => $audience->uuid,
        'name' => 'Example domain',
        'rules' => [['field' => 'email', 'operator' => 'contains', 'value' => '@example.com']],
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('segment.name', 'Example domain')
        ->where('segment.match_type', 'all')
        ->where('segment.subscribers_count', 1)
        ->etc());

    $segment = Segment::query()->whereBelongsTo($audience)->sole();

    MaildunServer::tool(ListSubscribersTool::class, [
        'workspace' => $team->slug,
        'audience_uuid' => $audience->uuid,
        'segment_uuid' => $segment->uuid,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('count', 1)
        ->where('subscribers.0.uuid', $member->uuid)
        ->etc());

    MaildunServer::tool(UpdateSegmentTool::class, [
        'workspace' => $team->slug,
        'uuid' => $segment->uuid,
        'rules' => [['field' => 'status', 'operator' => 'equals', 'value' => 'subscribed']],
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('segment.subscribers_count', 2)
        ->where('segment.name', 'Example domain')
        ->etc());

    MaildunServer::tool(ListSegmentsTool::class, [
        'workspace' => $team->slug,
        'audience_uuid' => $audience->uuid,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('count', 1)
        ->where('segments.0.uuid', $segment->uuid)
        ->etc());

    MaildunServer::tool(GetSegmentTool::class, [
        'workspace' => $team->slug,
        'uuid' => $segment->uuid,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('segment.rules.0.field', 'status')
        ->etc());

    MaildunServer::tool(DeleteSegmentTool::class, [
        'workspace' => $team->slug,
        'uuid' => $segment->uuid,
        'confirm_name' => 'Example domain',
    ])->assertOk();

    expect(Segment::query()->whereKey($segment->id)->exists())->toBeFalse()
        ->and($audience->subscribers()->count())->toBe(2);
});

test('rejects an operator that does not fit the rule field', function () {
    $team = Team::factory()->create();
    $audience = Audience::factory()->for($team)->create();

    MaildunServer::tool(CreateSegmentTool::class, [
        'workspace' => $team->slug,
        'audience_uuid' => $audience->uuid,
        'name' => 'Broken',
        'rules' => [['field' => 'status', 'operator' => 'contains', 'value' => 'sub']],
    ])->assertHasErrors(['not valid for this field']);

    expect($audience->segments()->exists())->toBeFalse();
});

test('refuses to delete a segment a sending or scheduled campaign targets', function (array $campaignAttributes) {
    $team = Team::factory()->create();
    $audience = Audience::factory()->for($team)->create();
    $segment = Segment::factory()->for($audience)->create(['name' => 'Customers']);
    Email::factory()->for($team)->create([
        'name' => 'Launch',
        'audience_id' => $audience->id,
        'segment_id' => $segment->id,
        ...$campaignAttributes,
    ]);

    MaildunServer::tool(DeleteSegmentTool::class, [
        'workspace' => $team->slug,
        'uuid' => $segment->uuid,
        'confirm_name' => 'Customers',
    ])->assertHasErrors(['used by scheduled or sending campaigns: Launch']);

    expect(Segment::query()->whereKey($segment->id)->exists())->toBeTrue();
})->with([
    'sending' => [['status' => EmailStatus::Sending]],
    'scheduled' => [['status' => EmailStatus::Draft, 'scheduled_at' => now()->addDay()]],
]);
