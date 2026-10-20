<?php

namespace App\Concerns;

trait SanitizesCsvCells
{
    /**
     * Prefix cells a spreadsheet would run as a formula so they open as plain text.
     *
     * @param  list<int|string|null>  $cells
     * @return list<string>
     */
    private function sanitizeCsvRow(array $cells): array
    {
        return array_map(function (int|string|null $cell): string {
            $value = (string) $cell;

            return preg_match('/^[=+\-@\t\r]/u', $value) === 1
                ? "'".$value
                : $value;
        }, $cells);
    }
}
