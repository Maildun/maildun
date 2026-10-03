<?php

namespace App\Mcp\Support;

use App\Actions\Transactional\RenderTransactionalContent;
use App\Enums\EmailEditor;
use App\Enums\EmailStatus;
use App\Models\Email;
use App\Models\TransactionalEmail;
use App\Services\EmailBuilderAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

class EmailBuilderContext
{
    public const array EDIT_TOOLS = [
        'insert_blocks', 'update_block', 'move_block', 'remove_block', 'duplicate_block',
        'replace_block', 'insert_section', 'update_settings', 'update_theme', 'replace_document', 'apply_ops',
    ];

    public function __construct(
        private WorkspaceContext $context,
        private EmailBuilderAgent $agent,
        private RenderTransactionalContent $renderer,
    ) {}

    public function record(Request $request): Email|TransactionalEmail
    {
        $validated = $request->validate(['target' => ['required', Rule::in(['campaign', 'transactional_email'])]]);
        $team = $this->context->team($request);
        $record = $validated['target'] === 'campaign'
            ? $this->context->campaign($request, $team)
            : $this->context->transactionalEmail($request, $team);

        if ($record->editor !== EmailEditor::Builder) {
            throw ValidationException::withMessages(['uuid' => 'This email does not use the block builder.']);
        }

        return $record;
    }

    public function revision(Email|TransactionalEmail $record): string
    {
        return hash('sha256', json_encode($record->getAttributes(), JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    public function inspect(Request $request, string $tool): array
    {
        $record = $this->record($request);
        $validated = $request->validate(['block_id' => ['nullable', 'string', 'max:255']]);
        $input = isset($validated['block_id']) && $tool === 'get_document' ? ['id' => $validated['block_id']] : [];
        $mergeTags = $record instanceof Email
            ? [
                'first_name', 'last_name', 'name', 'email', 'web_view_url', 'unsubscribe_url', 'subscribe_url',
                'day', 'day_name', 'month', 'month_name', 'year',
                ...($record->audience?->audienceAttributes()->pluck('key')->all() ?? []),
            ]
            : ['web_view_url', ...array_column($record->variables, 'key')];
        $mergeTags = array_values(array_filter($mergeTags, is_string(...)));
        $result = $this->agent->run($record->design, $record->html, $input === [] ? $tool : 'get_block', $input, $mergeTags);

        if (isset($result['instructions'])) {
            $result['instructions'] .= "\nMaildun exposes these native tools through wrappers: use get-email-builder for get_document, get-email-builder with block_id for get_block, and check-email-builder for check_email. For every write call edit-email-builder with workspace, target, uuid, revision, tool (the native name), and input (its arguments). Use the returned revision for the next write. dry_run=true previews without saving; reread after a revision conflict.";
        }

        unset($result['document'], $result['ops']);

        if ($tool === 'get_document') {
            unset($result['html'], $result['text'], $result['warnings']);
        }

        return ['target' => $request->get('target'), 'uuid' => $record->uuid, 'revision' => $this->revision($record), ...$result];
    }

    /** @return array<string, mixed> */
    public function edit(Request $request): array
    {
        $record = $this->record($request);
        $this->authorizeEdit($request, $record);
        $validated = $request->validate([
            'revision' => ['required', 'string', 'size:64'],
            'tool' => ['required', Rule::in(self::EDIT_TOOLS)],
            'input' => ['required', 'array'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);
        $this->assertRevision($record, $validated['revision']);
        $result = $this->agent->run($record->design, $record->html, $validated['tool'], $validated['input']);
        $dryRun = (bool) ($validated['dry_run'] ?? false);

        if (! $dryRun) {
            $record = DB::transaction(function () use ($request, $record, $validated, $result): Email|TransactionalEmail {
                $current = $record->newQuery()->whereKey($record->id)->where('team_id', $record->team_id)->lockForUpdate()->first();

                if (! $current instanceof Email && ! $current instanceof TransactionalEmail) {
                    throw ValidationException::withMessages(['uuid' => 'The email was deleted.']);
                }

                $this->authorizeEdit($request, $current);
                $this->assertRevision($current, $validated['revision']);
                $changes = ['design' => $result['document'], 'html' => $result['html']];

                if ($current instanceof Email) {
                    $changes['plain_text'] = $result['text'];
                } else {
                    $changes['variables'] = $this->renderer->merge(
                        $current->variables,
                        $this->renderer->detect($current->subject, (string) $current->preheader, $result['html']),
                    );
                }

                $current->update($changes);

                return $current->refresh();
            });
        }

        unset($result['document'], $result['html'], $result['text']);

        return [
            'target' => $request->get('target'),
            'uuid' => $record->uuid,
            'revision' => $this->revision($record),
            'saved' => ! $dryRun,
            ...$result,
        ];
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace' => $schema->string()->description('Workspace slug.')->max(255)->required(),
            'target' => $schema->string()->enum(['campaign', 'transactional_email'])->description('The type of email to edit.')->required(),
            'uuid' => $schema->string()->description('Email UUID returned by a list or get tool.')->format('uuid')->required(),
        ];
    }

    private function authorizeEdit(Request $request, Email|TransactionalEmail $record): void
    {
        $this->context->authorize($request, 'update', $record);

        if ($record instanceof Email && $record->status !== EmailStatus::Draft) {
            throw ValidationException::withMessages(['uuid' => 'Only draft campaigns can be updated.']);
        }
    }

    private function assertRevision(Email|TransactionalEmail $record, string $revision): void
    {
        if (! hash_equals($this->revision($record), $revision)) {
            throw ValidationException::withMessages([
                'revision' => 'The email changed since it was read. Call get-email-builder again before editing.',
            ]);
        }
    }
}
