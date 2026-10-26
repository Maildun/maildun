<?php

namespace App\Concerns;

use Illuminate\Http\Request;

/**
 * List filters come from a hand-editable query string, where `?search[]=x`
 * arrives as an array. Reading one as a string would fail with a server error,
 * so anything that is not a string counts as no filter.
 */
trait ReadsQueryFilters
{
    protected function queryFilter(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? trim($value) : '';
    }
}
