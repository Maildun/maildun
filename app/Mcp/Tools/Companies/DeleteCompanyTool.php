<?php

namespace App\Mcp\Tools\Companies;

use App\Actions\Companies\DeleteCompany;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Permanently deletes a company and its domains after its exact name is supplied as confirmation. Its contacts are kept and lose the company; manually assigned ones return to email-domain matching and may join another company.')]
#[IsDestructive]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('delete-company')]
class DeleteCompanyTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
        private DeleteCompany $deleteCompany,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $company = $this->context->company($request, $team);
        $this->context->authorize($request, 'delete', $company);
        $request->validate([
            'confirm_name' => ['required', 'string', Rule::in([$company->name])],
        ], [
            'confirm_name.in' => 'The confirmation name must exactly match the company name.',
        ]);

        $deleted = ['uuid' => $company->uuid, 'name' => $company->name];
        $this->deleteCompany->handle($team, $company);

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'deleted' => true,
            'company' => $deleted,
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
            'confirm_name' => $schema->string()->description('Exact company name required to confirm deletion.')->max(255)->required(),
        ];
    }
}
