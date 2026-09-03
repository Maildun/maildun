<?php

namespace App\Http\Requests\Teams;

use App\Enums\TeamPermission;
use App\Models\Team;
use App\Models\TeamSenderDomain;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreTeamSenderDomainRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team
            && $this->user()?->hasTeamPermission($team, TeamPermission::UpdateTeam) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('team');

        abort_unless($team instanceof Team, 404);

        return [
            'domain' => [
                'required',
                'string',
                'max:253',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)
                        || ! str_contains($value, '.')
                        || filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                        $fail(__('Enter a valid domain such as example.com.'));
                    }
                },
                Rule::unique(TeamSenderDomain::class)->where('team_id', $team->id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('domain'))) {
            $this->merge([
                'domain' => Str::lower(rtrim(trim($this->string('domain')->value()), '.')),
            ]);
        }
    }
}
