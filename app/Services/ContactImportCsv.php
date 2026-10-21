<?php

namespace App\Services;

use App\Enums\SubscriberLanguage;
use App\Exceptions\ContactImportFailed;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use Illuminate\Support\Str;

class ContactImportCsv
{
    public const int SAMPLE_ROWS = 5;

    private const string UTF8_BOM = "\xEF\xBB\xBF";

    /** Bytes read from the start of the file to detect its delimiter. */
    private const int DETECTION_BYTES = 65536;

    /** Data rows compared with the header to break delimiter ties. */
    private const int DETECTION_ROWS = 20;

    /** @var list<string> */
    private const array DELIMITERS = [',', ';', "\t", '|'];

    /** @var array<string, list<string>> */
    private const array FIELD_ALIASES = [
        'email' => ['email', 'e_mail', 'email_address', 'mail', 'emailaddress'],
        'first_name' => ['first_name', 'firstname', 'first', 'given_name', 'fname'],
        'last_name' => ['last_name', 'lastname', 'last', 'surname', 'family_name', 'lname'],
        'tags' => ['tags', 'tag', 'labels'],
    ];

    /** @var array<string, list<string>> Fields that live on the audience membership, offered only for audience imports. */
    private const array SUBSCRIBER_FIELD_ALIASES = [
        'language' => ['language', 'lang', 'language_code', 'locale'],
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
            throw new ContactImportFailed(__('The uploaded CSV file could not be read.'));
        }

        try {
            $this->skipByteOrderMark($stream);
            $sample = (string) fread($stream, self::DETECTION_BYTES);

            if (trim($sample) === '') {
                throw new ContactImportFailed(__('The CSV is empty.'));
            }

            $delimiter = $this->detectDelimiter($sample, strlen($sample) === self::DETECTION_BYTES);
            rewind($stream);
            $this->skipByteOrderMark($stream);
            $headers = $this->readRow($stream, $delimiter);

            if ($headers === false) {
                throw new ContactImportFailed(__('The CSV is empty.'));
            }

            $headers = array_map(fn (string $header): string => trim($header), $headers);
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

    /**
     * Move past a UTF-8 byte order mark so it never becomes part of the first
     * header, which would stop a quoted first header from being unquoted.
     *
     * @param  resource  $stream
     */
    public function skipByteOrderMark($stream): void
    {
        $start = ftell($stream);
        $bytes = fread($stream, 3);

        if ($bytes !== self::UTF8_BOM && $start !== false) {
            fseek($stream, $start);
        }
    }

    /**
     * Read a CSV language cell as an ISO 639-1 code ("en", "EN", "en-US") or an
     * English name ("English").
     */
    public function language(string $value): ?SubscriberLanguage
    {
        $normalized = Str::lower(trim($value));
        $code = Str::before(str_replace('_', '-', $normalized), '-');

        return SubscriberLanguage::tryFrom($code)
            ?? collect(SubscriberLanguage::cases())->first(
                fn (SubscriberLanguage $language): bool => Str::lower($language->label()) === $normalized,
            );
    }

    /**
     * Pick the delimiter that splits the header into the most columns, parsing
     * quotes so a comma inside a quoted "Name, Vorname" header does not count.
     * A delimiter that leaves stray quotes inside cells cut a quoted cell apart,
     * so it loses to one that parses cleanly. Ties go to the delimiter whose
     * data rows match the header width most often.
     */
    public function detectDelimiter(string $sample, bool $truncated = false): string
    {
        $best = ',';
        $bestScore = [0, 1, 0];

        foreach (self::DELIMITERS as $delimiter) {
            $rows = $this->parseSample($sample, $delimiter, $truncated);

            if ($rows === []) {
                continue;
            }

            $columns = count($rows[0]);
            $splitsQuotedCells = collect($rows)->flatten()->contains(fn (?string $cell): bool => str_contains((string) $cell, '"'));
            $score = [
                $splitsQuotedCells ? 0 : 1,
                $columns,
                count(array_filter(array_slice($rows, 1), fn (array $row): bool => count($row) === $columns)),
            ];

            if ($columns > 1 && $score > $bestScore) {
                $best = $delimiter;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * @return list<list<string|null>>
     */
    private function parseSample(string $sample, string $delimiter, bool $truncated): array
    {
        $stream = fopen('php://memory', 'w+b');

        if ($stream === false) {
            return [];
        }

        fwrite($stream, $sample);
        rewind($stream);
        $rows = [];

        while (count($rows) <= self::DETECTION_ROWS && ($row = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            if ($row !== [null]) {
                $rows[] = $row;
            }
        }

        fclose($stream);

        // The last row of a cut-off sample is usually incomplete.
        if ($truncated && count($rows) > 1) {
            array_pop($rows);
        }

        return $rows;
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
    public function suggestMapping(array $headers, array $attributeKeys = [], bool $forAudience = false): array
    {
        $used = [];
        $aliases = $forAudience ? [...self::FIELD_ALIASES, ...self::SUBSCRIBER_FIELD_ALIASES] : self::FIELD_ALIASES;

        return array_map(function (string $header) use ($attributeKeys, $aliases, &$used): ?string {
            $slug = Str::of($header)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
            $field = collect($aliases)
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
            ['value' => 'language', 'label' => __('Language')],
            ...$audience->audienceAttributes()->orderBy('position')->get(['name', 'key'])
                ->map(fn (AudienceAttribute $attribute): array => ['value' => 'attribute:'.$attribute->key, 'label' => $attribute->name])
                ->values()
                ->all(),
        ];
    }
}
