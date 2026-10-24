<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DeleteContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contact = $this->route('contact');

        return $contact instanceof Contact && Gate::allows('delete', $contact);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $contact = $this->route('contact');

        abort_unless($contact instanceof Contact, 404);

        return [
            'confirmation' => ['required', 'string', Rule::in([$contact->email])],
        ];
    }
}
