<?php

namespace App\Mcp\Tools\Workspaces;

use App\Mcp\Support\ResourcePayload;
use App\Models\Team;
use App\Models\User;
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

#[Description('Lists active Maildun workspaces that can be used with the other MCP tools.')]
#[IsOpenWorld(false)]
#[IsReadOnly]
#[Name('list-workspaces')]
class ListWorkspacesTool extends Tool
{
    public function __construct(private ResourcePayload $payload) {}

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 20);

        $user = $request->user();
        $query = $user instanceof User ? $user->teams() : Team::query();

        $workspaces = $query
            ->when($search !== '', function ($query) use ($search): void {
                $pattern = '%'.addcslashes($search, '%_\\').'%';

                $query->whereAny(['name', 'slug'], 'like', $pattern);
            })
            ->orderByRaw('LOWER(name)')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return Response::structured([
            'count' => $workspaces->count(),
            'workspaces' => $workspaces
                ->map(fn (Team $team): array => $this->payload->workspace($team))
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
            'search' => $schema->string()
                ->description('Optional case-insensitive workspace name or slug search.')
                ->max(255),
            'limit' => $schema->integer()
                ->description('Maximum number of workspaces to return.')
                ->min(1)
                ->max(100)
                ->default(20),
        ];
    }
}
