<?php

namespace App\Http\Requests;

use App\Models\ContactImport;
use App\Services\ContactImportCsv;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateContactImportDelimiterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contactImport = $this->route('contactImport');

        return $contactImport instanceof ContactImport && Gate::allows('update', $contactImport);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'delimiter' => ['required', 'string', Rule::in(ContactImportCsv::DELIMITERS)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'delimiter.in' => __('Choose comma, semicolon, tab or pipe.'),
        ];
    }
}
