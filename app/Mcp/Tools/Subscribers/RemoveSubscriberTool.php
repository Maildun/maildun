<?php

namespace App\Mcp\Tools\Subscribers;

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

#[Description('Permanently deletes one audience membership after its exact email is supplied as confirmation. The workspace contact is kept. Prefer unsubscribe-subscriber to honor an opt-out.')]
#[IsDestructive]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('remove-subscriber')]
class RemoveSubscriberTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $subscriber = $this->context->subscriber($request, $team);
        $this->context->authorize($request, 'delete', $subscriber);
        $request->validate([
            'confirm_email' => ['required', 'string', Rule::in([$subscriber->email])],
        ], [
            'confirm_email.in' => 'The confirmation email must exactly match the subscriber email.',
        ]);

        $deleted = [
            'uuid' => $subscriber->uuid,
            'email' => $subscriber->email,
            'audience' => ['uuid' => $subscriber->audience->uuid, 'name' => $subscriber->audience->name],
        ];
        $subscriber->delete();

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'deleted' => true,
            'subscriber' => $deleted,
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
            'uuid' => $schema->string()->description('Subscriber UUID.')->format('uuid')->required(),
            'confirm_email' => $schema->string()->description('Exact subscriber email required to confirm deletion.')->max(255)->required(),
        ];
    }
}
