<?php

namespace App\Mcp\Tools\Audiences;

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

#[Description('Permanently deletes an audience after its exact name is supplied as confirmation.')]
#[IsDestructive]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('delete-audience')]
class DeleteAudienceTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $audience = $this->context->audience($request, $team);
        $this->context->authorize($request, 'delete', $audience);
        $request->validate([
            'confirm_name' => ['required', 'string', Rule::in([$audience->name])],
        ], [
            'confirm_name.in' => 'The confirmation name must exactly match the audience name.',
        ]);

        $deleted = ['uuid' => $audience->uuid, 'name' => $audience->name];
        $audience->delete();

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'deleted' => true,
            'audience' => $deleted,
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
            'uuid' => $schema->string()->description('Audience UUID.')->format('uuid')->required(),
            'confirm_name' => $schema->string()->description('Exact audience name required to confirm deletion.')->max(255)->required(),
        ];
    }
}
