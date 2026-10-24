<?php

namespace App\Concerns;

/**
 * Cells written to downloadable CSV and Excel files often hold text that
 * people typed into public forms or uploaded CSVs, so they must not run as
 * spreadsheet formulas or break the file.
 */
trait SanitizesSpreadsheetCells
{
    /**
     * Valid UTF-8 cells, with a leading quote on any cell a spreadsheet would
     * read as a formula. The first byte is checked without the /u modifier so
     * a cell with invalid UTF-8 later on is still neutralised.
     *
     * @param  array<int, string|int|null>  $cells
     * @return list<string>
     */
    protected function sanitizeCsvRow(array $cells): array
    {
        return array_values(array_map(function (string|int|null $cell): string {
            $value = mb_scrub((string) $cell, 'UTF-8');

            return $value !== '' && str_contains("=+-@\t\r", $value[0])
                ? "'".$value
                : $value;
        }, $cells));
    }

    /**
     * Text safe inside an XML 1.0 element: valid UTF-8 without the control
     * characters XML forbids, escaped.
     */
    protected function spreadsheetXmlText(string|int|null $cell): string
    {
        $value = (string) preg_replace(
            '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            mb_scrub((string) $cell, 'UTF-8'),
        );

        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
