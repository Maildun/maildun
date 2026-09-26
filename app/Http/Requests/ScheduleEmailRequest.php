<?php

namespace App\Http\Requests;

use App\Models\Email;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ScheduleEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        $email = $this->route('email');

        return $email instanceof Email && ($this->user()?->can('send', $email) ?? false);
    }

    /**
     * The browser sends an ISO 8601 time with its offset, so it is stored in
     * UTC whatever timezone the author picked it in.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date', 'after:+1 minute', 'before:+1 year'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'scheduled_at.after' => __('Pick a time at least a minute from now.'),
            'scheduled_at.before' => __('Pick a time within the next year.'),
        ];
    }

    public function sendAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('scheduled_at')->toString())->utc();
    }
}
