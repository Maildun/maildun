<?php

namespace App\Http\Requests;

use App\Models\Tag;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BulkTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team
            && Gate::allows('create', [Tag::class, $team]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $team = $this->route('team');

        abort_unless($team instanceof Team, 404);

        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => [
                'required',
                'uuid',
                Rule::exists(Tag::class, 'uuid')->where('team_id', $team->id),
            ],
        ];
    }
}
