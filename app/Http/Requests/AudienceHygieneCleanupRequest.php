<?php

namespace App\Http\Requests;

use App\Models\Audience;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * An audience-level cleanup names the set the user confirmed: when the page
 * counted it and how many it showed.
 */
class AudienceHygieneCleanupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $audience = $this->route('audience');

        return $audience instanceof Audience && Gate::allows('update', $audience);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'counted_at' => ['required', 'date'],
            'count' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * When the confirmed count was taken, never later than now.
     */
    public function countedAt(): CarbonInterface
    {
        $countedAt = CarbonImmutable::parse($this->string('counted_at')->toString())
            ->setTimezone(config('app.timezone'));

        return $countedAt->isFuture() ? CarbonImmutable::now() : $countedAt;
    }

    public function confirmedCount(): int
    {
        return $this->integer('count');
    }
}
