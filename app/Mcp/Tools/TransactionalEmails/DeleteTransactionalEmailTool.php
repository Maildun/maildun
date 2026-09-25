<?php

namespace App\Mcp\Tools\TransactionalEmails;

use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Soft-deletes a transactional email after exact-name confirmation.')]
#[IsDestructive]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('delete-transactional-email')]
class DeleteTransactionalEmailTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $email = $this->context->transactionalEmail($request, $team);
        $this->context->authorize($request, 'delete', $email);
        $request->validate([
            'confirm_name' => ['required', 'string', Rule::in([$email->name])],
        ], [
            'confirm_name.in' => 'The confirmation name must exactly match the transactional email name.',
        ]);

        $blockReason = $email->doubleOptInBlockReason();

        if ($blockReason !== null) {
            throw ValidationException::withMessages(['uuid' => $blockReason]);
        }

        $deleted = ['uuid' => $email->uuid, 'name' => $email->name, 'slug' => $email->slug];
        $email->delete();

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'deleted' => true,
            'transactional_email' => $deleted,
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
            'uuid' => $schema->string()->description('Transactional email UUID.')->format('uuid')->required(),
            'confirm_name' => $schema->string()->description('Exact transactional email name required to confirm deletion.')->max(255)->required(),
        ];
    }
}
