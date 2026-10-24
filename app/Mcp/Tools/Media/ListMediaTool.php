<?php

namespace App\Mcp\Tools\Media;

use App\Actions\Media\SearchReadyTeamMedia;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\Media;
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

#[Description('Lists ready images in the workspace media library, newest first, with absolute URLs, alt text and dimensions. Use the url and alt in email builder image, avatar and section params instead of placeholder URLs.')]
#[IsOpenWorld(false)]
#[IsReadOnly]
#[Name('list-media')]
class ListMediaTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
        private SearchReadyTeamMedia $search,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $this->context->authorize($request, 'viewAny', [Media::class, $team]);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $media = $this->search->handle($team, (string) ($validated['search'] ?? ''), $validated['limit'] ?? 20);

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'count' => $media->count(),
            'media' => $media->map(fn (Media $item): array => $this->payload->media($item))->values()->all(),
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
            'search' => $schema->string()->description('Optional case-insensitive file name or alt text search.')->max(255),
            'limit' => $schema->integer()->description('Maximum images to return.')->min(1)->max(100)->default(20),
        ];
    }
}
