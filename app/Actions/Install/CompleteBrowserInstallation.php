<?php

namespace App\Actions\Install;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use App\Services\InstallationState;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteBrowserInstallation
{
    public function __construct(
        private CreateNewUser $createNewUser,
        private InstallationState $installation,
    ) {}

    /**
     * Claim the deployment and create its first administrator.
     *
     * @param  array{name: string, email: string, password: string, password_confirmation: string}  $input
     */
    public function handle(array $input): User
    {
        if (! $this->installation->isReady() || $this->installation->isComplete()) {
            throw ValidationException::withMessages([
                'installation' => __('This installation is not available.'),
            ]);
        }

        return DB::transaction(function () use ($input): User {
            if (! $this->installation->claim()) {
                throw ValidationException::withMessages([
                    'installation' => __('Another administrator has already completed this installation.'),
                ]);
            }

            $user = $this->createNewUser->create($input);
            $user->forceFill(['email_verified_at' => now()])->save();

            $this->installation->markComplete();

            return $user;
        }, attempts: 3);
    }
}
