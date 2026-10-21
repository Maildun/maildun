<?php

namespace App\Mcp\Tools\Segments;

use App\Actions\Audiences\SyncSegmentSubscribers;
use App\Actions\Audiences\ValidateSegmentRules;
use App\Enums\SegmentMatchType;
use App\Enums\SegmentRuleField;
use App\Enums\SegmentRuleOperator;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\Segment;
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
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Creates a rule-based segment in an audience and immediately computes its matching subscribers.')]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('create-segment')]
class CreateSegmentTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
        private ValidateSegmentRules $validateRules,
        private SyncSegmentSubscribers $syncSubscribers,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $audience = $this->context->audienceFrom($request, $team);
        $this->context->authorize($request, 'create', [Segment::class, $audience]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'match_type' => ['nullable', Rule::enum(SegmentMatchType::class)],
            'rules' => ['required', 'array', 'min:1', 'max:10'],
            'rules.*.field' => ['required', Rule::enum(SegmentRuleField::class)],
            'rules.*.operator' => ['required', Rule::enum(SegmentRuleOperator::class)],
            'rules.*.value' => ['required', 'string', 'max:255'],
            'rules.*.attribute' => ['nullable', 'string', 'max:255'],
        ]);

        $errors = $this->validateRules->handle($audience, $validated['rules']);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $segment = DB::transaction(function () use ($audience, $validated): Segment {
            $segment = $audience->segments()->create([
                'name' => trim($validated['name']),
                'description' => $validated['description'] ?? null,
                'match_type' => $validated['match_type'] ?? SegmentMatchType::All->value,
                'rules' => array_values($validated['rules']),
            ]);
            $this->syncSubscribers->handle($segment);

            return $segment;
        });

        $segment->load('audience:id,uuid,name')->loadCount('subscribers');

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
            'audience_uuid' => $schema->string()->description('Audience UUID.')->format('uuid')->required(),
            'name' => $schema->string()->description('Segment name.')->max(255)->required(),
            'description' => $schema->string()->description('Optional description.')->max(2000),
            'match_type' => $schema->string()->description('all: every rule must match. any: at least one rule must match.')->enum(SegmentMatchType::class)->default(SegmentMatchType::All->value),
            'rules' => self::rulesSchema($schema)->required(),
        ];
    }

    public static function rulesSchema(JsonSchema $schema): Type
    {
        return $schema->array()
            ->description('1-10 rules. Text fields (email, first_name, last_name) take equals, not_equals, contains, does_not_contain. status (subscribed: opted in and confirmed | pending: waiting on a double opt-in or rejoin confirmation | unsubscribed), source, subscribe_form (form UUID), language (ISO 639-1 code such as en or id), and tag (tag UUID; equals means has the tag) take equals or not_equals. not_equals and does_not_contain also match subscribers with no value in that field (no name, no form, no language). subscribed_at takes before or after with a date value written as YYYY-MM-DD, compared as a UTC calendar day. attribute targets a custom audience field named by attribute (its UUID): text fields take the text operators, number fields equals, not_equals, greater_than or less_than, and date fields before or after with YYYY-MM-DD.')
            ->min(1)
            ->max(10)
            ->items($schema->object([
                'field' => $schema->string()->enum(SegmentRuleField::class)->required(),
                'operator' => $schema->string()->enum(SegmentRuleOperator::class)->required(),
                'value' => $schema->string()->max(255)->required(),
                'attribute' => $schema->string()->description('Custom field UUID, only for the attribute field.')->format('uuid'),
            ])->withoutAdditionalProperties());
    }
}
