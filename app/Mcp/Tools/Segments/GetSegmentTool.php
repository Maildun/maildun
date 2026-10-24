<?php

namespace App\Mcp\Tools\Segments;

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
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Gets one segment by UUID with its rules and matching subscriber count. Use list-subscribers with segment_uuid to see its members.')]
#[IsOpenWorld(false)]
#[IsReadOnly]
#[Name('get-segment')]
class GetSegmentTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $segment = $this->context->segment($request, $team);
        $segment->load('audience:id,uuid,name')->loadCount('subscribers');

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'segment' => $this->payload->segment($segment),
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
            'uuid' => $schema->string()->description('Segment UUID.')->format('uuid')->required(),
        ];
    }
}
