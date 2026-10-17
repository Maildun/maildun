<?php

namespace App\Actions\Emails;

use Illuminate\Support\Str;

/**
 * Derives the plain-text part of an email from its HTML when the author did
 * not write one.
 */
class ConvertHtmlToText
{
    public function handle(string $html): string
    {
        // The head, styles, scripts and a hidden preview line are not part of
        // the readable body, but strip_tags would keep their text.
        $html = preg_replace('/<(head|style|script|title)\b[^>]*>.*?<\/\1\s*>/is', '', $html) ?? $html;
        $html = preg_replace('/<div\b[^>]*\bstyle\s*=\s*(["\'])[^"\']*display\s*:\s*none[^"\']*\1[^>]*>.*?<\/div\s*>/is', '', $html) ?? $html;

        // strip_tags drops hrefs, so write each link's target after its text.
        $html = preg_replace_callback(
            '/<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)<\/a\s*>/is',
            function (array $matches): string {
                $url = trim(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5));
                $label = trim(html_entity_decode(strip_tags($matches[3]), ENT_QUOTES | ENT_HTML5));
                $leadsSomewhere = $url !== '' && ! str_starts_with($url, '#');

                if ($label === '') {
                    return $leadsSomewhere ? $matches[2] : '';
                }

                if (! $leadsSomewhere || $label === $url) {
                    return $matches[3];
                }

                return $matches[3].' ('.$matches[2].')';
            },
            $html,
        ) ?? $html;

        $withLineBreaks = preg_replace('/<(br\s*\/?>|\/(p|div|li|h[1-6]))>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($withLineBreaks), ENT_QUOTES | ENT_HTML5);

        return Str::of($text)
            ->replaceMatches('/[ \t]+\n/', "\n")
            ->replaceMatches('/\n{3,}/', "\n\n")
            ->trim()
            ->toString();
    }
}
