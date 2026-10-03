<?php

namespace App\Mcp\Tools\Subscribers;

use App\Actions\Audiences\UnsubscribeViaApi;
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

#[Description('Unsubscribes a subscriber from its audience. The membership and history are kept; it can only be restored with resubscribe-subscriber on renewed consent.')]
#[IsDestructive]
#[IsIdempotent]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('unsubscribe-subscriber')]
class UnsubscribeSubscriberTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
        private UnsubscribeViaApi $unsubscribe,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $subscriber = $this->context->subscriber($request, $team);
        $this->context->authorize($request, 'update', $subscriber);

        $subscriber = $this->unsubscribe->handle($subscriber->audience, $subscriber->email) ?? $subscriber;
        $subscriber->load(['audience:id,uuid,name', 'contact:id,uuid', 'tags:id,uuid,name']);

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'subscriber' => $this->payload->subscriber($subscriber),
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
        ];
    }
}
