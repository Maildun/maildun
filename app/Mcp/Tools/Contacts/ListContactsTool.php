<?php

namespace App\Mcp\Tools\Contacts;

use App\Concerns\SearchesText;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\Contact;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Lists contacts in a Maildun workspace with optional email or name search, tag, audience, and company filters.')]
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
            'company' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail) use ($team): void {
                if (in_array($value, ['assigned', 'unassigned'], true)) {
                    return;
                }

                if (! is_string($value) || ! Str::isUuid($value) || ! $team->companies()->where('uuid', $value)->exists()) {
                    $fail('Use assigned, unassigned, or a company UUID from this workspace.');
                }
            }],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $audience = $request->get('audience_uuid') === null ? null : $this->context->audienceFrom($request, $team);

        $search = mb_strtolower(trim((string) ($validated['search'] ?? '')));
        $tag = trim((string) ($validated['tag'] ?? ''));
        $company = (string) ($validated['company'] ?? '');

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
            ->when($company === 'assigned', fn ($query) => $query->whereNotNull('company_id'))
            ->when($company === 'unassigned', fn ($query) => $query->whereNull('company_id'))
            ->when(Str::isUuid($company), fn ($query) => $query->whereHas(
                'company',
                fn ($companyQuery) => $companyQuery->where('uuid', $company),
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
            'company' => $schema->string()->description('Optional company filter: assigned, unassigned, or a company UUID from list-companies.')->max(36),
            'limit' => $schema->integer()->description('Maximum contacts to return.')->min(1)->max(100)->default(20),
        ];
    }
}
