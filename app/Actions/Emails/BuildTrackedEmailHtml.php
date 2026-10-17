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

    /**
     * The hidden preview block @maildun/email-builder renders for its own
     * preheader setting, so ours reads the same in every client.
     */
    private const string PREHEADER_STYLE = 'display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all';

    /**
     * Invisible padding after the preheader so inboxes do not pull body copy
     * into the preview.
     */
    private const string PREHEADER_FILLER = '&#847;&zwnj;&nbsp;';

    /**
     * A tag-free hidden block leading the body: the builder's preheader, or one
     * an author wrote into raw HTML.
     */
    private const string LEADING_PREHEADER = '/^\s*<(div|span)\b[^>]*\bstyle\s*=\s*(["\'])[^"\']*display\s*:\s*none[^>]*>[^<]*<\/\1>/i';

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

        $unsubscribeUrl = self::unsubscribeUrl($delivery);
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

        // After the link tags, so a tag in the preheader is never counted as
        // the author's opt-out.
        $html = $this->insertPreheader($html, $this->renderer->text((string) $email->preheader, $mergeData));

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
     * The text/plain part, carrying the same delivery links as the HTML part.
     * Merge data snapshots no links, so they are filled in here, and a text
     * that does not already link the opt-out gets one appended.
     */
    public function buildText(EmailDelivery $delivery): string
    {
        $delivery->loadMissing('email');
        $email = $delivery->email;
        $unsubscribeUrl = self::unsubscribeUrl($delivery);
        $links = [
            'unsubscribe_url' => $unsubscribeUrl,
            'web_view_url' => self::webViewUrl($delivery),
        ];
        $subscribeUrl = $this->subscribeFormUrl($email);

        if ($subscribeUrl !== null) {
            $links['subscribe_url'] = $subscribeUrl;
        }

        $text = $this->renderer->plainText($email, [
            ...Arr::except($delivery->merge_data ?? [], self::LINK_KEYS),
            ...$links,
        ]);

        return $this->appendTextUnsubscribeFooter($text, $unsubscribeUrl);
    }

    /**
     * Automation mail is rendered by the run rather than from a delivery row,
     * but it is marketing mail all the same: it gets the campaign fallback
     * footer, the preheader, and a text part that links the opt-out.
     *
     * @return array{html: string, text: string}
     */
    public function buildAutomationBody(string $html, ?string $preheader, string $unsubscribeUrl): array
    {
        // The rendered body only holds the URL if the author placed the tag.
        if (! str_contains($html, e($unsubscribeUrl))) {
            $html = $this->appendUnsubscribeFooter($html, $unsubscribeUrl);
        }

        return [
            'html' => $this->insertPreheader($html, $preheader),
            'text' => $this->appendTextUnsubscribeFooter($this->renderer->htmlToText($html), $unsubscribeUrl),
        ];
    }

    /**
     * Show the preheader as inbox preview text. A hidden block already leading
     * the body, such as the one the block editor renders from its own
     * preheader setting, is replaced so the message never carries two.
     */
    public function insertPreheader(string $html, ?string $preheader): string
    {
        if (blank($preheader)) {
            return $html;
        }

        $block = '<div style="'.self::PREHEADER_STYLE.'">'
            .htmlspecialchars($preheader, ENT_QUOTES | ENT_HTML5)
            .str_repeat(self::PREHEADER_FILLER, 80)
            .'</div>';

        if (preg_match('/<body\b[^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return $block.$this->withoutLeadingPreheader($html);
        }

        $bodyStart = $matches[0][1] + strlen($matches[0][0]);

        return substr($html, 0, $bodyStart).$block.$this->withoutLeadingPreheader(substr($html, $bodyStart));
    }

    /**
     * The signed confirmation page this delivery's opt-out links to.
     */
    public static function unsubscribeUrl(EmailDelivery $delivery): string
    {
        return URL::signedRoute('public.unsubscribe.show', ['delivery' => $delivery]);
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
     * Test copies go to a real inbox, so hash placeholders like #unsubscribe
     * are shown as raw text. Use https URLs that do not opt anyone out.
     */
    public function prepareTestHtml(Email $email, string $html): string
    {
        return $this->preparePreviewHtml(
            $html,
            route('public.unsubscribe.test'),
            route('public.web_view.test'),
            $email->query_string,
            $this->subscribeFormUrl($email) ?? route('public.web_view.test'),
        );
    }

    /**
     * The text part of a test copy links the same harmless pages as its HTML.
     *
     * @param  array<string, mixed>  $mergeData
     */
    public function prepareTestText(Email $email, array $mergeData): string
    {
        $unsubscribeUrl = route('public.unsubscribe.test');
        $text = $this->renderer->plainText($email, [
            ...$mergeData,
            'unsubscribe_url' => $unsubscribeUrl,
            'web_view_url' => route('public.web_view.test'),
            'subscribe_url' => $this->subscribeFormUrl($email) ?? route('public.web_view.test'),
        ]);

        return $this->appendTextUnsubscribeFooter($text, $unsubscribeUrl);
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
            return $this->appendUnsubscribeFooter($html, $unsubscribeUrl);
        }

        return $html;
    }

    /**
     * Put the visible opt-out footer just before </body>.
     */
    public function appendUnsubscribeFooter(string $html, string $url): string
    {
        $footer = $this->unsubscribeFooter($url);

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

    /**
     * Readers who only see the text part still need a working opt-out.
     */
    public function appendTextUnsubscribeFooter(string $text, string $url): string
    {
        if (str_contains($text, $url)) {
            return $text;
        }

        return ltrim(rtrim($text)."\n\n".__('Unsubscribe').': '.$url);
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

    private function withoutLeadingPreheader(string $html): string
    {
        return preg_replace(self::LEADING_PREHEADER, '', $html, 1) ?? $html;
    }
}
