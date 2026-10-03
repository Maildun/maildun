<?php

namespace App\Mcp\Tools\Segments;

use App\Actions\Audiences\DeleteSegment;
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

#[Description('Permanently deletes a segment after its exact name is supplied as confirmation. Subscribers are kept. Refused while a scheduled or sending campaign targets the segment.')]
#[IsDestructive]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('delete-segment')]
class DeleteSegmentTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
        private DeleteSegment $deleteSegment,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $segment = $this->context->segment($request, $team);
        $this->context->authorize($request, 'delete', $segment);
        $request->validate([
            'confirm_name' => ['required', 'string', Rule::in([$segment->name])],
        ], [
            'confirm_name.in' => 'The confirmation name must exactly match the segment name.',
        ]);

        $deleted = ['uuid' => $segment->uuid, 'name' => $segment->name];
        $this->deleteSegment->handle($segment, 'uuid');

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'deleted' => true,
            'segment' => $deleted,
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
            'uuid' => $schema->string()->description('Segment UUID.')->format('uuid')->required(),
            'confirm_name' => $schema->string()->description('Exact segment name required to confirm deletion.')->max(255)->required(),
        ];
    }
}
