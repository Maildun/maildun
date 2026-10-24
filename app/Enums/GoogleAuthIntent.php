<?php

namespace App\Enums;

/**
 * Why the browser was sent to Google, remembered in the session until the
 * callback so one callback URL can serve every flow.
 */
enum GoogleAuthIntent: string
{
    /** Sign in, linking or creating the account as needed. */
    case Login = 'login';

    /** Attach a Google account to the signed-in user from Security settings. */
    case Connect = 'connect';

    /** Stand in for the password on the confirm-password screen. */
    case Confirm = 'confirm';
}
