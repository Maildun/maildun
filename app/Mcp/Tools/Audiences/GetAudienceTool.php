<?php

namespace App\Mcp\Tools\Audiences;

use App\Enums\SubscriberStatus;
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
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Gets one audience from a Maildun workspace by UUID.')]
#[IsOpenWorld(false)]
#[IsReadOnly]
#[Name('get-audience')]
class GetAudienceTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $audience = $this->context->audience($request, $team);
        $audience->loadCount([
            'subscribers',
            'subscribers as subscribed_count' => fn ($query) => $query->where('status', SubscriberStatus::Subscribed),
            'segments',
        ]);

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'audience' => $this->payload->audience($audience),
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
            'uuid' => $schema->string()->description('Audience UUID.')->format('uuid')->required(),
        ];
    }
}
