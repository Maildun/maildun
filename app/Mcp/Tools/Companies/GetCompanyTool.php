<?php

namespace App\Mcp\Tools\Companies;

use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
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

#[Description('Gets one company by UUID with its email domains and contact count. Use list-contacts with company to see its contacts.')]
#[IsOpenWorld(false)]
#[IsReadOnly]
#[Name('get-company')]
class GetCompanyTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $company = $this->context->company($request, $team);
        $company->load('domains:id,company_id,domain')->loadCount('contacts');

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'company' => $this->payload->company($company),
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
            'uuid' => $schema->string()->description('Company UUID.')->format('uuid')->required(),
        ];
    }
}
