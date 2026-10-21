<?php

namespace App\Http\Controllers;

use App\Actions\Audiences\NotifyAudienceOfSubscriptionChange;
use App\Actions\Audiences\UnsubscribeSubscriber;
use App\Enums\SubscriberStatus;
use App\Models\Audience;
use App\Models\AutomationEmailDelivery;
use App\Models\EmailDelivery;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UnsubscribeController extends Controller
{
    public function __construct(
        private UnsubscribeSubscriber $unsubscribeSubscriber,
        private NotifyAudienceOfSubscriptionChange $notifyAudience,
    ) {}

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

        $this->optOut($this->currentMembership($delivery));

        // One-click providers only need a 2xx; they never render a page. The
        // page posts over XHR, so an external landing page needs an Inertia
        // location visit: a plain redirect would be followed silently.
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
     * Show the opt-out confirmation for automation mail.
     *
     * Automation links are keyed to the subscriber, not a campaign delivery, so
     * the audience comes off the subscriber, or off the automation delivery
     * once that member is gone.
     */
    public function showForSubscriber(string $subscriber): Response
    {
        return Inertia::render('unsubscribe/show', $this->subscriberProps($subscriber, $this->automationRecipient($subscriber)));
    }

    /**
     * Opt the recipient out of their audience.
     *
     * Signed rather than authenticated and CSRF exempt for the same RFC 8058
     * one-click reason as the campaign route above.
     */
    public function storeForSubscriber(Request $request, string $subscriber): SymfonyResponse
    {
        $recipient = $this->automationRecipient($subscriber);

        $this->optOut($recipient['membership']);

        if (! $request->header('X-Inertia')) {
            return response()->noContent();
        }

        $landingPage = $recipient['audience']->unsubscribed_url;

        // An Inertia visit cannot follow a cross-origin redirect, so the
        // browser is told to load the landing page itself.
        if (filled($landingPage)) {
            return Inertia::location($landingPage);
        }

        return redirect()->to($this->subscriberShowUrl($subscriber));
    }

    /**
     * Opting out reuses the app's idempotent transition. A link whose member
     * is gone has nothing left to opt out.
     */
    private function optOut(?Subscriber $subscriber): void
    {
        if (! $subscriber instanceof Subscriber) {
            return;
        }

        $wasUnsubscribed = $subscriber->status === SubscriberStatus::Unsubscribed;
        $subscriber = $this->unsubscribeSubscriber->handle($subscriber);

        if (! $wasUnsubscribed && $subscriber->status === SubscriberStatus::Unsubscribed) {
            $this->notifyAudience->handle($subscriber, subscribed: false);
        }
    }

    /**
     * @param  array{audience: Audience, email: string, membership: Subscriber|null}  $recipient
     * @return array{
     *     email: string,
     *     audience: string|null,
     *     unsubscribed: bool,
     *     action: string
     * }
     */
    private function subscriberProps(string $subscriberUuid, array $recipient): array
    {
        return [
            'email' => $recipient['email'],
            'audience' => $recipient['audience']->name,
            'unsubscribed' => $recipient['membership'] === null
                || $recipient['membership']->status === SubscriberStatus::Unsubscribed,
            'action' => URL::signedRoute(
                'public.unsubscribe.subscriber.store',
                ['subscriber' => $subscriberUuid],
            ),
        ];
    }

    /**
     * Who an automation link opts out. Removing the member nulls the
     * delivery's subscriber_id but keeps its subscriber_uuid, so when the
     * person was later added back the link falls back to their current
     * membership in the same audience, by contact and then by address. With
     * no membership left, the link shows them as unsubscribed. A uuid no
     * automation was ever sent to is a 404.
     *
     * @return array{audience: Audience, email: string, membership: Subscriber|null}
     */
    private function automationRecipient(string $subscriberUuid): array
    {
        $subscriber = Subscriber::query()->with('audience')->where('uuid', $subscriberUuid)->first();

        if ($subscriber instanceof Subscriber) {
            return ['audience' => $subscriber->audience, 'email' => $subscriber->email, 'membership' => $subscriber];
        }

        $delivery = AutomationEmailDelivery::query()
            ->with('audience')
            ->where('subscriber_uuid', $subscriberUuid)
            ->whereNotNull('audience_id')
            ->latest('id')
            ->first();

        abort_if($delivery?->audience === null, 404);

        $membership = $this->membershipIn($delivery->audience, $delivery->contact_id, $delivery->to_address);

        return [
            'audience' => $delivery->audience,
            'email' => $membership->email ?? $delivery->to_address,
            'membership' => $membership,
        ];
    }

    private function subscriberShowUrl(string $subscriberUuid): string
    {
        return URL::signedRoute(
            'public.unsubscribe.subscriber.show',
            ['subscriber' => $subscriberUuid],
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
            'unsubscribed' => $this->currentMembership($delivery)?->status !== SubscriberStatus::Subscribed,
            'action' => URL::signedRoute('public.unsubscribe.store', ['delivery' => $delivery]),
        ];
    }

    /**
     * The membership a campaign's opt-out applies to. Removing a member nulls
     * the delivery's subscriber_id, so when the person was later added back
     * the link falls back to their current membership in the same audience,
     * by contact and then by address. It never reaches another audience.
     */
    private function currentMembership(EmailDelivery $delivery): ?Subscriber
    {
        if ($delivery->subscriber instanceof Subscriber) {
            return $delivery->subscriber;
        }

        $audience = $delivery->email->audience;

        if ($audience === null) {
            return null;
        }

        return $this->membershipIn($audience, $delivery->contact_id, $delivery->email_address);
    }

    /**
     * The person's membership in one audience, by contact and then by address.
     */
    private function membershipIn(Audience $audience, ?int $contactId, string $address): ?Subscriber
    {
        return ($contactId !== null
            ? $audience->subscribers()->where('contact_id', $contactId)->first()
            : null)
            ?? $audience->subscribers()->where('email', Str::lower($address))->first();
    }

    private function showUrl(EmailDelivery $delivery): string
    {
        return URL::signedRoute('public.unsubscribe.show', ['delivery' => $delivery]);
    }
}
