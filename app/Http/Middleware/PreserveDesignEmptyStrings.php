<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;

/**
 * Empty strings inside a block design are real values (an image's alt text
 * defaults to ""). The schema rejects null, so this tree must not be nulled.
 */
class PreserveDesignEmptyStrings extends ConvertEmptyStringsToNull
{
    /**
     * @param  string  $key
     * @param  mixed  $value
     */
    protected function transform($key, $value): mixed
    {
        if ($value === '' && ($key === 'design' || str_starts_with($key, 'design.'))) {
            return '';
        }

        return parent::transform($key, $value);
    }
}
