<?php

namespace App\Mcp\Tools\Segments;

use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\Segment;
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

#[Description('Lists the segments of one audience with their rules and matching subscriber counts.')]
#[IsOpenWorld(false)]
#[IsReadOnly]
#[Name('list-segments')]
class ListSegmentsTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $audience = $this->context->audienceFrom($request, $team);

        $segments = $audience->segments()
            ->with('audience:id,uuid,name')
            ->withCount('subscribers')
            ->orderBy('name')
            ->get();

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'count' => $segments->count(),
            'segments' => $segments
                ->map(fn (Segment $segment): array => $this->payload->segment($segment))
                ->values()
                ->all(),
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
            'audience_uuid' => $schema->string()->description('Audience UUID.')->format('uuid')->required(),
        ];
    }
}
