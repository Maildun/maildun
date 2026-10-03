<?php

namespace App\Mcp\Tools\Subscribers;

use App\Enums\SubscriberStatus;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\Segment;
use App\Models\Subscriber;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Lists the subscribers of one audience with optional status, search, tag, and segment filters.')]
#[IsOpenWorld(false)]
#[IsReadOnly]
#[Name('list-subscribers')]
class ListSubscribersTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $audience = $this->context->audienceFrom($request, $team);
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(SubscriberStatus::class)],
            'search' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:50'],
            'segment_uuid' => ['nullable', 'uuid', Rule::exists(Segment::class, 'uuid')->where('audience_id', $audience->id)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [
            'segment_uuid.exists' => 'The selected segment was not found in this audience.',
        ]);

        $search = mb_strtolower(trim((string) ($validated['search'] ?? '')));
        $tag = trim((string) ($validated['tag'] ?? ''));

        $subscribers = $audience->subscribers()
            ->with(['audience:id,uuid,name', 'contact:id,uuid', 'tags:id,uuid,name'])
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when($search !== '', function ($query) use ($search): void {
                $pattern = '%'.addcslashes($search, '%_\\').'%';

                $query->where(fn ($subscriberQuery) => $subscriberQuery
                    ->whereRaw('LOWER(subscribers.email) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(subscribers.first_name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(subscribers.last_name) LIKE ?', [$pattern]));
            })
            ->when($tag !== '', fn ($query) => $query->whereHas(
                'tags',
                fn ($tagQuery) => $tagQuery->whereRaw('LOWER(tags.name) = ?', [mb_strtolower($tag)]),
            ))
            ->when(isset($validated['segment_uuid']), fn ($query) => $query->whereHas(
                'segments',
                fn ($segmentQuery) => $segmentQuery->where('segments.uuid', $validated['segment_uuid']),
            ))
            ->orderByDesc('subscribers.created_at')
            ->orderByDesc('subscribers.id')
            ->limit((int) ($validated['limit'] ?? 20))
            ->get();

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'audience' => ['uuid' => $audience->uuid, 'name' => $audience->name],
            'count' => $subscribers->count(),
            'subscribers' => $subscribers
                ->map(fn (Subscriber $subscriber): array => $this->payload->subscriber($subscriber))
                ->values()
                ->all(),
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
            'status' => $schema->string()->description('Optional subscription status filter.')->enum(SubscriberStatus::class),
            'search' => $schema->string()->description('Optional email, first name, or last name search.')->max(255),
            'tag' => $schema->string()->description('Optional tag name filter.')->max(50),
            'segment_uuid' => $schema->string()->description('Optional segment UUID in this audience.')->format('uuid'),
            'limit' => $schema->integer()->description('Maximum subscribers to return.')->min(1)->max(100)->default(20),
        ];
    }
}
