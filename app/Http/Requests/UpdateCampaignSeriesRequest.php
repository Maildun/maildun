<?php

namespace App\Http\Requests;

use App\Enums\CampaignSeriesGoal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateCampaignSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('campaignSeries'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'goal' => ['required', Rule::enum(CampaignSeriesGoal::class)],
            'objective' => ['nullable', 'string', 'max:255'],
            'primary_cta_url' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }
}
