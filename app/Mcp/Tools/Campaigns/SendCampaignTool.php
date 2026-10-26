<?php

namespace App\Mcp\Tools\Campaigns;

use App\Actions\Emails\StartEmailSend;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
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
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Immediately queues a draft campaign for delivery to every sendable recipient after exact-name confirmation. This cannot be undone; use send-test-campaign first and stop-campaign to halt it.')]
#[IsDestructive]
#[IsOpenWorld]
#[IsReadOnly(false)]
#[Name('send-campaign')]
class SendCampaignTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
        private StartEmailSend $startEmailSend,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $campaign = $this->context->campaign($request, $team);
        $this->context->authorize($request, 'send', $campaign);
        $request->validate([
            'confirm_name' => ['required', 'string', Rule::in([$campaign->name])],
        ], [
            'confirm_name.in' => 'The confirmation name must exactly match the campaign name.',
        ]);

        $this->startEmailSend->handle($campaign);
        $campaign->refresh()->loadMissing(['audience:id,uuid,name', 'segment:id,uuid,name']);

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'queued' => true,
            'campaign' => $this->payload->campaignSummary($campaign),
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
            'confirm_name' => $schema->string()->description('Exact campaign name required to confirm sending.')->max(255)->required(),
        ];
    }
}
