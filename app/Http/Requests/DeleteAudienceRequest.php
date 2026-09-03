<?php

namespace App\Http\Requests;

use App\Models\Audience;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DeleteAudienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('delete', $this->route('audience'));
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $audience = $this->route('audience');

        abort_unless($audience instanceof Audience, 404);

        return [
            'name' => ['required', 'string', Rule::in([$audience->name])],
        ];
    }
}
