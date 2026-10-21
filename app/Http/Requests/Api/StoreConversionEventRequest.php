<?php

namespace App\Http\Requests\Api;

use App\Models\CampaignSeries;
use App\Models\EmailConversionEvent;
use App\Models\Team;
use App\Models\TeamApiKey;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StoreConversionEventRequest extends FormRequest
{
    public const int MAX_METADATA_KEYS = 20;

    public const int MAX_METADATA_BYTES = 4096;

    public const int MAX_VALUE_MINOR = 1_000_000_000_000_000;

    private ?CampaignSeries $resolvedCampaignSeries = null;

    public function authorize(): bool
    {
        return $this->attributes->get('teamApiKey') instanceof TeamApiKey;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower($this->string('email')->trim()->toString())]);
        }

        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => Str::upper($this->string('currency')->trim()->toString())]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'string', 'max:255'],
            'event' => ['required', 'string', 'regex:'.EmailConversionEvent::EVENT_KEY_PATTERN],
            'email' => ['required', 'string', 'email', 'max:255'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:'.now()->addMinutes(5)->toIso8601String()],
            'value_minor' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_VALUE_MINOR],
            'currency' => ['nullable', 'required_with:value_minor', 'string', 'regex:'.EmailConversionEvent::CURRENCY_PATTERN],
            'campaign_series' => ['nullable', 'string', 'uuid'],
            'metadata' => ['nullable', 'array', 'max:'.self::MAX_METADATA_KEYS],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'event.regex' => __('The event must be lowercase letters, numbers, and underscores, starting with a letter, up to 64 characters.'),
            'currency.regex' => __('The currency must be a three-letter ISO 4217 code such as USD.'),
            'occurred_at.before_or_equal' => __('The occurred at time cannot be in the future.'),
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateMetadata($validator);

                if (filled($this->input('campaign_series')) && ! $this->campaignSeries() instanceof CampaignSeries) {
                    // The same answer for a missing series and another
                    // workspace's series, so neither existence leaks.
                    $validator->errors()->add('campaign_series', __('The selected campaign series is invalid.'));
                }
            },
        ];
    }

    /**
     * The explicit series, resolved inside the authenticated workspace.
     */
    public function campaignSeries(): ?CampaignSeries
    {
        if ($this->resolvedCampaignSeries instanceof CampaignSeries || blank($this->input('campaign_series'))) {
            return $this->resolvedCampaignSeries;
        }

        $team = $this->attributes->get('team');
        abort_unless($team instanceof Team, 401);

        $uuid = (string) $this->input('campaign_series');

        // Postgres rejects a non-uuid value for a uuid column, so check first.
        $this->resolvedCampaignSeries = Str::isUuid($uuid)
            ? $team->campaignSeries()->where('uuid', Str::lower($uuid))->first()
            : null;

        return $this->resolvedCampaignSeries;
    }

    /**
     * API callers branch on the code, so a validation failure carries one
     * like every other error from this endpoint.
     */
    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'code' => 'validation_failed',
            'errors' => $validator->errors(),
        ], 422));
    }

    private function validateMetadata(Validator $validator): void
    {
        $metadata = $this->input('metadata');

        if (! is_array($metadata)) {
            return;
        }

        foreach ($metadata as $key => $value) {
            if (! is_string($key) || preg_match('/\A[A-Za-z0-9_.\-]{1,64}\z/', $key) !== 1) {
                $validator->errors()->add('metadata', __('Metadata keys must be 1 to 64 letters, numbers, dots, dashes, or underscores.'));

                return;
            }

            if (! is_scalar($value) && $value !== null) {
                $validator->errors()->add('metadata', __('Metadata values must be strings, numbers, booleans, or null.'));

                return;
            }
        }

        if (strlen((string) json_encode($metadata)) > self::MAX_METADATA_BYTES) {
            $validator->errors()->add('metadata', __('Metadata may not be larger than :bytes bytes.', ['bytes' => self::MAX_METADATA_BYTES]));
        }
    }
}
