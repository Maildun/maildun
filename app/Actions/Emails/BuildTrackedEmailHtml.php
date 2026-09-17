<?php

namespace App\Actions\Emails;

use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\EmailLink;
use App\Models\SubscribeForm;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;

class BuildTrackedEmailHtml
{
    /**
     * Authors can place the opt-out themselves with {{ unsubscribe_url }}.
     */
    public const string UNSUBSCRIBE_TAG = '/\{\{\s*unsubscribe_url\s*\}\}/';

    /**
     * Authors can offer a hosted copy of the email with {{ web_view_url }}.
     */
    public const string WEB_VIEW_TAG = '/\{\{\s*web_view_url\s*\}\}/';

    /**
     * Authors can link the audience subscribe form with {{ subscribe_url }}.
     */
    public const string SUBSCRIBE_TAG = '/\{\{\s*subscribe_url\s*\}\}/';

    /**
     * Merge keys that resolve to delivery links below, so no snapshotted
     * subscriber field may shadow them.
     *
     * @var list<string>
     */
    private const array LINK_KEYS = ['unsubscribe_url', 'web_view_url', 'subscribe_url'];

    public function __construct(private readonly RenderCampaignContent $renderer) {}

    /** @return list<string> */
    public function extractLinks(string $html, ?string $queryString = null): array
    {
        $html = $this->appendQueryString($html, $queryString);
        preg_match_all('/href\s*=\s*(["\'])(.*?)\1/i', $html, $matches);

        return array_values(collect($matches[2])
            ->map(fn (string $url): string => html_entity_decode($url, ENT_QUOTES | ENT_HTML5))
            ->filter(fn (string $url): bool => in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true))
            ->unique()
            ->values()
            ->all());
    }

    public function build(EmailDelivery $delivery): string
    {
        $delivery->loadMissing('email.links');
        $email = $delivery->email;
        $links = $email->links->keyBy('url_hash');
        $mergeData = Arr::except($delivery->merge_data ?? [], self::LINK_KEYS);
        $html = $this->renderer->html($email->html ?? '', $mergeData);
        $html = $this->appendQueryString($html, $email->query_string);

        if ($email->track_clicks) {
            $html = preg_replace_callback(
                '/href\s*=\s*(["\'])(.*?)\1/i',
                function (array $matches) use ($delivery, $links): string {
                    $url = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5);
                    $link = $links->get(hash('sha256', $url));

                    if (! $link instanceof EmailLink) {
                        return $matches[0];
                    }

                    $trackedUrl = URL::signedRoute('emails.track.click', [
                        'delivery' => $delivery,
                        'link' => $link,
                    ]);

                    return 'href='.$matches[1].htmlspecialchars($trackedUrl, ENT_QUOTES | ENT_HTML5).$matches[1];
                },
                $html,
            ) ?? $html;
        }

        $unsubscribeUrl = URL::signedRoute('public.unsubscribe.show', ['delivery' => $delivery]);
        $placed = 0;
        $html = preg_replace(
            self::UNSUBSCRIBE_TAG,
            htmlspecialchars($unsubscribeUrl, ENT_QUOTES | ENT_HTML5),
            $html,
            -1,
            $placed,
        ) ?? $html;

        $html = preg_replace(
            self::WEB_VIEW_TAG,
            htmlspecialchars(self::webViewUrl($delivery), ENT_QUOTES | ENT_HTML5),
            $html,
        ) ?? $html;

        $subscribeUrl = $this->subscribeFormUrl($email);

        if ($subscribeUrl !== null) {
            $html = preg_replace(
                self::SUBSCRIBE_TAG,
                htmlspecialchars($subscribeUrl, ENT_QUOTES | ENT_HTML5),
                $html,
            ) ?? $html;
        }

        $appended = '';

        if ($email->track_opens) {
            $pixelUrl = URL::signedRoute('emails.track.open', ['delivery' => $delivery]);
            $appended = '<img src="'.htmlspecialchars($pixelUrl, ENT_QUOTES | ENT_HTML5).'" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0" />';
        }

        // Every campaign needs a visible opt-out, but an author who placed the
        // merge tag themselves has already chosen where it goes.
        if ($placed === 0) {
            $appended = $this->unsubscribeFooter($unsubscribeUrl).$appended;
        }

        if (preg_match('/<\/body>/i', $html) === 1) {
            return preg_replace_callback('/<\/body>/i', fn (): string => $appended.'</body>', $html, 1) ?? ($html.$appended);
        }

        return $html.$appended;
    }

    /**
     * The signed page that serves this delivery's rendered body in a browser.
     */
    public static function webViewUrl(EmailDelivery $delivery): string
    {
        return URL::signedRoute('public.web_view.show', ['delivery' => $delivery]);
    }

    /**
     * The public subscribe form for this campaign's audience, if one is published.
     */
    public function subscribeFormUrl(Email $email): ?string
    {
        if ($email->audience_id === null) {
            return null;
        }

        $form = SubscribeForm::query()
            ->where('audience_id', $email->audience_id)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();

        if (! $form instanceof SubscribeForm) {
            return null;
        }

        return route('public.subscribe_forms.show', $form);
    }

    /**
     * Authors who omit {{ unsubscribe_url }} still get a footer, but a
     * generic opt-out is weaker for inbox placement than one they placed.
     */
    public function authorPlacedUnsubscribe(string $html): bool
    {
        if (preg_match(self::UNSUBSCRIBE_TAG, $html) === 1) {
            return true;
        }

        return preg_match(
            '/%7B%7B(?:\s|%20)*unsubscribe_url(?:\s|%20)*%7D%7D/i',
            $html,
        ) === 1;
    }

    /**
     * Draft preview uses the same query string, merge-tag links, and footer as
     * a send, but with placeholder hrefs instead of signed delivery URLs.
     */
    public function preparePreviewHtml(
        string $html,
        string $unsubscribeUrl,
        string $webViewUrl,
        ?string $queryString,
        string $subscribeUrl = '#subscribe',
    ): string {
        $html = $this->appendQueryString($html, $queryString);

        $placed = 0;
        $html = preg_replace(
            self::UNSUBSCRIBE_TAG,
            htmlspecialchars($unsubscribeUrl, ENT_QUOTES | ENT_HTML5),
            $html,
            -1,
            $placed,
        ) ?? $html;

        $html = preg_replace(
            self::WEB_VIEW_TAG,
            htmlspecialchars($webViewUrl, ENT_QUOTES | ENT_HTML5),
            $html,
        ) ?? $html;

        $html = preg_replace(
            self::SUBSCRIBE_TAG,
            htmlspecialchars($subscribeUrl, ENT_QUOTES | ENT_HTML5),
            $html,
        ) ?? $html;

        if ($placed === 0) {
            $footer = $this->unsubscribeFooter($unsubscribeUrl);

            if (preg_match('/<\/body>/i', $html) === 1) {
                return preg_replace_callback(
                    '/<\/body>/i',
                    fn (): string => $footer.'</body>',
                    $html,
                    1,
                ) ?? ($html.$footer);
            }

            return $html.$footer;
        }

        return $html;
    }

    public function appendQueryString(string $html, ?string $queryString): string
    {
        if (blank($queryString)) {
            return $html;
        }

        return preg_replace_callback('/href\s*=\s*(["\'])(.*?)\1/i', function (array $matches) use ($queryString): string {
            $url = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5);

            if (! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                return $matches[0];
            }

            $fragmentPosition = strpos($url, '#');
            $fragment = $fragmentPosition === false ? '' : substr($url, $fragmentPosition);
            $baseUrl = $fragmentPosition === false ? $url : substr($url, 0, $fragmentPosition);
            $separator = str_contains($baseUrl, '?') ? '&' : '?';
            $taggedUrl = $baseUrl.$separator.$queryString.$fragment;

            return 'href='.$matches[1].htmlspecialchars($taggedUrl, ENT_QUOTES | ENT_HTML5).$matches[1];
        }, $html) ?? $html;
    }

    private function unsubscribeFooter(string $url): string
    {
        return '<div style="margin:24px 0 0;padding:16px;text-align:center;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#6b7280">'
            .'<a href="'.htmlspecialchars($url, ENT_QUOTES | ENT_HTML5).'" style="color:#6b7280;text-decoration:underline">'
            .__('Unsubscribe')
            .'</a>'
            .'</div>';
    }
}
