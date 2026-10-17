<?php

namespace App\Http\Requests;

use App\Enums\EmailEditor;
use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\Team;
use App\Models\TransactionalEmail;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('emailTemplate');

        return $template instanceof EmailTemplate
            ? Gate::allows('update', $template)
            : Gate::allows('create', [EmailTemplate::class, $this->route('current_team')]);
    }

    /**
     * A template saved from a campaign or transactional email takes that
     * email's stored editor, which can differ from the team editor once the
     * team default changes. Other new templates take the team editor. Existing
     * ones keep the editor they were saved with — the compose page does not
     * switch it.
     */
    public function editor(): EmailEditor
    {
        $template = $this->route('emailTemplate');

        if ($template instanceof EmailTemplate) {
            return $template->editor;
        }

        $team = $this->route('current_team');

        abort_unless($team instanceof Team, 404);

        return $this->sourceEmail($team)->editor ?? $team->email_editor;
    }

    /**
     * The campaign or transactional email the template is being saved from,
     * found within the current team so another team's uuid 404s.
     */
    protected function sourceEmail(Team $team): Email|TransactionalEmail|null
    {
        if (blank($this->input('email_uuid'))) {
            return null;
        }

        $emails = $this->input('email_type') === 'transactional'
            ? $team->transactionalEmails()
            : $team->emails();

        return $emails->where('uuid', $this->input('email_uuid'))->firstOrFail();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $team = $this->route('current_team');
        $template = $this->route('emailTemplate');

        abort_unless($team instanceof Team, 404);

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(EmailTemplate::class)
                    ->where('team_id', $team->id)
                    ->ignore($template),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'html' => [
                $template instanceof EmailTemplate ? 'required' : 'nullable',
                'string',
                'max:2000000',
            ],
            'source' => ['nullable', 'string', 'max:2000000'],
            'design' => ['nullable', 'array', 'required_array_keys:root'],
            'design.root' => ['array'],
            'email_type' => ['nullable', 'required_with:email_uuid', Rule::in(['campaign', 'transactional'])],
            'email_uuid' => ['nullable', 'required_with:email_type', 'uuid'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $savingBody = $this->filled('html') || $this->filled('source') || $this->filled('design');
            $updating = $this->route('emailTemplate') instanceof EmailTemplate;
            $editor = $this->editor();

            if ($editor === EmailEditor::Builder
                && ($updating || $savingBody)
                && blank($this->input('design'))) {
                $validator->errors()->add('design', __('The block layout is missing.'));
            }

            if ($editor->usesSource()
                && ($updating || $savingBody)
                && blank($this->input('source'))) {
                $validator->errors()->add('source', __('The email content is missing.'));
            }
        }];
    }
}
