<?php

namespace App\Actions\Automations;

use App\Mail\AutomationEmail;
use App\Models\AutomationEmailDelivery;
use App\Models\Subscriber;
use Illuminate\Support\Facades\URL;

/**
 * The merge data an automation email is rendered with.
 *
 * Shared by the send and by the hosted web view, which has no stored body and
 * re-renders the template from the same inputs.
 */
class BuildAutomationMergeData
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function handle(Subscriber $subscriber, array $context, AutomationEmailDelivery $delivery): array
    {
        $payload = is_array($context['data'] ?? null) ? $context['data'] : [];

        return [
            'email' => $subscriber->email,
            'first_name' => $subscriber->first_name ?? '',
            'last_name' => $subscriber->last_name ?? '',
            ...($subscriber->attribute_values ?? []),
            ...$payload,
            // Last so neither a custom attribute nor trigger context can shadow
            // the opt-out or browser copy links with a value of its own.
            'unsubscribe_url' => AutomationEmail::unsubscribeUrl($subscriber),
            'web_view_url' => self::webViewUrl($delivery),
        ];
    }

    /**
     * The signed page that re-renders this delivery's body in a browser.
     */
    public static function webViewUrl(AutomationEmailDelivery $delivery): string
    {
        return URL::signedRoute('public.web_view.automation.show', ['delivery' => $delivery]);
    }
}
