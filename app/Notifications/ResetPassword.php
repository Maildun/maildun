<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The queued payload carries the plaintext reset token, which is enough to
 * take over the account. It is encrypted so the token cannot be read from
 * Horizon's job views, Redis, or the failed_jobs table.
 */
class ResetPassword extends BaseResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;
}
