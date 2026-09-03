<?php

namespace App\Mcp\Tools\Automations;

use App\Enums\AutomationRunStatus;
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

#[Description('Gets one automation and its workflow graph without exposing its trigger secret.')]
#[IsOpenWorld(false)]
#[IsReadOnly]
#[Name('get-automation')]
class GetAutomationTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $automation = $this->context->automation($request, $team);
        $automation->loadCount([
            'runs as enrolled_count',
            'runs as running_count' => fn ($query) => $query->whereIn('status', AutomationRunStatus::open()),
        ]);

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'automation' => $this->payload->automation($automation),
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
            'uuid' => $schema->string()->description('Automation UUID.')->format('uuid')->required(),
        ];
    }
}
