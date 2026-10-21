<?php

namespace App\Mcp\Tools\Subscribers;

use App\Mcp\Support\AudienceMembership;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\Subscriber;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
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

#[Description('Subscribes a person to an audience on a team member\'s confirmed consent, creating the workspace contact if needed. An existing contact keeps its profile and tags. People who unsubscribed are refused; use resubscribe-subscriber for them.')]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('add-subscriber')]
class AddSubscriberTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
        private AudienceMembership $membership,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $audience = $this->context->audienceFrom($request, $team);
        $this->context->authorize($request, 'create', [Subscriber::class, $audience]);
        if (is_string($request->get('email'))) {
            $request->merge(['email' => Str::lower(trim($request->get('email')))]);
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', Rule::in($audience->allowed_languages ?? [])],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
            'consent_confirmed' => ['required', 'accepted'],
        ], [
            'consent_confirmed.required' => 'Confirm the person consented to marketing email before subscribing them.',
            'consent_confirmed.accepted' => 'Confirm the person consented to marketing email before subscribing them.',
            'language.in' => 'The language must be one of this audience\'s allowed languages.',
        ]);
        $profile = $this->context->blankStringsToNull($validated, ['first_name', 'last_name']);

        [$contact, $changedMemberships] = $this->membership->join($team, [
            'email' => $validated['email'],
            'first_name' => $profile['first_name'] ?? null,
            'last_name' => $profile['last_name'] ?? null,
        ], $team->audiences()->whereKey($audience->id)->get(), $validated['tags'] ?? [], $validated['language'] ?? null);

        $subscriber = $audience->subscribers()
            ->with(['audience:id,uuid,name', 'contact:id,uuid', 'tags:id,uuid,name'])
            ->where('contact_id', $contact->id)
            ->firstOrFail();

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'subscribed' => $changedMemberships > 0,
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
            'audience_uuid' => $schema->string()->description('Audience UUID.')->format('uuid')->required(),
            'email' => $schema->string()->description('Subscriber email address.')->format('email')->max(255)->required(),
            'first_name' => $schema->string()->description('First name, used only when the contact is new.')->max(255),
            'last_name' => $schema->string()->description('Last name, used only when the contact is new.')->max(255),
            'language' => $schema->string()->description('Subscriber language as an ISO 639-1 code from the audience\'s allowed_languages, used only when this subscription is new. Defaults to the audience default_language. Use update-subscriber to change an existing member\'s language.')->max(10),
            'tags' => $schema->array()->description('Tag names, used only when the contact is new; missing tags are created.')->items($schema->string()->max(50))->max(20),
            'consent_confirmed' => $schema->boolean()->description('The person consented to receive marketing email from this audience.')->required(),
        ];
    }
}
