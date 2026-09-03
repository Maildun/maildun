<?php

namespace App\Mcp\Tools\Campaigns;

use App\Enums\EmailStatus;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\Email;
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

#[Description('Lists email campaigns in a Maildun workspace with optional search and status filtering.')]
#[IsOpenWorld(false)]
#[IsReadOnly]
#[Name('list-campaigns')]
class ListCampaignsTool extends Tool
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
            'status' => ['nullable', Rule::enum(EmailStatus::class)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 20);

        $campaigns = $team->emails()
            ->with(['audience:id,uuid,name', 'segment:id,uuid,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $pattern = '%'.addcslashes($search, '%_\\').'%';

                $query->whereAny(['name', 'subject'], 'like', $pattern);
            })
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'count' => $campaigns->count(),
            'campaigns' => $campaigns
                ->map(fn (Email $campaign): array => $this->payload->campaignSummary($campaign))
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
            'search' => $schema->string()->description('Optional campaign name or subject search.')->max(255),
            'status' => $schema->string()->description('Optional campaign status filter.')->enum(EmailStatus::class),
            'limit' => $schema->integer()->description('Maximum campaigns to return.')->min(1)->max(100)->default(20),
        ];
    }
}
