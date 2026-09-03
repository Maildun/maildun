<?php

namespace App\Actions\Transactional;

class RenderTransactionalContent
{
    /**
     * Merge tags look like {{ first_name }}, with optional spaces around the key.
     */
    public const string PATTERN = '/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/';

    /**
     * Replace merge tags in HTML, escaping values so caller-supplied data cannot
     * inject markup.
     *
     * @param  array<string, mixed>  $data
     */
    public function html(string $content, array $data): string
    {
        return $this->replace($content, $data, escape: true);
    }

    /**
     * Replace merge tags in plain text (subject, preheader) without HTML escaping.
     *
     * @param  array<string, mixed>  $data
     */
    public function text(string $content, array $data): string
    {
        return $this->replace($content, $data, escape: false);
    }

    /**
     * Keys that appear in any of the given strings, in first-seen order.
     *
     * @return list<string>
     */
    public function detect(string ...$contents): array
    {
        $keys = [];

        foreach ($contents as $content) {
            if ($content === '') {
                continue;
            }

            preg_match_all(self::PATTERN, $content, $matches);

            foreach ($matches[1] as $key) {
                if (! in_array($key, $keys, true)) {
                    $keys[] = $key;
                }
            }
        }

        return $keys;
    }

    /**
     * Keep declared examples and extra keys, then append newly detected ones.
     *
     * @param  list<array{key?: mixed, example?: mixed}>  $declared
     * @param  list<string>  $detected
     * @return list<array{key: string, example: string}>
     */
    public function merge(array $declared, array $detected): array
    {
        $merged = [];
        $seen = [];

        foreach ($declared as $variable) {
            $key = is_string($variable['key'] ?? null) ? $variable['key'] : '';

            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $example = $variable['example'] ?? '';
            $merged[] = [
                'key' => $key,
                'example' => is_string($example) ? $example : '',
            ];
            $seen[$key] = true;
        }

        foreach ($detected as $key) {
            if (isset($seen[$key])) {
                continue;
            }

            $merged[] = ['key' => $key, 'example' => ''];
            $seen[$key] = true;
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function replace(string $content, array $data, bool $escape): string
    {
        return (string) preg_replace_callback(self::PATTERN, function (array $matches) use ($data, $escape): string {
            $key = $matches[1];

            if (! array_key_exists($key, $data)) {
                return $matches[0];
            }

            $value = $data[$key];
            $string = is_scalar($value) || $value === null ? (string) $value : '';

            return $escape ? e($string) : $string;
        }, $content);
    }
}
