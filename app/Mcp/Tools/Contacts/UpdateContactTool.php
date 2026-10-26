<?php

namespace App\Mcp\Tools\Contacts;

use App\Actions\Automations\DispatchTaggedTriggers;
use App\Mcp\Support\ContactCompanyAssignment;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\Contact;
use App\Services\ManageContact;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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

#[Description('Updates selected fields on a contact. Profile changes apply to every audience membership; tags replaces the full tag set. company_uuid pins a company manually; company_assignment_mode automatic hands the company back to email-domain matching.')]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('update-contact')]
class UpdateContactTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
        private ManageContact $manageContact,
        private DispatchTaggedTriggers $dispatchTaggedTriggers,
        private ContactCompanyAssignment $companyAssignment,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $contact = $this->context->contact($request, $team);
        $this->context->authorize($request, 'update', $contact);
        if (is_string($request->get('email'))) {
            $request->merge(['email' => Str::lower(trim($request->get('email')))]);
        }

        $validated = $request->validate([
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(Contact::class)->where('team_id', $team->id)->ignore($contact),
            ],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
            ...$this->companyAssignment->rules($team),
        ], [
            'email.unique' => 'This email is already used by another contact.',
            ...$this->companyAssignment->messages(),
        ]);
        $changes = $this->context->changes($validated, ['email', 'first_name', 'last_name', 'tags', 'company_assignment_mode', 'company_uuid']);
        $profile = [
            ...$this->context->trimStrings(Arr::only($changes, ['email', 'first_name', 'last_name']), ['first_name', 'last_name']),
            ...$this->companyAssignment->attributes($changes, $team, $contact),
        ];

        $contact = DB::transaction(function () use ($team, $contact, $profile, $changes): Contact {
            if ($profile !== []) {
                $contact = $this->manageContact->update($contact, $profile);
            }

            if (array_key_exists('tags', $changes)) {
                $addedTags = $this->manageContact->syncTags($team, $contact, $changes['tags']);
                $this->dispatchTaggedTriggers->handle($contact, $addedTags);
            }

            return $contact;
        });

        $contact->load(['company:id,uuid,name', 'tags:id,uuid,name', 'subscribers.audience:id,uuid,name']);

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
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
            'uuid' => $schema->string()->description('Contact UUID.')->format('uuid')->required(),
            'email' => $schema->string()->description('New email address; must not belong to another contact.')->format('email')->max(255),
            'first_name' => $schema->string()->description('First name.')->max(255)->nullable(),
            'last_name' => $schema->string()->description('Last name.')->max(255)->nullable(),
            'tags' => $schema->array()->description('Complete tag name set; missing tags are created and omitted ones removed. An empty array clears all tags.')->items($schema->string()->max(50))->max(20),
            ...$this->companyAssignment->schema($schema),
        ];
    }
}
