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

        abort_if($delivery->subscriber === null || $delivery->transactionalEmail === null, 404);

        $merge = $mergeData->handle($delivery->subscriber, $delivery->run->context, $delivery);

        return $this->page($renderer->html($delivery->transactionalEmail->html ?? '', $merge));
    }

    /**
     * Serve author-supplied markup as a page of its own.
     *
     * Scripts are refused by policy because the body is authored by workspace
     * members and served on the application origin. The attribution notice is
     * a license condition on every public page; see LICENSE.
     */
    private function page(string $html): Response
    {
        return response($this->withAttribution($html))
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Security-Policy', "default-src 'none'; img-src * data:; style-src * 'unsafe-inline'; font-src * data:; media-src *; base-uri 'none'")
            ->header('X-Robots-Tag', 'noindex, nofollow');
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
