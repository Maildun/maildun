<?php

namespace App\Mcp\Tools\Segments;

use App\Actions\Audiences\EnsureSegmentRulesCanChange;
use App\Actions\Audiences\SyncSegmentSubscribers;
use App\Actions\Audiences\ValidateSegmentRules;
use App\Enums\SegmentMatchType;
use App\Enums\SegmentRuleField;
use App\Enums\SegmentRuleOperator;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

#[Description('Updates selected fields on a segment and recomputes its matching subscribers. rules replaces the full rule list. rules and match_type are locked while a campaign to the segment is queued or sending.')]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('update-segment')]
class UpdateSegmentTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
        private ValidateSegmentRules $validateRules,
        private SyncSegmentSubscribers $syncSubscribers,
        private EnsureSegmentRulesCanChange $ensureRulesCanChange,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $segment = $this->context->segment($request, $team);
        $this->context->authorize($request, 'update', $segment);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'match_type' => ['sometimes', Rule::enum(SegmentMatchType::class)],
            'rules' => ['sometimes', 'array', 'min:1', 'max:10'],
            'rules.*.field' => ['required', Rule::enum(SegmentRuleField::class)],
            'rules.*.operator' => ['required', Rule::enum(SegmentRuleOperator::class)],
            'rules.*.value' => ['required', 'string', 'max:255'],
        ]);
        $changes = $this->context->changes($validated, ['name', 'description', 'match_type', 'rules']);

        if (array_key_exists('rules', $changes)) {
            $errors = $this->validateRules->handle($segment->audience, $changes['rules']);

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $changes['rules'] = array_values($changes['rules']);
        }

        $this->ensureRulesCanChange->handle($segment, $changes);

        DB::transaction(function () use ($segment, $changes): void {
            $segment->update($changes);
            $this->syncSubscribers->handle($segment);
        });

        $segment->refresh()->load('audience:id,uuid,name')->loadCount('subscribers');

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'segment' => $this->payload->segment($segment),
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
            'name' => $schema->string()->description('Segment name.')->max(255),
            'description' => $schema->string()->description('Description.')->max(2000)->nullable(),
            'match_type' => $schema->string()->description('all: every rule must match. any: at least one rule must match.')->enum(SegmentMatchType::class),
            'rules' => CreateSegmentTool::rulesSchema($schema),
        ];
    }
}
