<?php

namespace App\Http\Requests\Api;

use App\Models\Audience;
use App\Models\Team;
use App\Models\TeamApiKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UnsubscribeSubscriberRequest extends FormRequest
{
    private ?Audience $resolvedAudience = null;

    public function authorize(): bool
    {
        return $this->attributes->get('teamApiKey') instanceof TeamApiKey;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => Str::lower($this->string('email')->trim()->toString())]);
        }
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    public function audience(): Audience
    {
        if ($this->resolvedAudience instanceof Audience) {
            return $this->resolvedAudience;
        }

        $team = $this->attributes->get('team');
        abort_unless($team instanceof Team, 401);

        $this->resolvedAudience = $team->audiences()
            ->where('uuid', (string) $this->route('audience'))
            ->firstOrFail();

        return $this->resolvedAudience;
    }
}
