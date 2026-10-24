<?php

namespace App\Mcp\Tools\Campaigns;

use App\Actions\Emails\ScheduleEmailSend;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Schedules a draft campaign to send automatically at a future time, or moves an existing schedule, after exact-name confirmation. Send readiness is checked now and again when the time comes.')]
#[IsDestructive]
#[IsIdempotent]
#[IsOpenWorld]
#[IsReadOnly(false)]
#[Name('schedule-campaign')]
class ScheduleCampaignTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
        private ScheduleEmailSend $schedules,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $campaign = $this->context->campaign($request, $team);
        $this->context->authorize($request, 'send', $campaign);
        $validated = $request->validate([
            'confirm_name' => ['required', 'string', Rule::in([$campaign->name])],
            'scheduled_at' => ['required', 'date', 'after:+1 minute', 'before:+1 year'],
        ], [
            'confirm_name.in' => 'The confirmation name must exactly match the campaign name.',
            'scheduled_at.after' => 'Pick a time at least a minute from now.',
            'scheduled_at.before' => 'Pick a time within the next year.',
        ]);

        $this->schedules->schedule($campaign, CarbonImmutable::parse($validated['scheduled_at'])->utc());
        $campaign->refresh()->loadMissing(['audience:id,uuid,name', 'segment:id,uuid,name']);

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'campaign' => $this->payload->campaign($campaign),
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace' => $schema->string()->description('Workspace slug.')->max(255)->required(),
            'uuid' => $schema->string()->description('Draft campaign UUID.')->format('uuid')->required(),
            'scheduled_at' => $schema->string()->description('ISO 8601 send time with an offset, between one minute and one year from now.')->format('date-time')->required(),
            'confirm_name' => $schema->string()->description('Exact campaign name required to confirm scheduling.')->max(255)->required(),
        ];
    }
}
