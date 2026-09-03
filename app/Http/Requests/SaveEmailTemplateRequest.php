<?php

namespace App\Http\Requests;

use App\Enums\EmailEditor;
use App\Models\EmailTemplate;
use App\Models\Team;
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
     * New templates take the team editor. Existing ones keep the editor they
     * were saved with — the compose page does not switch it.
     */
    public function editor(): EmailEditor
    {
        $template = $this->route('emailTemplate');

        if ($template instanceof EmailTemplate) {
            return $template->editor;
        }

        $team = $this->route('current_team');

        abort_unless($team instanceof Team, 404);

        return $team->email_editor;
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
            'design' => ['nullable', 'array'],
            'design.root' => ['required_with:design', 'array'],
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

            if ($this->editor() === EmailEditor::Builder
                && ($updating || $savingBody)
                && blank($this->input('design'))) {
                $validator->errors()->add('design', __('The block layout is missing.'));
            }

            if ($this->editor()->usesSource()
                && ($updating || $savingBody)
                && blank($this->input('source'))) {
                $validator->errors()->add('source', __('The email content is missing.'));
            }
        }];
    }
}
