<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = $this->profileRules($this->user()->id);

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
