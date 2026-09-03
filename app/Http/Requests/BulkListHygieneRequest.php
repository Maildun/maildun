<?php

namespace App\Http\Requests;

use App\Models\Audience;
use App\Models\Subscriber;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BulkListHygieneRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');

        return $team instanceof Team
            && Gate::allows('create', [Audience::class, $team]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $team = $this->route('current_team');

        abort_unless($team instanceof Team, 404);

        return [
            'subscribers' => ['required', 'array', 'min:1', 'max:100'],
            'subscribers.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists(Subscriber::class, 'uuid')->where(
                    fn (Builder $query): Builder => $query->whereIn(
                        'audience_id',
                        $team->audiences()->select('id'),
                    ),
                ),
            ],
        ];
    }

    /** @return list<string> */
    public function subscriberUuids(): array
    {
        /** @var list<string> $subscriberUuids */
        $subscriberUuids = $this->validated('subscribers');

        return $subscriberUuids;
    }
}
