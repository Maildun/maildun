<?php

namespace App\Http\Controllers;

use App\Actions\Automations\BuildAutomationMergeData;
use App\Actions\Emails\BuildTrackedEmailHtml;
use App\Actions\Transactional\RenderTransactionalContent;
use App\Models\AutomationEmailDelivery;
use App\Models\EmailDelivery;
use App\Models\TransactionalEmailDelivery;
use Illuminate\Http\Response;

/**
 * The hosted copy of a sent email that {{ web_view_url }} points at.
 *
 * Every route here is signed rather than authenticated: the link lives in the
 * recipient's inbox and is meant to work without an account.
 */
class EmailWebViewController extends Controller
{
    /**
     * The policy for author-supplied markup served on the application origin.
     *
     * default-src does not cover form-action or frame-ancestors, so both are
     * closed explicitly: the page can neither post a lookalike sign-in form
     * nor be framed. The sandbox gives the page an opaque origin with no
     * scripts, forms or automatic features. Links still work: following one
     * navigates this top-level page, and target="_blank" opens a normal,
     * unsandboxed window for the destination.
     */
    private const string CONTENT_SECURITY_POLICY = "default-src 'none'; img-src * data:; style-src * 'unsafe-inline'; font-src * data:; media-src *; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; sandbox allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation";

    /**
     * Test copies have no delivery to host, so this page stands in for
     * {{ web_view_url }} without signing a live copy.
     */
    public function test(): Response
    {
        return $this->page(
            '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>'
            .e(__('Test copy'))
            .'</title></head><body style="font-family:Arial,Helvetica,sans-serif;padding:24px;color:#111">'
            .'<p>'.e(__('This is a test email. The real campaign hosts a personalized copy of this message in the browser.')).'</p>'
            .'</body></html>',
        );
    }

    /**
     * A campaign delivery renders the same tracked body it was sent with, so
     * links and the open pixel keep reporting against the delivery.
     */
    public function campaign(EmailDelivery $delivery, BuildTrackedEmailHtml $trackedHtml): Response
    {
        $delivery->loadMissing(['email' => fn ($query) => $query->withTrashed()]);

        return $this->page($trackedHtml->build($delivery));
    }

    /**
     * A transactional delivery stores the exact body it sent.
     */
    public function transactional(TransactionalEmailDelivery $delivery): Response
    {
        return $this->page($delivery->html);
    }

    /**
     * An automation delivery stores no body, so the template is rendered again
     * from the run's inputs. Editing the template after the send shows the
     * edited version here.
     */
    public function automation(
        AutomationEmailDelivery $delivery,
        BuildAutomationMergeData $mergeData,
        RenderTransactionalContent $renderer,
    ): Response {
        $delivery->loadMissing(['run', 'subscriber', 'transactionalEmail']);

        abort_if($delivery->subscriber === null || $delivery->transactionalEmail === null || $delivery->run === null, 404);

        $merge = $mergeData->handle($delivery->subscriber, $delivery->run->context, $delivery);

        return $this->page($renderer->html($delivery->transactionalEmail->html ?? '', $merge));
    }

    /**
     * Serve author-supplied markup as a page of its own.
     *
     * Scripts, forms and framing are refused by policy because the body is
     * authored by workspace members and served on the application origin. The
     * default "Powered by Maildun" credit is appended, linking to the source
     * code.
     */
    private function page(string $html): Response
    {
        return response($this->withAttribution($this->withoutHttpEquiv($html)))
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Security-Policy', self::CONTENT_SECURITY_POLICY)
            ->header('X-Frame-Options', 'DENY')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Disarm every http-equiv pragma, refresh above all, which would send a
     * visitor from this origin to any address the author chose without a
     * click. The response headers already carry the charset and the policy,
     * so the page needs none of them.
     *
     * The attribute is renamed wherever an attribute name could begin (after
     * whitespace, a slash, or a closing quote) rather than by parsing tags,
     * so no tokenizer quirk in the markup can hide one.
     */
    private function withoutHttpEquiv(string $html): string
    {
        return preg_replace('/(?<=[\t\n\f\r \/"\'])http-equiv(?=[\t\n\f\r ]*=)/i', 'data-http-equiv', $html) ?? $html;
    }

    private function withAttribution(string $html): string
    {
        $notice = '<div data-test="attribution-badge" style="margin:24px 0 0;padding:16px;text-align:center;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:20px;color:#6b7280">'
            .__('Powered by')
            .' <a href="'.htmlspecialchars((string) config('attribution.source_url'), ENT_QUOTES | ENT_HTML5).'" style="color:#6b7280;text-decoration:underline">Maildun</a>'
            .'</div>';

        if (preg_match('/<\/body>/i', $html) === 1) {
            return preg_replace_callback('/<\/body>/i', fn (): string => $notice.'</body>', $html, 1) ?? ($html.$notice);
        }

        return $html.$notice;
    }
}
