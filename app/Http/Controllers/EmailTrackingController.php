<?php

namespace App\Http\Controllers;

use App\Actions\Emails\CaptureEmailTrackingEvent;
use App\Enums\EmailTrackingEventType;
use App\Models\EmailDelivery;
use App\Models\EmailLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmailTrackingController extends Controller
{
    public function open(
        EmailDelivery $delivery,
        Request $request,
        CaptureEmailTrackingEvent $captureEvent,
    ): Response {
        $captureEvent->handle(
            delivery: $delivery,
            type: EmailTrackingEventType::Open,
            userAgent: $request->userAgent(),
            ipAddress: $request->ip(),
        );

        return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=='), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function click(
        EmailDelivery $delivery,
        EmailLink $link,
        Request $request,
        CaptureEmailTrackingEvent $captureEvent,
    ): RedirectResponse {
        abort_unless($link->email_id === $delivery->email_id, 404);
        abort_unless(in_array(parse_url($link->url, PHP_URL_SCHEME), ['http', 'https'], true), 404);
        abort_unless($delivery->email()->value('track_clicks'), 404);

        $captureEvent->handle(
            delivery: $delivery,
            type: EmailTrackingEventType::Click,
            link: $link,
            userAgent: $request->userAgent(),
            ipAddress: $request->ip(),
        );

        return redirect()->away($link->url);
    }
}
