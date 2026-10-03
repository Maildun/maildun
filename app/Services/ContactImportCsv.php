<?php

namespace App\Services;

use App\Models\Audience;
use App\Models\AudienceAttribute;
use Illuminate\Support\Str;
use RuntimeException;

class ContactImportCsv
{
    public const int SAMPLE_ROWS = 5;

    /** @var list<string> */
    private const array DELIMITERS = [',', ';', "\t", '|'];

    /** @var array<string, list<string>> */
    private const array FIELD_ALIASES = [
        'email' => ['email', 'e_mail', 'email_address', 'mail', 'emailaddress'],
        'first_name' => ['first_name', 'firstname', 'first', 'given_name', 'fname'],
        'last_name' => ['last_name', 'lastname', 'last', 'surname', 'family_name', 'lname'],
        'tags' => ['tags', 'tag', 'labels'],
    ];

    /**
     * Read the header, a few sample rows, and the data row count from a local CSV file.
     *
     * @return array{delimiter: string, headers: list<string>, sample_rows: list<list<string>>, total_rows: int}
     */
    public function analyze(string $path): array
    {
        $stream = fopen($path, 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException('The uploaded CSV file could not be read.');
        }

        try {
            $firstLine = fgets($stream);

            if ($firstLine === false || trim($firstLine) === '') {
                throw new RuntimeException('The CSV is empty.');
            }

            $delimiter = $this->detectDelimiter($firstLine);
            rewind($stream);
            $headers = $this->readRow($stream, $delimiter);

            if ($headers === false) {
                throw new RuntimeException('The CSV is empty.');
            }

            $headers = array_map(fn (string $header, int $index): string => trim(
                $index === 0 ? ltrim($header, "\xEF\xBB\xBF") : $header,
            ), $headers, array_keys($headers));
            $sampleRows = [];
            $totalRows = 0;

            while (($row = $this->readRow($stream, $delimiter)) !== false) {
                if ($this->isBlankRow($row)) {
                    continue;
                }

                $totalRows++;

                if (count($sampleRows) < self::SAMPLE_ROWS) {
                    $sampleRows[] = array_map(fn (string $value): string => trim($value), $row);
                }
            }
        } finally {
            fclose($stream);
        }

        return [
            'delimiter' => $delimiter,
            'headers' => $headers,
            'sample_rows' => $sampleRows,
            'total_rows' => $totalRows,
        ];
    }

    public function detectDelimiter(string $line): string
    {
        $counts = array_map(fn (string $delimiter): int => substr_count($line, $delimiter), self::DELIMITERS);
        $best = array_search(max($counts), $counts, true);

        return $counts[$best] > 0 ? self::DELIMITERS[$best] : ',';
    }

    /**
     * Read one CSV row and normalize it to UTF-8 strings.
     *
     * @param  resource  $stream
     * @return list<string>|false
     */
    public function readRow($stream, string $delimiter): array|false
    {
        $row = fgetcsv($stream, null, $delimiter, '"', '');

        if (! is_array($row)) {
            return false;
        }

        return array_map(function (mixed $value): string {
            $value = (string) $value;

            return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }, $row);
    }

    /** @param array<int, string|null> $row */
    public function isBlankRow(array $row): bool
    {
        return collect($row)->every(fn (mixed $value): bool => trim((string) $value) === '');
    }

    /**
     * Suggest an import field for each header, using each field at most once.
     *
     * @param  list<string>  $headers
     * @param  array<int, string>  $attributeKeys
     * @return list<string|null>
     */
    public function suggestMapping(array $headers, array $attributeKeys = []): array
    {
        $used = [];

        return array_map(function (string $header) use ($attributeKeys, &$used): ?string {
            $slug = Str::of($header)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
            $field = collect(self::FIELD_ALIASES)
                ->search(fn (array $aliases): bool => in_array($slug, $aliases, true));

            if ($field === false && in_array($slug, $attributeKeys, true)) {
                $field = 'attribute:'.$slug;
            }

            if ($field === false || in_array($field, $used, true)) {
                return null;
            }

            $used[] = $field;

            return $field;
        }, $headers);
    }

    /**
     * Fields a CSV column can be mapped to.
     *
     * @return list<array{value: string, label: string}>
     */
    public function fieldOptions(?Audience $audience): array
    {
        $options = [
            ['value' => 'email', 'label' => __('Email')],
            ['value' => 'first_name', 'label' => __('First name')],
            ['value' => 'last_name', 'label' => __('Last name')],
            ['value' => 'tags', 'label' => __('Tags')],
        ];

        if ($audience === null) {
            return $options;
        }

        return [
            ...$options,
            ...$audience->audienceAttributes()->orderBy('position')->get(['name', 'key'])
                ->map(fn (AudienceAttribute $attribute): array => ['value' => 'attribute:'.$attribute->key, 'label' => $attribute->name])
                ->values()
                ->all(),
        ];
    }
}
