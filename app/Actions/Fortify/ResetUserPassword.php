<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        if (! $user->hasVerifiedEmail()) {
            $this->forgetUnprovenCredentials($user);
        }

        $user->forceFill([
            'password' => $input['password'],
        ])->save();
    }

    /**
     * Drop the passkeys, two-factor secret and Google connection of an account
     * nobody has proven they own.
     *
     * Someone who signs up with another person's address can enrol these
     * before the real owner ever sees the account, and the owner's only way
     * in is this reset. The reset link is the first proof of the inbox, so
     * nothing added before it is trusted. A real but unverified user cannot
     * reach the security settings, so this rarely touches their own data.
     *
     * The Google connection is the one a verified account can carry here: it
     * survives changing the profile to an address the account does not own,
     * and Google sign-in matches on it before it looks at the address.
     */
    private function forgetUnprovenCredentials(User $user): void
    {
        $user->passkeys()->delete();

        $user->forceFill([
            'google_id' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }
}
