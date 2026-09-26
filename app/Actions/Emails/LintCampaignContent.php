<?php

namespace App\Actions\Emails;

use App\Models\Email;

class LintCampaignContent
{
    /**
     * Gmail clips a message body larger than about 102 KB and hides the rest,
     * including the unsubscribe footer, behind a "View entire message" link.
     */
    public const int GMAIL_CLIP_BYTES = 102 * 1024;

    /** Merge tags every campaign can use, besides the audience's own attributes. */
    private const array STANDARD_TAGS = [
        'name', 'first_name', 'last_name', 'email',
        'day', 'day_name', 'month', 'month_name', 'year',
        'unsubscribe_url', 'web_view_url', 'subscribe_url',
    ];

    /**
     * Problems worth fixing before a campaign goes out. Nothing here blocks a
     * send: warnings are likely to hurt delivery or rendering, notices are
     * suggestions.
     *
     * @return list<array{level: 'warning'|'notice', code: string, message: string}>
     */
    public function handle(Email $email): array
    {
        $html = (string) ($email->html ?? '');
        $subject = (string) $email->subject;
        $issues = [];

        if (strlen($html) > self::GMAIL_CLIP_BYTES) {
            $issues[] = $this->warning('gmail_clipping', __('The email is :size KB. Gmail clips messages over 102 KB and hides the rest, including the unsubscribe footer.', [
                'size' => (int) ceil(strlen($html) / 1024),
            ]));
        }

        $unknownTags = $this->unknownTags($email, $subject.' '.$email->preheader.' '.$html.' '.$email->plain_text);

        if ($unknownTags !== []) {
            $issues[] = $this->warning('unknown_merge_tags', __('These merge tags are not audience fields, so they will be sent as written: :tags.', [
                'tags' => collect($unknownTags)->map(fn (string $tag): string => '{{ '.$tag.' }}')->join(', '),
            ]));
        }

        $imagesWithoutAlt = preg_match_all('/<img\b(?![^>]*\balt\s*=)[^>]*>/i', $html);

        if ($imagesWithoutAlt > 0) {
            $issues[] = $this->warning('images_without_alt', trans_choice(
                '1 image has no alt text, so readers with images blocked or using a screen reader see nothing.|:count images have no alt text, so readers with images blocked or using a screen reader see nothing.',
                $imagesWithoutAlt,
            ));
        }

        $insecureLinks = preg_match_all('/<a\b[^>]*\bhref\s*=\s*["\']http:\/\//i', $html);

        if ($insecureLinks > 0) {
            $issues[] = $this->notice('insecure_links', trans_choice(
                '1 link uses http:// instead of https://. Some mail clients warn about insecure links.|:count links use http:// instead of https://. Some mail clients warn about insecure links.',
                $insecureLinks,
            ));
        }

        $letters = preg_replace('/[^\p{L}]/u', '', $subject) ?? '';

        if (mb_strlen($letters) >= 8 && $letters === mb_strtoupper($letters)) {
            $issues[] = $this->warning('shouting_subject', __('The subject is all capital letters, which spam filters often penalize.'));
        }

        if (preg_match('/[!?]{3,}/', $subject) === 1) {
            $issues[] = $this->warning('subject_punctuation', __('The subject repeats ! or ?, which spam filters often penalize.'));
        }

        if (blank($email->preheader)) {
            $issues[] = $this->notice('missing_preheader', __('There is no preheader, so inboxes show the first words of the body next to the subject.'));
        }

        return $issues;
    }

    /**
     * Tag names are case sensitive when rendered, so {{ First_Name }} is sent
     * as written and is reported here.
     *
     * @return list<string>
     */
    private function unknownTags(Email $email, string $content): array
    {
        preg_match_all(RenderCampaignContent::PATTERN, $content, $matches);

        $known = [
            ...self::STANDARD_TAGS,
            ...($email->audience?->audienceAttributes()->pluck('key')->all() ?? []),
        ];

        return array_values(array_unique(array_filter(
            $matches[1],
            fn (string $tag): bool => ! in_array($tag, $known, true),
        )));
    }

    /** @return array{level: 'warning', code: string, message: string} */
    private function warning(string $code, string $message): array
    {
        return ['level' => 'warning', 'code' => $code, 'message' => $message];
    }

    /** @return array{level: 'notice', code: string, message: string} */
    private function notice(string $code, string $message): array
    {
        return ['level' => 'notice', 'code' => $code, 'message' => $message];
    }
}
