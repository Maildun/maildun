<?php

namespace App\Mcp\Tools\Subscribers;

use App\Actions\Audiences\ResubscribeSubscriber;
use App\Enums\SubscriberStatus;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
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

#[Description('Resubscribes an unsubscribed subscriber. Only use this when the person has consented again; consent_confirmed is required.')]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('resubscribe-subscriber')]
class ResubscribeSubscriberTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
        private ResubscribeSubscriber $resubscribe,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $subscriber = $this->context->subscriber($request, $team);
        $this->context->authorize($request, 'update', $subscriber);
        $request->validate([
            'consent_confirmed' => ['required', 'accepted'],
        ], [
            'consent_confirmed.required' => 'Confirm the person consented again before resubscribing them.',
            'consent_confirmed.accepted' => 'Confirm the person consented again before resubscribing them.',
        ]);

        if ($subscriber->status !== SubscriberStatus::Unsubscribed) {
            throw ValidationException::withMessages([
                'uuid' => 'This subscriber is already subscribed.',
            ]);
        }

        $subscriber = $this->resubscribe->handle($subscriber, null);
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
            'consent_confirmed' => $schema->boolean()->description('The person consented again to receive marketing email from this audience.')->required(),
        ];
    }
}
