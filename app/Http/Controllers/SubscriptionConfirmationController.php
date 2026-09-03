<?php

namespace App\Http\Controllers;

use App\Actions\Audiences\ConfirmSubscriberSubscription;
use App\Models\Subscriber;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionConfirmationController extends Controller
{
    public function confirm(Subscriber $subscriber, ConfirmSubscriberSubscription $confirmSubscriberSubscription): Response
    {
        $subscriber = $confirmSubscriberSubscription->handle($subscriber);

        return Inertia::render('subscribe-forms/confirmed', [
            'audienceName' => $subscriber->audience->name,
            'confirmed' => $subscriber->subscribed_at !== null,
        ]);
    }
}
