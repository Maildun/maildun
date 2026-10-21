<?php

namespace App\Mcp\Tools\Contacts;

use App\Mcp\Support\AudienceMembership;
use App\Mcp\Support\ContactCompanyAssignment;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\Audience;
use App\Models\Contact;
use App\Models\Subscriber;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
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

#[Description('Creates a workspace contact and optionally subscribes it to audiences. An existing contact keeps its profile, company and tags and is only added to the audiences. Subscribing requires consent_confirmed, and people who unsubscribed are refused.')]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('create-contact')]
class CreateContactTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
        private AudienceMembership $membership,
        private ContactCompanyAssignment $companyAssignment,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $this->context->authorize($request, 'create', [Contact::class, $team]);
        if (is_string($request->get('email'))) {
            $request->merge(['email' => Str::lower(trim($request->get('email')))]);
        }

        $audienceUuids = $request->get('audience_uuids', []);
        $hasAudiences = is_array($audienceUuids) && $audienceUuids !== [];
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
            'audience_uuids' => ['sometimes', 'array', 'max:50'],
            'audience_uuids.*' => ['string', 'distinct', Rule::exists(Audience::class, 'uuid')->where('team_id', $team->id)],
            'consent_confirmed' => $hasAudiences ? ['required', 'accepted'] : ['sometimes', 'accepted'],
            ...$this->companyAssignment->rules($team),
        ], [
            ...$this->companyAssignment->messages(),
            'consent_confirmed.required' => 'Confirm the contact consented to marketing email before adding them to audiences.',
            'consent_confirmed.accepted' => 'Confirm the contact consented to marketing email before adding them to audiences.',
        ]);

        $audiences = $team->audiences()->whereIn('uuid', $validated['audience_uuids'] ?? [])->get();
        $audiences->each(fn (Audience $audience) => $this->context->authorize($request, 'create', [Subscriber::class, $audience]));
        $email = $validated['email'];

        if ($audiences->isEmpty() && $team->contacts()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This contact already exists. Use update-contact, or pass audience_uuids to add it to audiences.',
            ]);
        }

        [$contact, $changedMemberships] = $this->membership->join($team, [
            'email' => $email,
            'first_name' => $this->context->trimStrings($validated, ['first_name'])['first_name'] ?? null,
            'last_name' => $this->context->trimStrings($validated, ['last_name'])['last_name'] ?? null,
            ...$this->companyAssignment->attributes($validated, $team),
        ], $audiences, $validated['tags'] ?? []);

        $contact->load(['company:id,uuid,name', 'tags:id,uuid,name', 'subscribers.audience:id,uuid,name']);

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'created' => $contact->wasRecentlyCreated,
            'subscribed_audiences' => $changedMemberships,
            'contact' => $this->payload->contact($contact),
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
            'email' => $schema->string()->description('Contact email address.')->format('email')->max(255)->required(),
            'first_name' => $schema->string()->description('First name.')->max(255),
            'last_name' => $schema->string()->description('Last name.')->max(255),
            'tags' => $schema->array()->description('Tag names for a new contact; missing tags are created. Ignored for an existing contact.')->items($schema->string()->max(50))->max(20),
            'audience_uuids' => $schema->array()->description('Audiences to subscribe the contact to.')->items($schema->string()->format('uuid'))->max(50),
            'consent_confirmed' => $schema->boolean()->description('Required when audience_uuids is set: the person consented to receive marketing email.'),
            ...$this->companyAssignment->schema($schema),
        ];
    }
}
