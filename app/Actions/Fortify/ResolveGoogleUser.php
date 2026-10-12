<?php

namespace App\Actions\Fortify;

use App\Actions\Teams\CreateTeam;
use App\Exceptions\GoogleSignInFailed;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\InstallationState;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\User as GoogleUser;

/**
 * Find, link, or create the Maildun account behind a Google sign-in.
 *
 * Accounts are matched by Google's subject id once linked, never by email.
 * An unlinked account is linked by email only when both Google and Maildun
 * have verified that address, and a new account is created only where the
 * register routes would also let the person in.
 */
class ResolveGoogleUser
{
    /**
     * Accounts one network address may create through Google per hour.
     */
    private const int RegistrationsPerHour = 20;

    public function __construct(
        private readonly InstallationState $installation,
        private readonly CreateTeam $createTeam,
    ) {}

    /**
     * Resolve the account a Google sign-in logs in as.
     *
     * @throws GoogleSignInFailed
     */
    public function handle(GoogleUser $googleUser, ?string $invitationCode, string $ipAddress): User
    {
        $googleId = (string) $googleUser->getId();

        if ($googleId === '') {
            throw GoogleSignInFailed::generic();
        }

        $linkedUser = User::query()->where('google_id', $googleId)->first();

        if ($linkedUser !== null) {
            return $linkedUser;
        }

        $email = Str::lower(trim((string) $googleUser->getEmail()));

        if ($email === '' || ! $this->hasVerifiedEmail($googleUser)) {
            throw GoogleSignInFailed::unverifiedGoogleEmail();
        }

        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->oldest('id')
            ->first();

        if ($existingUser !== null) {
            return $this->link($existingUser, $googleId);
        }

        return $this->register($googleUser, $googleId, $email, $invitationCode, $ipAddress);
    }

    /**
     * Only Google's own email_verified claim proves the person controls the
     * mailbox; the address alone does not.
     */
    private function hasVerifiedEmail(GoogleUser $googleUser): bool
    {
        return ($googleUser->getRaw()['email_verified'] ?? null) === true;
    }

    /**
     * Link the Google identity to the existing account with the same address.
     *
     * An unverified account is never linked: somebody may have registered the
     * address without owning it, waiting for the real owner to arrive through
     * Google (pre-account takeover).
     *
     * @throws GoogleSignInFailed
     */
    private function link(User $user, string $googleId): User
    {
        if ($user->email_verified_at === null) {
            throw GoogleSignInFailed::unverifiedLocalAccount();
        }

        if ($user->google_id !== null) {
            throw GoogleSignInFailed::linkedToDifferentGoogleAccount();
        }

        try {
            $user->forceFill(['google_id' => $googleId])->save();
        } catch (UniqueConstraintViolationException) {
            throw GoogleSignInFailed::generic();
        }

        return $user;
    }

    /**
     * Create a password-less account for a verified Google address.
     *
     * Nothing is created before installation finishes: the first account
     * claims the instance and closes the browser installer, so it has to come
     * from the installer itself.
     *
     * @throws GoogleSignInFailed
     */
    private function register(GoogleUser $googleUser, string $googleId, string $email, ?string $invitationCode, string $ipAddress): User
    {
        if (! $this->installation->isComplete()) {
            throw GoogleSignInFailed::installationPending();
        }

        $this->ensureRegistrationIsOpen($email, $invitationCode);

        $rateLimitKey = 'google-registration:'.$ipAddress;

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::RegistrationsPerHour)) {
            throw GoogleSignInFailed::tooManyRegistrations();
        }

        $user = new User;
        $user->forceFill([
            'name' => $this->displayName($googleUser, $email),
            'email' => $email,
            'password' => null,
            'google_id' => $googleId,
            'email_verified_at' => now(),
        ]);

        // Like a password sign-up, the account starts with its personal workspace.
        try {
            DB::transaction(function () use ($user): void {
                $user->save();

                $this->createTeam->handle($user, $user->name."'s Team", isPersonal: true);
            });
        } catch (UniqueConstraintViolationException) {
            throw GoogleSignInFailed::generic();
        }

        RateLimiter::hit($rateLimitKey, 3600);

        event(new Registered($user));

        return $user;
    }

    /**
     * Apply the gate EnsureRegistrationIsOpen puts on the register routes,
     * which never run for a Google sign-up.
     *
     * @throws GoogleSignInFailed
     */
    private function ensureRegistrationIsOpen(string $email, ?string $invitationCode): void
    {
        if (config('fortify.registration_open')) {
            return;
        }

        if (TeamInvitation::query()->pendingFor($email)->exists()) {
            return;
        }

        $invitation = $invitationCode === null
            ? null
            : TeamInvitation::query()->where('code', $invitationCode)->first();

        throw $invitation?->isPending()
            ? GoogleSignInFailed::invitationForDifferentEmail()
            : GoogleSignInFailed::registrationClosed();
    }

    private function displayName(GoogleUser $googleUser, string $email): string
    {
        $name = trim((string) $googleUser->getName());

        return Str::substr($name !== '' ? $name : Str::before($email, '@'), 0, 255);
    }
}
