<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BulkContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');

        return $team instanceof Team && Gate::allows('create', [Contact::class, $team]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $team = $this->route('current_team');

        abort_unless($team instanceof Team, 404);

        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => [
                'required',
                'uuid',
                Rule::exists(Contact::class, 'uuid')->where('team_id', $team->id),
            ],
        ];
    }
}
