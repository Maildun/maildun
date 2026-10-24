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
    public const array DELIMITERS = [',', ';', "\t", '|'];

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
     * Bring an uploaded file into the shape the importer reads: UTF-8 (UTF-16
     * files from Excel's "Unicode text" export are converted), \n or \r\n
     * line endings (old Mac CR-only files are converted), and the header on
     * the first line (leading blank lines and an Excel "sep=;" line are
     * removed, the latter's delimiter returned).
     *
     * The returned path is a new temporary file when anything changed; the
     * caller must delete it.
     *
     * @return array{path: string, delimiter: string|null}
     */
    public function normalize(string $path): array
    {
        $original = @file_get_contents($path);

        if ($original === false) {
            throw new ContactImportFailed(__('The uploaded CSV file could not be read.'));
        }

        $contents = $this->decodeUtf16($original);

        if (str_contains($contents, "\r") && ! str_contains($contents, "\n")) {
            $contents = str_replace("\r", "\n", $contents);
        }

        if (str_starts_with($contents, self::UTF8_BOM)) {
            $contents = substr($contents, strlen(self::UTF8_BOM));
        }

        $contents = (string) preg_replace('/\A(?:[ \t]*\r?\n)+/', '', $contents);
        $delimiter = null;

        if (preg_match('/\Asep=(.)[ \t]*\r?\n/i', $contents, $matches) === 1 && in_array($matches[1], self::DELIMITERS, true)) {
            $delimiter = $matches[1];
            $contents = (string) preg_replace('/\A(?:[ \t]*\r?\n)+/', '', substr($contents, strlen($matches[0])));
        }

        // A file that only differs by its UTF-8 BOM is read as is: the reader
        // skips the BOM itself.
        $originalWithoutBom = str_starts_with($original, self::UTF8_BOM) ? substr($original, strlen(self::UTF8_BOM)) : $original;

        if ($contents === $originalWithoutBom) {
            return ['path' => $path, 'delimiter' => $delimiter];
        }

        $normalizedPath = tempnam(sys_get_temp_dir(), 'contact-import');

        if ($normalizedPath === false || file_put_contents($normalizedPath, $contents) === false) {
            throw new ContactImportFailed(__('The uploaded CSV file could not be read.'));
        }

        return ['path' => $normalizedPath, 'delimiter' => $delimiter];
    }

    /**
     * Delimiters a user can choose when automatic detection guessed wrong.
     *
     * @return list<array{value: string, label: string}>
     */
    public function delimiterOptions(): array
    {
        return [
            ['value' => ',', 'label' => __('Comma')],
            ['value' => ';', 'label' => __('Semicolon')],
            ['value' => "\t", 'label' => __('Tab')],
            ['value' => '|', 'label' => __('Pipe')],
        ];
    }

    /**
     * Read the header, a few sample rows, and the data row count from a local
     * CSV file, detecting the delimiter unless one is given.
     *
     * @return array{delimiter: string, headers: list<string>, sample_rows: list<list<string>>, total_rows: int}
     */
    public function analyze(string $path, ?string $delimiter = null): array
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

            $delimiter ??= $this->detectDelimiter($sample, strlen($sample) === self::DETECTION_BYTES);
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
     * Convert UTF-16 text, recognised by its byte order mark or, without one,
     * by the zero byte in every other position of ASCII text, to UTF-8.
     */
    private function decodeUtf16(string $contents): string
    {
        $encoding = match (substr($contents, 0, 2)) {
            "\xFF\xFE" => 'UTF-16LE',
            "\xFE\xFF" => 'UTF-16BE',
            default => null,
        };

        if ($encoding !== null) {
            return mb_convert_encoding(substr($contents, 2), 'UTF-8', $encoding);
        }

        $head = substr($contents, 0, 4096);
        $length = strlen($head) - strlen($head) % 2;

        if ($length < 2 || substr_count($head, "\0") < $length / 4) {
            return $contents;
        }

        $oddZeros = 0;
        $evenZeros = 0;

        for ($index = 0; $index < $length; $index++) {
            if ($head[$index] === "\0") {
                $index % 2 === 1 ? $oddZeros++ : $evenZeros++;
            }
        }

        return mb_convert_encoding($contents, 'UTF-8', $oddZeros >= $evenZeros ? 'UTF-16LE' : 'UTF-16BE');
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
