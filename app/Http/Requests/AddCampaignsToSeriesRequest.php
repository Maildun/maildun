<?php

namespace App\Http\Requests;

use App\Models\CampaignSeries;
use App\Models\Email;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AddCampaignsToSeriesRequest extends FormRequest
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
        $team = $this->route('current_team');
        $campaignSeries = $this->route('campaignSeries');

        abort_unless($team instanceof Team && $campaignSeries instanceof CampaignSeries, 404);

        return [
            'campaign_uuids' => ['required', 'array', 'min:1', 'max:100'],
            'campaign_uuids.*' => [
                'required',
                'string',
                'uuid',
                'distinct',
                Rule::exists(Email::class, 'uuid')->where(
                    fn (Builder $query) => $query
                        ->where('team_id', $team->id)
                        ->whereNull('campaign_series_id'),
                ),
            ],
        ];
    }
}
