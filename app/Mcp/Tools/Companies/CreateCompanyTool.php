<?php

namespace App\Mcp\Tools\Companies;

use App\Actions\Companies\SaveCompany;
use App\Actions\Companies\ValidateCompanyInput;
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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Creates a company with one or more email domains. Contacts on those domains are linked to it automatically in the background unless their company was set manually. Personal email providers and domains another company already owns are refused.')]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('create-company')]
class CreateCompanyTool extends Tool
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
        $this->context->authorize($request, 'create', [Company::class, $team]);

        $input = $this->validateCompanyInput->validate($team, null, $request->get('name'), $request->get('domains'));
        $company = $this->saveCompany->handle($team, null, $input['name'], $input['domains']);
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
            'name' => $schema->string()->description('Company name; unique in the workspace, ignoring case.')->max(255)->required(),
            'domains' => $schema->array()->description('Email domains such as acme.com. URLs and a leading www. are stripped. The first domain supplies the company icon.')->items($schema->string()->max(253))->min(1)->max(20)->required(),
        ];
    }
}
