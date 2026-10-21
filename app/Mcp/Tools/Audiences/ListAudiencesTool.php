<?php

namespace App\Mcp\Tools\Audiences;

use App\Concerns\SearchesText;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\Audience;
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

#[Description('Lists audiences in a Maildun workspace, including subscriber and segment counts.')]
#[IsOpenWorld(false)]
#[IsReadOnly]
#[Name('list-audiences')]
class ListAudiencesTool extends Tool
{
    use SearchesText;

    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 20);

        $audiences = $team->audiences()
            ->with(['doubleOptInEmail:id,uuid,name', 'audienceAttributes'])
            ->withCount([
                'subscribers',
                'subscribers as subscribed_count' => fn ($query) => $query->optedIn(),
                'segments',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $this->whereAnyColumnContains($query, ['name', 'description'], $search);
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'count' => $audiences->count(),
            'audiences' => $audiences
                ->map(fn (Audience $audience): array => $this->payload->audience($audience))
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
            'search' => $schema->string()->description('Optional audience name or description search.')->max(255),
            'limit' => $schema->integer()->description('Maximum audiences to return.')->min(1)->max(100)->default(20),
        ];
    }
}
