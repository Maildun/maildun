<?php

namespace App\Mcp\Tools\Tags;

use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\Tag;
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

#[Description('Lists the workspace tags with how many contacts carry each. Contact and subscriber tools take tag names and create missing tags.')]
#[IsOpenWorld(false)]
#[IsReadOnly]
#[Name('list-tags')]
class ListTagsTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $this->context->authorize($request, 'viewAny', [Tag::class, $team]);

        $tags = $team->tags()
            ->withCount('contacts')
            ->orderByRaw('LOWER(name)')
            ->get();

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'count' => $tags->count(),
            'tags' => $tags->map(fn (Tag $tag): array => $this->payload->tag($tag))->values()->all(),
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
        ];
    }
}
