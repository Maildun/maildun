<?php

namespace App\Http\Requests;

use App\Models\Audience;
use App\Models\Contact;
use App\Models\Subscriber;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;

class StoreContactImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $audience = $this->route('audience');

        if (! $team instanceof Team) {
            return false;
        }

        return $audience instanceof Audience
            ? Gate::allows('create', [Subscriber::class, $audience])
            : Gate::allows('create', [Contact::class, $team]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', File::types(['csv', 'txt'])->max(10 * 1024)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => __('Choose a CSV file to import.'),
            'file.mimetypes' => __('The import must be a CSV file.'),
            'file.max' => __('The CSV file may not be larger than 10 MB.'),
        ];
    }
}
