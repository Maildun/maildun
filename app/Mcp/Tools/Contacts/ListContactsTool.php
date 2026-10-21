<?php

namespace App\Mcp\Tools\Contacts;

use App\Concerns\SearchesText;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\Contact;
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

#[Description('Lists contacts in a Maildun workspace with optional email or name search, tag, and audience filters.')]
#[IsOpenWorld(false)]
#[IsReadOnly]
#[Name('list-contacts')]
class ListContactsTool extends Tool
{
    use SearchesText;

    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $this->context->authorize($request, 'viewAny', [Contact::class, $team]);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $audience = $request->get('audience_uuid') === null ? null : $this->context->audienceFrom($request, $team);

        $search = mb_strtolower(trim((string) ($validated['search'] ?? '')));
        $tag = trim((string) ($validated['tag'] ?? ''));

        $contacts = $team->contacts()
            ->with(['company:id,uuid,name', 'tags:id,uuid,name'])
            ->withCount('subscribers')
            ->when($search !== '', function ($query) use ($search): void {
                $this->whereAnyColumnContains($query, ['email', 'first_name', 'last_name'], $search);
            })
            ->when($tag !== '', fn ($query) => $query->whereHas(
                'tags',
                fn ($tagQuery) => $tagQuery->whereRaw('LOWER(name) = ?', [mb_strtolower($tag)]),
            ))
            ->when($audience !== null, fn ($query) => $query->whereHas(
                'subscribers',
                fn ($subscriberQuery) => $subscriberQuery->where('audience_id', $audience?->id),
            ))
            ->latest()
            ->orderByDesc('id')
            ->limit((int) ($validated['limit'] ?? 20))
            ->get();

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'count' => $contacts->count(),
            'contacts' => $contacts
                ->map(fn (Contact $contact): array => $this->payload->contactSummary($contact))
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
            'search' => $schema->string()->description('Optional email, first name, or last name search.')->max(255),
            'tag' => $schema->string()->description('Optional tag name; only contacts with this tag are returned.')->max(50),
            'audience_uuid' => $schema->string()->description('Optional audience UUID; only contacts in this audience are returned.')->format('uuid'),
            'limit' => $schema->integer()->description('Maximum contacts to return.')->min(1)->max(100)->default(20),
        ];
    }
}
