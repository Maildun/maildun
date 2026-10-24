<?php

namespace App\Mcp\Tools\Contacts;

use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\Contact;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Gets one contact by UUID or email, including tags, company, and every audience membership with its subscription status.')]
#[IsOpenWorld(false)]
#[IsReadOnly]
#[Name('get-contact')]
class GetContactTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        if (is_string($request->get('email'))) {
            $request->merge(['email' => Str::lower(trim($request->get('email')))]);
        }

        $validated = $request->validate([
            'uuid' => ['required_without:email', 'nullable', 'uuid'],
            'email' => ['required_without:uuid', 'nullable', 'string', 'email', 'max:255'],
        ]);

        if (isset($validated['uuid'])) {
            $contact = $this->context->contact($request, $team);
        } else {
            $contact = $team->contacts()->where('email', $validated['email'])->first();

            if (! $contact instanceof Contact) {
                throw ValidationException::withMessages([
                    'email' => 'No contact with this email exists in this workspace.',
                ]);
            }

            $this->context->authorize($request, 'view', $contact);
        }

        $contact->load(['company:id,uuid,name', 'tags:id,uuid,name', 'subscribers.audience:id,uuid,name']);

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'contact' => $this->payload->contact($contact),
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
            'uuid' => $schema->string()->description('Contact UUID. Provide this or email.')->format('uuid'),
            'email' => $schema->string()->description('Contact email address. Provide this or uuid.')->format('email')->max(255),
        ];
    }
}
