<?php

namespace App\Http\Requests;

use App\Models\Subscriber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ResubscribeSubscriberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('subscriber'));
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        abort_unless($this->route('subscriber') instanceof Subscriber, 404);

        return [
            'consent_confirmed' => ['required', 'accepted'],
        ];
    }
}
