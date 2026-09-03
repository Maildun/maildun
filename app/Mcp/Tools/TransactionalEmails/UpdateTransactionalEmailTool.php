<?php

namespace App\Mcp\Tools\TransactionalEmails;

use App\Actions\Transactional\RenderTransactionalContent;
use App\Enums\EmailEditor;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\TransactionalEmail;
use App\Rules\AuthorizedSenderAddress;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
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

#[Description('Updates selected fields on a transactional email definition in a Maildun workspace.')]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('update-transactional-email')]
class UpdateTransactionalEmailTool extends Tool
{
    private const array FIELDS = [
        'name', 'slug', 'description', 'subject', 'preheader', 'from_name', 'from_address', 'reply_to',
        'html', 'source', 'design', 'variables',
    ];

    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
        private RenderTransactionalContent $renderer,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $email = $this->context->transactionalEmail($request, $team);
        $this->context->authorize($request, 'update', $email);
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'preheader' => ['sometimes', 'nullable', 'string', 'max:255'],
            'from_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'from_address' => ['sometimes', 'nullable', 'email:rfc', 'max:255', new AuthorizedSenderAddress($team)],
            'reply_to' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'html' => ['sometimes', 'required', 'string', 'max:2000000'],
            'source' => ['sometimes', 'required', 'string', 'max:2000000'],
            'design' => ['sometimes', 'nullable', 'array'],
            'design.root' => ['required_with:design', 'array'],
            'variables' => ['sometimes', 'nullable', 'array'],
            'variables.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/'],
            'variables.*.example' => ['nullable', 'string', 'max:255'],
        ]);

        $changes = $this->context->changes($validated, self::FIELDS);
        $changes = $this->context->blankStringsToNull($changes, [
            'description', 'preheader', 'from_name', 'from_address', 'reply_to',
        ]);

        if (isset($changes['slug']) && $email->slugIsFrozen() && $changes['slug'] !== $email->slug) {
            throw ValidationException::withMessages([
                'slug' => 'The identifier cannot change after the first publish.',
            ]);
        }

        if (isset($changes['slug']) && TransactionalEmail::slugTaken($team, (string) $changes['slug'], $email->id)) {
            throw ValidationException::withMessages([
                'slug' => 'This identifier is already used in the workspace.',
            ]);
        }

        if (array_key_exists('design', $changes) && $team->email_editor === EmailEditor::Builder && $changes['design'] === null) {
            throw ValidationException::withMessages([
                'design' => 'The block layout is missing.',
            ]);
        }

        $subject = (string) ($changes['subject'] ?? $email->subject);
        $preheader = (string) ($changes['preheader'] ?? $email->preheader ?? '');
        $html = (string) ($changes['html'] ?? $email->html ?? '');
        /** @var list<array{key?: mixed, example?: mixed}> $declared */
        $declared = array_key_exists('variables', $changes) && is_array($changes['variables'])
            ? $changes['variables']
            : $email->variables;

        $changes['editor'] = $team->email_editor;
        $changes['source'] = $team->email_editor->usesSource()
            ? ($changes['source'] ?? $email->source)
            : null;
        $changes['design'] = $team->email_editor === EmailEditor::Builder
            ? ($changes['design'] ?? $email->design)
            : null;
        $changes['variables'] = $this->renderer->merge(
            $declared,
            $this->renderer->detect($subject, $preheader, $html),
        );

        $email->update($changes);
        $email->refresh();

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'transactional_email' => $this->payload->transactionalEmail($email),
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
            'uuid' => $schema->string()->description('Transactional email UUID.')->format('uuid')->required(),
            'name' => $schema->string()->description('Transactional email name.')->max(255),
            'slug' => $schema->string()->description('Stable API identifier.')->max(64),
            'description' => $schema->string()->description('Internal description.')->max(255)->nullable(),
            'subject' => $schema->string()->description('Email subject.')->max(255),
            'preheader' => $schema->string()->description('Preview text.')->max(255)->nullable(),
            'from_name' => $schema->string()->description('Sender name.')->max(255)->nullable(),
            'from_address' => $schema->string()->description('Authorized sender email.')->format('email')->max(255)->nullable(),
            'reply_to' => $schema->string()->description('Reply-to email.')->format('email')->max(255)->nullable(),
            'html' => $schema->string()->description('Rendered HTML content.')->max(2000000),
            'source' => $schema->string()->description('Editable Markdown or plain-text source for source-based workspace editors.')->max(2000000),
            'design' => $schema->object()->description('EmailBuilder.js design document.')->nullable(),
            'variables' => $this->variablesSchema($schema),
        ];
    }

    private function variablesSchema(JsonSchema $schema): Type
    {
        return $schema->array()
            ->description('Declared merge variables; variables detected in content are added automatically.')
            ->items($schema->object([
                'key' => $schema->string()->description('Merge variable key.')->max(64)->required(),
                'example' => $schema->string()->description('Example value.')->max(255),
            ])->withoutAdditionalProperties())
            ->nullable();
    }
}
