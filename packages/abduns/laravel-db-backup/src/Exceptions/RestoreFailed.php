<?php

namespace Abduns\DbBackup\Exceptions;

use Illuminate\Contracts\Process\ProcessResult;
use RuntimeException;

class RestoreFailed extends RuntimeException
{
    public static function fromProcess(string $binary, ProcessResult $result): self
    {
        $output = trim($result->errorOutput()) ?: trim($result->output());

        return new self(sprintf(
            '%s exited with code %s%s',
            $binary,
            $result->exitCode() ?? 'unknown',
            $output === '' ? '.' : ': '.$output,
        ));
    }
}
