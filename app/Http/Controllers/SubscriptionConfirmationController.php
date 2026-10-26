<?php

namespace App\Http\Controllers;

use App\Actions\Audiences\ConfirmSubscriberSubscription;
use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionConfirmationController extends Controller
{
    /**
     * Opening the link only shows a Confirm button. Mail security scanners
     * fetch every link in a message, so a GET or HEAD must never confirm.
     */
    public function show(Request $request, Subscriber $subscriber, ConfirmSubscriberSubscription $confirmSubscriberSubscription): Response
    {
        $subscriber->loadMissing('audience');

        return Inertia::render('subscribe-forms/confirmed', [
            'audienceName' => $subscriber->audience->name,
            'confirmed' => $subscriber->isOptedIn(),
            'canConfirm' => $confirmSubscriberSubscription->canConfirm($subscriber, $this->resubscribeToken($request)),
            'confirmUrl' => $request->getRequestUri(),
        ]);
    }

    /**
     * The Confirm button posts back to the same signed URL, then returns to it
     * so a refresh never resubmits.
     */
    public function store(Request $request, Subscriber $subscriber, ConfirmSubscriberSubscription $confirmSubscriberSubscription): RedirectResponse
    {
        $confirmSubscriberSubscription->handle($subscriber, $this->resubscribeToken($request));

        return redirect()->to($request->fullUrl(), 303);
    }

    private function resubscribeToken(Request $request): ?string
    {
        return $request->string('resubscribe')->toString() ?: null;
    }
}
