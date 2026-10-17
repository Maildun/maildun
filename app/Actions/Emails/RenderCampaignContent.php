<?php

namespace App\Actions\Emails;

use App\Enums\SubscriberLanguage;
use App\Models\Email;
use App\Models\Subscriber;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class RenderCampaignContent
{
    public const string PATTERN = '/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/';

    /** @return array<string, mixed> */
    public function mergeData(Subscriber $subscriber, CarbonInterface $date): array
    {
        $name = Str::of($subscriber->first_name.' '.$subscriber->last_name)->squish()->toString();

        return [
            ...($subscriber->attribute_values ?? []),
            'name' => $name !== '' ? $name : $subscriber->email,
            'first_name' => $subscriber->first_name ?? '',
            'last_name' => $subscriber->last_name ?? '',
            'email' => $subscriber->email,
            'language' => $subscriber->language->value ?? '',
            'language_name' => $subscriber->language?->label() ?? '',
            'day' => $date->format('d'),
            'day_name' => $date->format('l'),
            'month' => $date->format('m'),
            'month_name' => $date->format('F'),
            'year' => $date->format('Y'),
        ];
    }

    /** @return array<string, mixed> */
    public function testData(string $email, CarbonInterface $date): array
    {
        return [
            'name' => __('Sample Subscriber'),
            'first_name' => __('Sample'),
            'last_name' => __('Subscriber'),
            'email' => $email,
            'language' => SubscriberLanguage::English->value,
            'language_name' => SubscriberLanguage::English->label(),
            'day' => $date->format('d'),
            'day_name' => $date->format('l'),
            'month' => $date->format('m'),
            'month_name' => $date->format('F'),
            'year' => $date->format('Y'),
        ];
    }

    /**
     * Preview fills delivery-only link tags so {{ unsubscribe_url }} and
     * {{ web_view_url }} render as hrefs before a send creates those URLs.
     *
     * @return array<string, mixed>
     */
    public function previewData(Subscriber $subscriber, CarbonInterface $date): array
    {
        return [
            ...$this->mergeData($subscriber, $date),
            'unsubscribe_url' => '#unsubscribe',
            'web_view_url' => '#web-view',
            'subscribe_url' => '#subscribe',
        ];
    }

    /** @param array<string, mixed> $data */
    public function html(string $content, array $data): string
    {
        return $this->replace($content, $data, escape: true);
    }

    /** @param array<string, mixed> $data */
    public function text(string $content, array $data): string
    {
        return $this->replace($content, $data, escape: false);
    }

    /** @param array<string, mixed> $data */
    public function plainText(Email $email, array $data): string
    {
        $content = filled($email->plain_text)
            ? (string) $email->plain_text
            : $this->htmlToText($email->html ?? '');

        return $this->text($content, $data);
    }

    /**
     * Readable text for the text/plain part. Head content and hidden preview
     * text are not body copy, and a link keeps its target after its label so
     * text-only readers can still follow it.
     */
    public function htmlToText(string $html): string
    {
        $body = preg_replace([
            '/<head\b[^>]*>.*?<\/head>/is',
            '/<(style|script|title)\b[^>]*>.*?<\/\1>/is',
            '/<(div|span)\b[^>]*\bstyle\s*=\s*(["\'])[^"\']*display\s*:\s*none[^>]*>[^<]*<\/\1>/i',
        ], '', $html) ?? $html;
        $body = preg_replace_callback(
            '/<a\b[^>]*?\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is',
            fn (array $matches): string => $this->linkText(strip_tags($matches[3]), trim($matches[2])),
            $body,
        ) ?? $body;
        $withLineBreaks = preg_replace('/<br\b[^>]*>|<\/(p|div|li|h[1-6]|tr|td|th|table|blockquote)>/i', "\n", $body) ?? $body;
        $text = html_entity_decode(strip_tags($withLineBreaks), ENT_QUOTES | ENT_HTML5);

        return Str::of($text)
            ->replaceMatches('/[ \t]+/', ' ')
            ->replaceMatches('/ *\n */', "\n")
            ->replaceMatches('/\n{3,}/', "\n\n")
            ->trim()
            ->toString();
    }

    /** @param array<string, mixed> $data */
    private function replace(string $content, array $data, bool $escape): string
    {
        $content = $this->decodeEncodedTags($content);
        $content = $this->hydrateMarkdownMergeLinks($content, $escape);

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

    /**
     * EmailBuilder markdown often leaves `[Unsubscribe]({{ unsubscribe_url }})`
     * as text when marked cannot parse the merge tag as a URL. Turn those into
     * anchors before replacement so inboxes see a real link. Text has no
     * anchors, so there the link is written out after its label.
     */
    private function hydrateMarkdownMergeLinks(string $content, bool $asAnchors): string
    {
        return (string) preg_replace_callback(
            '/\[([^\]]+)\]\((\{\{\s*[a-zA-Z_][a-zA-Z0-9_]*\s*\}\})\)/',
            fn (array $matches): string => $asAnchors
                ? '<a href="'.$matches[2].'">'.e($matches[1]).'</a>'
                : $matches[1].' ('.$matches[2].')',
            $content,
        );
    }

    /**
     * Markdown URL encoding can turn {{ unsubscribe_url }} into
     * %7B%7B%20unsubscribe_url%20%7D%7D. Restore those before replacement.
     */
    private function decodeEncodedTags(string $content): string
    {
        return (string) preg_replace(
            '/%7B%7B(?:\s|%20)*([a-zA-Z_][a-zA-Z0-9_]*)(?:\s|%20)*%7D%7D/i',
            '{{ $1 }}',
            $content,
        );
    }

    /**
     * Only links a reader can follow are written out; merge tags count, since
     * they become delivery links once the text is rendered.
     */
    private function linkText(string $label, string $href): string
    {
        $label = trim(preg_replace('/\s+/', ' ', $label) ?? $label);

        if (preg_match('/^(https?:|mailto:|tel:|\{\{|%7B%7B)/i', $href) !== 1) {
            return $label;
        }

        if ($label === '' || $label === $href || 'mailto:'.$label === $href) {
            return $href;
        }

        return $label.' ('.$href.')';
    }
}
