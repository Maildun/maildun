<?php

namespace App\Http\Requests;

use App\Enums\CampaignSeriesGoal;
use App\Models\CampaignSeries;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
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
        ];
    }
}
