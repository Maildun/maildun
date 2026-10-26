<?php

namespace App\Mcp\Tools\Contacts;

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

#[Description('Permanently deletes a contact and all of its audience memberships after its exact email is supplied as confirmation.')]
#[IsDestructive]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('delete-contact')]
class DeleteContactTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $contact = $this->context->contact($request, $team);
        $this->context->authorize($request, 'delete', $contact);
        $request->validate([
            'confirm_email' => ['required', 'string', Rule::in([$contact->email])],
        ], [
            'confirm_email.in' => 'The confirmation email must exactly match the contact email.',
        ]);

        $deleted = ['uuid' => $contact->uuid, 'email' => $contact->email];
        $contact->delete();

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'deleted' => true,
            'contact' => $deleted,
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
            'uuid' => $schema->string()->description('Contact UUID.')->format('uuid')->required(),
            'confirm_email' => $schema->string()->description('Exact contact email required to confirm deletion.')->max(255)->required(),
        ];
    }
}
