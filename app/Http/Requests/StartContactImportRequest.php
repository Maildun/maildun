<?php

namespace App\Http\Requests;

use App\Enums\ContactImportIssue;
use App\Enums\ContactImportMergeStrategy;
use App\Models\ContactImport;
use App\Services\ContactImportCsv;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StartContactImportRequest extends FormRequest
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
        $contactImport = $this->contactImport();
        $fields = collect(app(ContactImportCsv::class)->fieldOptions($contactImport->audience))->pluck('value')->all();

        return [
            'column_map' => ['required', 'array', 'size:'.count($contactImport->headers ?? [])],
            'column_map.*' => ['nullable', 'string', Rule::in($fields)],
            'merge_strategy' => ['required', Rule::enum(ContactImportMergeStrategy::class)],
            'tags' => ['array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
            'resubscribe_unsubscribed' => ['boolean'],
            'review_options' => ['array'],
            ...collect(ContactImportIssue::cases())
                ->filter(fn (ContactImportIssue $issue): bool => $issue->actions() !== [])
                ->mapWithKeys(fn (ContactImportIssue $issue): array => [
                    'review_options.'.$issue->value => ['sometimes', 'string', Rule::in($issue->actions())],
                ])
                ->all(),
            'consent_confirmed' => $contactImport->audience_id === null ? ['nullable'] : ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'consent_confirmed.accepted' => __('Confirm that these contacts agreed to receive your emails.'),
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $mapped = array_values(array_filter((array) $this->input('column_map', []), fn (mixed $field): bool => is_string($field) && $field !== ''));

                if (! in_array('email', $mapped, true)) {
                    $validator->errors()->add('column_map', __('Choose which column holds the email address.'));
                } elseif (count($mapped) !== count(array_unique($mapped))) {
                    $validator->errors()->add('column_map', __('Each field can only be mapped to one column.'));
                }
            },
        ];
    }

    public function contactImport(): ContactImport
    {
        /** @var ContactImport $contactImport */
        $contactImport = $this->route('contactImport');

        return $contactImport;
    }
}
