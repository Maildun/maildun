<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A Google account could not be signed in, linked, or registered.
 *
 * The message is written for the person at the login screen.
 */
final class GoogleSignInFailed extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    /**
     * Something went wrong that the person cannot act on beyond retrying.
     */
    public static function generic(): self
    {
        return new self(__('We could not verify your Google account. Please try again.'));
    }

    /**
     * Google did not vouch for the address, so it proves nothing about who
     * controls the mailbox.
     */
    public static function unverifiedGoogleEmail(): self
    {
        return new self(__('Google has not verified the email address on that account, so it cannot be used to sign in.'));
    }

    /**
     * Linking would hand an account nobody has proven to own to whoever holds
     * the Google account, so it is refused (pre-account takeover).
     */
    public static function unverifiedLocalAccount(): self
    {
        return new self(__('An unverified account already uses this email address. Log in with your password or reset it, then connect Google from Security settings.'));
    }

    /**
     * The account with this address is already linked to another Google identity.
     */
    public static function linkedToDifferentGoogleAccount(): self
    {
        return new self(__('The account using this email address is connected to a different Google account.'));
    }

    /**
     * Nobody has claimed the instance yet; an account created now would close
     * the browser installer.
     */
    public static function installationPending(): self
    {
        return new self(__('Maildun has not finished installing yet. Complete the installation before signing up with Google.'));
    }

    /**
     * The same refusal EnsureRegistrationIsOpen gives the register routes.
     */
    public static function registrationClosed(): self
    {
        return new self(__('Registration is closed. Ask an administrator to invite you.'));
    }

    /**
     * The person followed a valid invitation but chose a Google account for
     * another address.
     */
    public static function invitationForDifferentEmail(): self
    {
        return new self(__('This invitation was sent to a different email address. Continue with the Google account for that address.'));
    }

    /**
     * Too many accounts were created from one network address recently.
     */
    public static function tooManyRegistrations(): self
    {
        return new self(__('Too many accounts have been created from your network. Please try again later.'));
    }
}
