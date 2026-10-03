<?php

namespace App\Http\Requests;

use App\Models\Audience;
use App\Models\Contact;
use App\Models\Subscriber;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreContactImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');

        if (! $team instanceof Team) {
            return false;
        }

        $audience = $this->targetAudience();

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
        /** @var Team $team */
        $team = $this->route('current_team');

        return [
            'file' => ['required', File::types(['csv', 'txt'])->max(10 * 1024)],
            'audience' => ['nullable', 'string', Rule::exists('audiences', 'uuid')->where('team_id', $team->id)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => __('Choose a CSV file to import.'),
            'file.mimes' => __('The import must be a CSV file.'),
            'file.max' => __('The CSV file may not be larger than 10 MB.'),
            'audience.exists' => __('Choose an audience from this workspace.'),
        ];
    }

    public function targetAudience(): ?Audience
    {
        $team = $this->route('current_team');
        $uuid = $this->input('audience');

        if (! $team instanceof Team || ! is_string($uuid) || $uuid === '') {
            return null;
        }

        return $team->audiences()->where('uuid', $uuid)->first();
    }
}
