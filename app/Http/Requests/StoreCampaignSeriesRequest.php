<?php

namespace App\Http\Requests;

use App\Enums\CampaignSeriesGoal;
use App\Models\CampaignSeries;
use App\Models\EmailConversionEvent;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCampaignSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', [CampaignSeries::class, $this->route('current_team')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        abort_unless($this->route('current_team') instanceof Team, 404);

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'goal' => ['required', Rule::enum(CampaignSeriesGoal::class)],
            'objective' => ['nullable', 'string', 'max:255'],
            'primary_cta_url' => ['nullable', 'url:http,https', 'max:2048'],
            'conversion_event_key' => ['nullable', 'string', 'regex:'.EmailConversionEvent::EVENT_KEY_PATTERN],
            'attribution_window_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'conversion_target' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'value_currency' => ['nullable', 'string', 'regex:'.EmailConversionEvent::CURRENCY_PATTERN],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('value_currency'))) {
            $this->merge(['value_currency' => Str::upper(trim($this->input('value_currency')))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'conversion_event_key.regex' => __('Use lowercase letters, numbers, and underscores, starting with a letter, such as meeting_booked.'),
            'value_currency.regex' => __('Use a three-letter currency code such as USD.'),
        ];
    }
}
