<?php

namespace App\Mcp\Tools\Companies;

use App\Actions\Companies\SaveCompany;
use App\Actions\Companies\ValidateCompanyInput;
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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Renames a company or replaces its email domains. domains is the complete set: omitted domains are removed. Contacts on added or removed domains are re-linked in the background; manually assigned contacts keep their company.')]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('update-company')]
class UpdateCompanyTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
        private ValidateCompanyInput $validateCompanyInput,
        private SaveCompany $saveCompany,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $company = $this->context->company($request, $team);
        $this->context->authorize($request, 'update', $company);
        $this->context->changes($request->all(), ['name', 'domains']);

        $input = $this->validateCompanyInput->validate(
            $team,
            $company,
            $request->get('name', $company->name),
            $request->get('domains', $company->domains()->orderBy('id')->pluck('domain')->all()),
        );
        $company = $this->saveCompany->handle($team, $company, $input['name'], $input['domains']);
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
            'name' => $schema->string()->description('New company name; unique in the workspace, ignoring case.')->max(255),
            'domains' => $schema->array()->description('Complete email domain set such as ["acme.com"]; omitted domains are removed. Keeping the first domain keeps the company icon.')->items($schema->string()->max(253))->min(1)->max(20),
        ];
    }
}
