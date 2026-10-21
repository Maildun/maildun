<?php

namespace App\Mcp\Tools\Companies;

use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\Company;
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

#[Description('Lists the companies in a Maildun workspace with their email domains and contact counts. Contacts on a company domain are assigned to it automatically unless their company was set manually.')]
#[IsOpenWorld(false)]
#[IsReadOnly]
#[Name('list-companies')]
class ListCompaniesTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $this->context->authorize($request, 'viewAny', [Company::class, $team]);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = mb_strtolower(trim((string) ($validated['search'] ?? '')));

        $companies = $team->companies()
            ->with('domains:id,company_id,domain')
            ->withCount('contacts')
            ->when($search !== '', function ($query) use ($search): void {
                $pattern = '%'.addcslashes($search, '%_\\').'%';

                $query->where(fn ($companyQuery) => $companyQuery
                    ->whereRaw('LOWER(name) LIKE ?', [$pattern])
                    ->orWhereHas('domains', fn ($domainQuery) => $domainQuery->whereLike('domain', $pattern)));
            })
            ->orderBy('normalized_name')
            ->orderBy('id')
            ->limit((int) ($validated['limit'] ?? 20))
            ->get();

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'count' => $companies->count(),
            'companies' => $companies
                ->map(fn (Company $company): array => $this->payload->company($company))
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
            'search' => $schema->string()->description('Optional company name or domain search.')->max(255),
            'limit' => $schema->integer()->description('Maximum companies to return.')->min(1)->max(100)->default(20),
        ];
    }
}
