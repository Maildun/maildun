<?php

namespace App\Actions\Emails;

class GuardMergedUrlScheme
{
    /** @var list<string> */
    private const array SAFE_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * A URL attribute value up to the merge tag, with the author's text so far
     * in whichever quoting style the attribute uses.
     */
    private const string URL_ATTRIBUTE_PREFIX = '/(?<![\w-])(?:href|src|action|formaction|background|poster)\s*=\s*(?:"([^"]*)|\'([^\']*)|([^\s"\'>]*))$/i';

    /**
     * The value to merge into the HTML at $offset.
     *
     * Escaping keeps a merge value from breaking out of an attribute, but not
     * from choosing a link's scheme: a subscriber field of
     * "javascript:alert(1)" merged into href="{{ website }}" stays a working
     * script link. When the tag decides the scheme of an href, src or similar
     * URL, only http, https and mailto are kept; anything else becomes "#". A
     * tag after an author-written scheme, as in href="tel:{{ phone }}" or a
     * query string, cannot change it and is left alone.
     */
    public function handle(string $content, int $offset, string $value): string
    {
        if (! $this->decidesUrlScheme($content, $offset)) {
            return $value;
        }

        return $this->hasUnsafeScheme($value) ? '#' : $value;
    }

    private function decidesUrlScheme(string $content, int $offset): bool
    {
        $tagStart = $offset === 0 ? false : strrpos($content, '<', $offset - strlen($content) - 1);

        if ($tagStart === false) {
            return false;
        }

        if (preg_match(self::URL_ATTRIBUTE_PREFIX, substr($content, $tagStart, $offset - $tagStart), $matches) !== 1) {
            return false;
        }

        $authorPrefix = $matches[1].($matches[2] ?? '').($matches[3] ?? '');

        return ! str_contains($authorPrefix, ':');
    }

    /**
     * Browsers drop control characters and whitespace around and inside a
     * scheme, so "java\tscript:" is still a script link.
     */
    private function hasUnsafeScheme(string $value): bool
    {
        $normalized = (string) preg_replace('/[\x00-\x20]+/', '', $value);

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $normalized, $matches) !== 1) {
            return false;
        }

        return ! in_array(strtolower($matches[1]), self::SAFE_SCHEMES, true);
    }
}
