<?php

namespace App\Mcp\Tools\Campaigns;

use App\Actions\Emails\StopEmailSend;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Stops a queued or sending campaign. Recipients not yet handed to the provider are cancelled; deliveries already sent cannot be recalled.')]
#[IsDestructive]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('stop-campaign')]
class StopCampaignTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
        private StopEmailSend $stopEmailSend,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $campaign = $this->context->campaign($request, $team);
        $this->context->authorize($request, 'send', $campaign);

        $cancelled = $this->stopEmailSend->handle($campaign);
        $campaign->refresh()->loadMissing(['audience:id,uuid,name', 'segment:id,uuid,name']);

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'cancelled_recipients' => $cancelled,
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
            'uuid' => $schema->string()->description('Campaign UUID.')->format('uuid')->required(),
        ];
    }
}
