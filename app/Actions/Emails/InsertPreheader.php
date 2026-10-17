<?php

namespace App\Actions\Emails;

/**
 * Puts the preheader into outgoing HTML as the hidden preview line inboxes
 * show next to the subject.
 *
 * It goes straight after the opening body tag so it is the first text an
 * inbox reads, ahead of any preheader a builder design carries itself. The
 * filler stops the inbox from pulling body text into the rest of the preview,
 * matching the @maildun/email-builder renderer.
 */
class InsertPreheader
{
    private const string FILLER = '&#847;&zwnj;&nbsp;';

    private const int FILLER_REPEAT = 80;

    /**
     * @param  string  $preheader  Plain text with merge tags already replaced.
     */
    public function handle(string $html, string $preheader): string
    {
        $preheader = trim($preheader);

        if ($preheader === '') {
            return $html;
        }

        $block = '<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all">'
            .e($preheader)
            .str_repeat(self::FILLER, self::FILLER_REPEAT)
            .'</div>';

        // A document may leave out the optional <body> tag, so fall back to
        // the end of the head, then the <html> tag, then the doctype, and only
        // prepend to a true fragment.
        foreach (['/<body\b[^>]*>/i', '/<\/head\s*>/i', '/<html\b[^>]*>/i', '/<!doctype\b[^>]*>/i'] as $pattern) {
            if (preg_match($pattern, $html, $matches, PREG_OFFSET_CAPTURE) === 1) {
                $position = $matches[0][1] + strlen($matches[0][0]);

                return substr($html, 0, $position).$block.substr($html, $position);
            }
        }

        return $block.$html;
    }
}
