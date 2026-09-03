<?php

namespace App\Mcp\Tools\Campaigns;

use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Storage;
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

#[Description('Soft-deletes a campaign and removes its attachment files after exact-name confirmation.')]
#[IsDestructive]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('delete-campaign')]
class DeleteCampaignTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $campaign = $this->context->campaign($request, $team);
        $this->context->authorize($request, 'delete', $campaign);
        $request->validate([
            'confirm_name' => ['required', 'string', Rule::in([$campaign->name])],
        ], [
            'confirm_name.in' => 'The confirmation name must exactly match the campaign name.',
        ]);

        $deleted = ['uuid' => $campaign->uuid, 'name' => $campaign->name];
        $campaign->loadMissing('attachments');
        $campaign->attachments->each(
            fn ($attachment) => Storage::disk($attachment->disk)->delete($attachment->path),
        );
        $campaign->attachments()->delete();
        $campaign->delete();

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'deleted' => true,
            'campaign' => $deleted,
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
            'confirm_name' => $schema->string()->description('Exact campaign name required to confirm deletion.')->max(255)->required(),
        ];
    }
}
