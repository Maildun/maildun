<?php

namespace App\Http\Controllers;

use App\Actions\Audiences\NotifyAudienceOfSubscriptionChange;
use App\Enums\AutomationTrigger;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Models\EmailDelivery;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UnsubscribeController extends Controller
{
    public function __construct(private NotifyAudienceOfSubscriptionChange $notifyAudience) {}

    /**
     * Test copies have no delivery to sign, so this page explains the link
     * without opting anyone out.
     */
    public function showTest(): HttpResponse
    {
        $notice = '<div data-test="attribution-badge" style="margin:24px 0 0;padding:16px;text-align:center;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:20px;color:#6b7280">'
            .__('Powered by')
            .' <a href="'.htmlspecialchars((string) config('attribution.source_url'), ENT_QUOTES | ENT_HTML5).'" style="color:#6b7280;text-decoration:underline">Maildun</a>'
            .'</div>';

        return response(
            '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>'
            .e(__('Test unsubscribe link'))
            .'</title></head><body style="font-family:Arial,Helvetica,sans-serif;padding:24px;color:#111;text-align:center">'
            .'<h1>'.e(__('This is a test email')).'</h1>'
            .'<p>'.e(__('Opening this link does not unsubscribe anyone. The real campaign uses a live opt-out link for each recipient.')).'</p>'
            .$notice
            .'</body></html>'
        )
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Show the opt-out confirmation for a delivered campaign.
     */
    public function show(EmailDelivery $delivery): Response
    {
        $delivery->loadMissing(['subscriber', 'email' => fn ($query) => $query->withTrashed()->with('audience')]);

        return Inertia::render('unsubscribe/show', $this->props($delivery));
    }

    /**
     * Opt the recipient out of the audience this campaign was sent to.
     *
     * Mail providers deliver RFC 8058 one-click requests here with no session,
     * so this route is signed rather than authenticated and is exempt from CSRF.
     */
    public function store(Request $request, EmailDelivery $delivery): SymfonyResponse
    {
        $delivery->loadMissing(['subscriber', 'email' => fn ($query) => $query->withTrashed()->with('audience')]);

        $this->optOut($delivery->subscriber);

        // One-click providers only need a 2xx; they never render a page.
        if (! $request->header('X-Inertia')) {
            return response()->noContent();
        }

        $landingPage = $delivery->email->audience?->unsubscribed_url;

        // An Inertia visit cannot follow a cross-origin redirect, so the
        // browser is told to load the landing page itself.
        if (filled($landingPage)) {
            return Inertia::location($landingPage);
        }

        return redirect()->to($this->showUrl($delivery));
    }

    /**
     * Show the opt-out confirmation for mail that has no delivery row.
     *
     * Automation sends are not recorded as an EmailDelivery, so their opt-out is
     * keyed to the subscriber and the audience comes off the subscriber.
     */
    public function showForSubscriber(Subscriber $subscriber): Response
    {
        $subscriber->loadMissing('audience');

        return Inertia::render('unsubscribe/show', $this->subscriberProps($subscriber));
    }

    /**
     * Opt the recipient out of their audience.
     *
     * Signed rather than authenticated and CSRF exempt for the same RFC 8058
     * one-click reason as the campaign route above.
     */
    public function storeForSubscriber(Request $request, Subscriber $subscriber): SymfonyResponse
    {
        $subscriber->loadMissing('audience');

        $this->optOut($subscriber);

        if (! $request->header('X-Inertia')) {
            return response()->noContent();
        }

        $landingPage = $subscriber->audience->unsubscribed_url;

        // An Inertia visit cannot follow a cross-origin redirect, so the
        // browser is told to load the landing page itself.
        if (filled($landingPage)) {
            return Inertia::location($landingPage);
        }

        return redirect()->to($this->subscriberShowUrl($subscriber));
    }

    /**
     * The shared opt-out transition. Idempotent: a second click on the same link
     * neither restamps unsubscribed_at nor fires the lifecycle event again, but
     * it still cancels a pending request to rejoin.
     */
    private function optOut(?Subscriber $subscriber): void
    {
        if (! $subscriber instanceof Subscriber) {
            return;
        }

        if ($subscriber->status === SubscriberStatus::Unsubscribed) {
            $subscriber->update(['resubscribe_requested_at' => null]);

            return;
        }

        $subscriber->update([
            'status' => SubscriberStatus::Unsubscribed,
            'unsubscribed_at' => now(),
            'resubscribe_requested_at' => null,
        ]);

        event(new SubscriberLifecycleOccurred(AutomationTrigger::Unsubscribed, $subscriber));
        $this->notifyAudience->handle($subscriber, subscribed: false);
    }

    /**
     * @return array{
     *     email: string,
     *     audience: string|null,
     *     unsubscribed: bool,
     *     action: string
     * }
     */
    private function subscriberProps(Subscriber $subscriber): array
    {
        return [
            'email' => $subscriber->email,
            'audience' => $subscriber->audience->name,
            'unsubscribed' => $subscriber->status === SubscriberStatus::Unsubscribed,
            'action' => URL::signedRoute(
                'public.unsubscribe.subscriber.store',
                ['subscriber' => $subscriber],
            ),
        ];
    }

    private function subscriberShowUrl(Subscriber $subscriber): string
    {
        return URL::signedRoute(
            'public.unsubscribe.subscriber.show',
            ['subscriber' => $subscriber],
        );
    }

    /**
     * @return array{
     *     email: string,
     *     audience: string|null,
     *     unsubscribed: bool,
     *     action: string
     * }
     */
    private function props(EmailDelivery $delivery): array
    {
        return [
            'email' => $delivery->email_address,
            'audience' => $delivery->email->audience?->name,
            'unsubscribed' => $delivery->subscriber?->status === SubscriberStatus::Unsubscribed
                || $delivery->subscriber === null,
            'action' => URL::signedRoute('public.unsubscribe.store', ['delivery' => $delivery]),
        ];
    }

    private function showUrl(EmailDelivery $delivery): string
    {
        return URL::signedRoute('public.unsubscribe.show', ['delivery' => $delivery]);
    }
}
