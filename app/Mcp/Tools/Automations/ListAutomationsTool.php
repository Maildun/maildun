<?php

namespace App\Mcp\Tools\Automations;

use App\Enums\AutomationRunStatus;
use App\Enums\AutomationStatus;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\Automation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Lists automations in a Maildun workspace without exposing trigger secrets.')]
#[IsOpenWorld(false)]
#[IsReadOnly]
#[Name('list-automations')]
class ListAutomationsTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(AutomationStatus::class)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 20);

        $automations = $team->automations()
            ->withCount([
                'runs as enrolled_count',
                'runs as running_count' => fn ($query) => $query->whereIn('status', AutomationRunStatus::open()),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $pattern = '%'.addcslashes($search, '%_\\').'%';

                $query->whereAny(['name', 'description'], 'like', $pattern);
            })
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'count' => $automations->count(),
            'automations' => $automations
                ->map(fn (Automation $automation): array => $this->payload->automationSummary($automation))
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
            'search' => $schema->string()->description('Optional automation name or description search.')->max(255),
            'status' => $schema->string()->description('Optional automation status filter.')->enum(AutomationStatus::class),
            'limit' => $schema->integer()->description('Maximum automations to return.')->min(1)->max(100)->default(20),
        ];
    }
}
