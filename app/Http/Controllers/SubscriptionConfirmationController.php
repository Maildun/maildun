<?php

namespace App\Http\Controllers;

use App\Actions\Audiences\ConfirmSubscriberSubscription;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionConfirmationController extends Controller
{
    public function confirm(Request $request, Subscriber $subscriber, ConfirmSubscriberSubscription $confirmSubscriberSubscription): Response
    {
        $subscriber = $confirmSubscriberSubscription->handle($subscriber, $request->string('resubscribe')->toString() ?: null);

        return Inertia::render('subscribe-forms/confirmed', [
            'audienceName' => $subscriber->audience->name,
            'confirmed' => $subscriber->isOptedIn(),
        ]);
    }
}
