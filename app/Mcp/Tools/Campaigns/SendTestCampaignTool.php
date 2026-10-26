<?php

namespace App\Mcp\Tools\Campaigns;

use App\Actions\Emails\QueueCampaignTestEmail;
use App\Enums\EmailStatus;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Queues one test copy of a draft campaign to a single email address, rendered with sample merge data.')]
#[IsDestructive(false)]
#[IsOpenWorld]
#[IsReadOnly(false)]
#[Name('send-test-campaign')]
class SendTestCampaignTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
        private QueueCampaignTestEmail $queueTestEmail,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $campaign = $this->context->campaign($request, $team);
        $this->context->authorize($request, 'sendTest', $campaign);
        $validated = $request->validate([
            'to' => ['required', 'string', 'email', 'max:255'],
        ]);

        if ($campaign->status !== EmailStatus::Draft) {
            throw ValidationException::withMessages([
                'uuid' => 'Only draft campaigns can send test copies.',
            ]);
        }

        $this->queueTestEmail->handle($campaign, $validated['to']);
        $campaign->loadMissing(['audience:id,uuid,name', 'segment:id,uuid,name']);

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'queued' => true,
            'to' => $validated['to'],
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
            'to' => $schema->string()->description('Email address that receives the test copy.')->format('email')->max(255)->required(),
        ];
    }
}
