<?php

namespace App\Http\Requests;

use App\Enums\EmailEditor;
use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\TransactionalEmail;
use App\Services\AssistantModels;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DesignAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->record());
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:4000'],
            'messages' => ['present', 'array', 'max:20'],
            'messages.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'messages.*.content' => ['required', 'string', 'max:8000'],
            'design' => ['required', 'array', 'required_array_keys:root'],
            'design.root' => ['array'],
            'model' => ['required', 'string', Rule::in(array_column(app(AssistantModels::class)->available(), 'id'))],
            'context' => ['sometimes', 'array'],
            'context.name' => ['nullable', 'string', 'max:255'],
            'context.subject' => ['nullable', 'string', 'max:255'],
            'context.preheader' => ['nullable', 'string', 'max:255'],
            'context.audience' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Campaign fields the author already filled in. Blank values are dropped,
     * and a line break cannot start a new instruction line. Other editors
     * leave this unset.
     *
     * @return array{name?: string, subject?: string, preheader?: string, audience?: string}|null
     */
    public function campaignBrief(): ?array
    {
        /** @var array<string, mixed>|null $context */
        $context = $this->validated('context');

        if (! is_array($context)) {
            return null;
        }

        $brief = [];

        foreach (['name', 'subject', 'preheader', 'audience'] as $key) {
            $value = trim(str_replace(["\r\n", "\r", "\n"], ' ', (string) ($context[$key] ?? '')));

            if ($value !== '') {
                $brief[$key] = $value;
            }
        }

        return $brief === [] ? null : $brief;
    }

    /**
     * The block email this assistant run designs, from whichever compose
     * route was hit. Only block-builder emails have a design to work on.
     */
    public function record(): Email|TransactionalEmail|EmailTemplate
    {
        $record = $this->route('email') ?? $this->route('transactionalEmail') ?? $this->route('emailTemplate');

        abort_unless($record instanceof Email || $record instanceof TransactionalEmail || $record instanceof EmailTemplate, 404);
        abort_unless($this->editorFor($record) === EmailEditor::Builder, 404);

        return $record;
    }

    /**
     * The editor the record opens in. Each email and template keeps the
     * editor it was composed in, whatever the workspace default is now.
     */
    private function editorFor(Email|TransactionalEmail|EmailTemplate $record): EmailEditor
    {
        return $record->editor;
    }

    /** @return list<array{role: string, content: string}> */
    public function history(): array
    {
        /** @var list<array{role: string, content: string}> $messages */
        $messages = $this->validated('messages');

        return array_map(
            fn (array $message): array => ['role' => $message['role'], 'content' => $message['content']],
            $messages,
        );
    }
}
