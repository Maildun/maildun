<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ProfileUpdateRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * The address is where password resets go, so changing it takes the
     * current password: a borrowed session must not be enough to move the
     * account to another inbox. An account that signed up with Google has no
     * password to type; ProfileController asks it for a recent confirmation
     * instead.
     *
     * While registration is closed an account only exists because an
     * invitation admitted its address, so an unverified account may not swap
     * that address for another before proving it owns the invited one.
     *
     * @return array<string, ValidationRule|Closure|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = $this->profileRules($this->user()->id);

        if ($this->changesEmail() && ! config('fortify.registration_open') && ! $this->user()->hasVerifiedEmail()) {
            $rules['email'][] = function (string $attribute, mixed $value, Closure $fail): void {
                $fail(__('Verify your current email address before changing it.'));
            };
        }

        if ($this->changesEmail() && $this->user()->hasPassword()) {
            $rules['current_password'] = $this->currentPasswordRules();
        }

        return $rules;
    }

    /**
     * Determine whether the request moves the account to another address.
     * Another spelling of the same address is not a change.
     */
    public function changesEmail(): bool
    {
        $email = $this->input('email');

        return is_string($email) && Str::lower(trim($email)) !== Str::lower($this->user()->email);
    }
}
