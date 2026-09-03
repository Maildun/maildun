<?php

namespace App\Actions\Emails;

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
            'day' => $date->format('d'),
            'day_name' => $date->format('l'),
            'month' => $date->format('m'),
            'month_name' => $date->format('F'),
            'year' => $date->format('Y'),
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

    /** @param array<string, mixed> $data */
    private function replace(string $content, array $data, bool $escape): string
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

    private function htmlToText(string $html): string
    {
        $withLineBreaks = preg_replace('/<(br\s*\/?>|\/(p|div|li|h[1-6]))>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($withLineBreaks), ENT_QUOTES | ENT_HTML5);

        return Str::of($text)
            ->replaceMatches('/[ \t]+\n/', "\n")
            ->replaceMatches('/\n{3,}/', "\n\n")
            ->trim()
            ->toString();
    }
}
