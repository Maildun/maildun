<?php

namespace App\Http\Requests;

use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', [Email::class, $this->route('current_team')]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $team = $this->route('current_team');

        abort_unless($team instanceof Team, 404);

        return [
            'name' => ['required', 'string', 'max:255'],
            // Starter templates belong to no team, so both they and this
            // team's templates are in scope when they use the team editor.
            'template' => [
                'nullable',
                'string',
                Rule::exists(EmailTemplate::class, 'uuid')->where(
                    fn (Builder $query) => $query
                        ->where('editor', $team->email_editor->value)
                        ->where(fn (Builder $query) => $query
                            ->whereNull('team_id')
                            ->orWhere('team_id', $team->id)),
                ),
            ],
        ];
    }
}
