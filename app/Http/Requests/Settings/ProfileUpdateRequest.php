<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ProfileUpdateRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * The email is the account's recovery address, so changing it asks for
     * the current password: otherwise anyone holding a signed-in session (or
     * able to make the browser submit this form) could redirect password
     * resets to themselves. An account that signed up with Google has no
     * password to type; ProfileController asks it for a recent confirmation
     * instead.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = $this->profileRules($this->user()->id);

        if (! $this->changesEmail()) {
            // Keeping the address is always allowed, even for an account that
            // predates case-insensitive uniqueness and shares its address with
            // a case-variant account; ProfileController leaves that one as is.
            $rules['email'] = array_values(array_filter($rules['email'], is_string(...)));

            return $rules;
        }

        if ($this->user()->hasPassword()) {
            $rules['current_password'] = $this->currentPasswordRules();
        }

        return $rules;
    }

    /**
     * Determine whether another account already holds the submitted address
     * in some letter case, which only happens to accounts created before
     * emails were stored lowercased.
     */
    public function normalizedEmailBelongsToAnotherAccount(): bool
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower((string) $this->input('email'))])
            ->whereKeyNot($this->user()->id)
            ->exists();
    }

    /**
     * Determine whether the submitted email is a different address from the
     * stored one. An address stored in mixed case before emails were
     * normalized only changes case when saved, which is not a new address.
     */
    public function changesEmail(): bool
    {
        return $this->has('email') && $this->input('email') !== Str::lower($this->user()->email);
    }

    /**
     * Normalize the email the same way User stores it, so changing only its
     * case is not treated as a change.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }
}
