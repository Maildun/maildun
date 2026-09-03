<?php

namespace App\Mcp\Tools\TransactionalEmails;

use App\Actions\Transactional\RenderTransactionalContent;
use App\Enums\EmailEditor;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\EmailTemplate;
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
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Creates a draft transactional email definition in a Maildun workspace.')]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('create-transactional-email')]
class CreateTransactionalEmailTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
        private RenderTransactionalContent $renderer,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $this->context->authorize($request, 'create', [TransactionalEmail::class, $team]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'email:rfc', 'max:255', new AuthorizedSenderAddress($team)],
            'reply_to' => ['nullable', 'email:rfc', 'max:255'],
            'html' => ['nullable', 'string', 'max:2000000'],
            'source' => ['nullable', 'string', 'max:2000000'],
            'design' => ['nullable', 'array'],
            'design.root' => ['required_with:design', 'array'],
            'variables' => ['nullable', 'array'],
            'variables.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/'],
            'variables.*.example' => ['nullable', 'string', 'max:255'],
        ]);

        $attributes = $this->context->blankStringsToNull($validated, [
            'slug', 'description', 'subject', 'preheader', 'from_name', 'from_address', 'reply_to', 'html', 'source',
        ]);
        $name = (string) $attributes['name'];
        $slug = $attributes['slug'] ?? TransactionalEmail::uniqueSlugFor($team, $name);

        if (TransactionalEmail::slugTaken($team, (string) $slug)) {
            throw ValidationException::withMessages([
                'slug' => 'This identifier is already used in the workspace.',
            ]);
        }

        $body = EmailTemplate::blankBodyFor($team->email_editor);
        $subject = (string) ($attributes['subject'] ?? $name);
        $preheader = (string) ($attributes['preheader'] ?? '');
        $html = (string) ($attributes['html'] ?? $body['html'] ?? '');
        /** @var list<array{key?: mixed, example?: mixed}> $declared */
        $declared = is_array($attributes['variables'] ?? null) ? $attributes['variables'] : [];

        $email = $team->transactionalEmails()->create([
            ...$attributes,
            'slug' => $slug,
            'subject' => $subject,
            'editor' => $team->email_editor,
            'html' => $html,
            'source' => $team->email_editor->usesSource()
                ? ($attributes['source'] ?? $body['source'])
                : null,
            'design' => $team->email_editor === EmailEditor::Builder
                ? ($attributes['design'] ?? $body['design'])
                : null,
            'variables' => $this->renderer->merge(
                $declared,
                $this->renderer->detect($subject, $preheader, $html),
            ),
        ]);

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
            'name' => $schema->string()->description('Transactional email name.')->max(255)->required(),
            'slug' => $schema->string()->description('Stable API identifier; generated from the name when omitted.')->max(64),
            'description' => $schema->string()->description('Internal description.')->max(255),
            'subject' => $schema->string()->description('Email subject; defaults to the name.')->max(255),
            'preheader' => $schema->string()->description('Preview text.')->max(255),
            'from_name' => $schema->string()->description('Sender name.')->max(255),
            'from_address' => $schema->string()->description('Authorized sender email.')->format('email')->max(255),
            'reply_to' => $schema->string()->description('Reply-to email.')->format('email')->max(255),
            'html' => $schema->string()->description('Rendered HTML email content.')->max(2000000),
            'source' => $schema->string()->description('Editable Markdown or plain-text source for source-based workspace editors.')->max(2000000),
            'design' => $schema->object()->description('EmailBuilder.js design document; root is required when supplied.'),
            'variables' => $this->variablesSchema($schema),
        ];
    }

    private function variablesSchema(JsonSchema $schema): Type
    {
        return $schema->array()
            ->description('Declared merge variables; variables detected in subject, preheader, and HTML are added automatically.')
            ->items($schema->object([
                'key' => $schema->string()->description('Merge variable key.')->max(64)->required(),
                'example' => $schema->string()->description('Example value.')->max(255),
            ])->withoutAdditionalProperties());
    }
}
