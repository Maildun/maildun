<?php

namespace App\Http\Requests;

use App\Models\Audience;
use App\Models\AudienceAttribute;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReorderAudienceAttributesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', [AudienceAttribute::class, $this->route('audience')]);
    }

    /**
     * The new order must list every field of this audience exactly once.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $audience = $this->route('audience');

        abort_unless($audience instanceof Audience, 404);

        $uuids = $audience->audienceAttributes()->pluck('uuid')->all();

        return [
            'attributes' => ['required', 'array', 'size:'.count($uuids)],
            'attributes.*' => ['required', 'string', 'distinct', Rule::in($uuids)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'attributes.size' => __('The fields changed while you were reordering them. Reload the page and try again.'),
        ];
    }
}
