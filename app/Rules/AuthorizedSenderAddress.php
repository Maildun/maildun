<?php

namespace App\Rules;

use App\Models\Team;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/** A From-address must be a sender verified for the current delivery connection. */
class AuthorizedSenderAddress implements ValidationRule
{
    public function __construct(private readonly Team $team) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value) || ! is_string($value)) {
            return;
        }

        if (! $this->team->hasVerifiedSenderAddress($value)) {
            $fail(__('Select a sender verified for the current email delivery connection.'));
        }
    }
}
