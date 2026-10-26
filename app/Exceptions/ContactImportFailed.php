<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A contact import problem whose message is written for the team and is safe
 * to show on the import page. Any other exception is shown as a generic error.
 */
class ContactImportFailed extends RuntimeException {}
