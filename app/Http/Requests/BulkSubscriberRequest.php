<?php

namespace App\Http\Requests;

use App\Models\Audience;
use App\Models\Subscriber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BulkSubscriberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $audience = $this->route('audience');

        return $audience instanceof Audience
            && Gate::allows('create', [Subscriber::class, $audience]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $audience = $this->route('audience');

        abort_unless($audience instanceof Audience, 404);

        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => [
                'required',
                'uuid',
                Rule::exists(Subscriber::class, 'uuid')->where('audience_id', $audience->id),
            ],
        ];
    }
}
