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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Updates an audience membership. Sets or clears the subscriber\'s language; the shared contact profile is changed with update-contact.')]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('update-subscriber')]
class UpdateSubscriberTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $subscriber = $this->context->subscriber($request, $team);
        $this->context->authorize($request, 'update', $subscriber);
        $validated = $request->validate([
            'language' => ['present', 'nullable', 'string', Rule::in($subscriber->audience->allowed_languages ?? [])],
        ], [
            'language.present' => 'Provide a language, or null to clear it.',
            'language.in' => 'The language must be one of this audience\'s allowed languages.',
        ]);

        $subscriber->update(['language' => $validated['language']]);
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
            'language' => $schema->string()->description('ISO 639-1 code from the audience\'s allowed_languages, or null to clear the language.')->max(10)->nullable()->required(),
        ];
    }
}
