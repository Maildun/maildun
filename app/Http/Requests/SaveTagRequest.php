<?php

namespace App\Http\Requests;

use App\Models\Tag;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tag = $this->route('tag');

        return $tag instanceof Tag
            ? Gate::allows('update', $tag)
            : Gate::allows('create', [Tag::class, $this->route('team')]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $team = $this->route('team');
        $tag = $this->route('tag');

        abort_unless($team instanceof Team, 404);

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique(Tag::class)
                    ->where('team_id', $team->id)
                    ->ignore($tag),
            ],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
