<?php

namespace App\Actions\Emails;

use App\Enums\SubscriberLanguage;
use App\Models\AudienceAttribute;
use App\Models\Email;
use App\Models\Subscriber;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class RenderCampaignContent
{
    public const string PATTERN = '/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/';

    public function __construct(private readonly ConvertHtmlToText $htmlToText = new ConvertHtmlToText) {}

    /**
     * Every audience field is seeded with a blank so a subscriber with no
     * stored value gets an empty string instead of the raw {{ key }} tag.
     * Pass the audience's field keys when rendering many subscribers at once;
     * otherwise they are read from the subscriber's audience.
     *
     * @param  array<int, string>|null  $attributeKeys
     * @return array<string, mixed>
     */
    public function mergeData(Subscriber $subscriber, CarbonInterface $date, ?array $attributeKeys = null): array
    {
        $name = Str::of($subscriber->first_name.' '.$subscriber->last_name)->squish()->toString();
        $attributeKeys ??= self::attributeKeys($subscriber->audience_id);

        return [
            ...array_fill_keys($attributeKeys, ''),
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

    /**
     * The field keys configured on an audience.
     *
     * @return array<int, string>
     */
    public static function attributeKeys(?int $audienceId): array
    {
        if ($audienceId === null) {
            return [];
        }

        return AudienceAttribute::query()
            ->where('audience_id', $audienceId)
            ->get(['key'])
            ->map(fn (AudienceAttribute $attribute): string => $attribute->key)
            ->values()
            ->all();
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

    /**
     * The text part of a send. Pass the delivery's link data so
     * {{ unsubscribe_url }} and the other link tags resolve; when the author
     * did not place the opt-out, it is appended the way the HTML footer is.
     *
     * @param  array<string, mixed>  $data
     */
    public function plainText(Email $email, array $data): string
    {
        $content = filled($email->plain_text)
            ? (string) $email->plain_text
            : $this->htmlToText->handle($email->html ?? '');

        $content = $this->decodeEncodedTags($content);

        if (filled($data['unsubscribe_url'] ?? null) && preg_match(BuildTrackedEmailHtml::UNSUBSCRIBE_TAG, $content) !== 1) {
            $content = rtrim($content)."\n\n".__('Unsubscribe').': {{ unsubscribe_url }}';
        }

        return $this->text($content, $data);
    }

    /** @param array<string, mixed> $data */
    private function replace(string $content, array $data, bool $escape): string
    {
        $content = $this->decodeEncodedTags($content);

        // Only markup gets anchors; a text part keeps the author's link text.
        if ($escape) {
            $content = $this->hydrateMarkdownMergeLinks($content);
        }

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
     * anchors before replacement so inboxes see a real link.
     */
    private function hydrateMarkdownMergeLinks(string $content): string
    {
        return (string) preg_replace_callback(
            '/\[([^\]]+)\]\((\{\{\s*[a-zA-Z_][a-zA-Z0-9_]*\s*\}\})\)/',
            fn (array $matches): string => '<a href="'.$matches[2].'">'.e($matches[1]).'</a>',
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
}
