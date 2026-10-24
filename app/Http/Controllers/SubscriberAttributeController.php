<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSubscriberAttributesRequest;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Subscriber;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SubscriberAttributeController extends Controller
{
    /**
     * Save a member's custom field values. A filled field is stored and a
     * blank one is cleared; values under keys the audience no longer defines
     * are left alone.
     */
    public function update(UpdateSubscriberAttributesRequest $request, Team $currentTeam, Audience $audience, Subscriber $subscriber): RedirectResponse
    {
        $values = $subscriber->attribute_values ?? [];

        $request->audienceAttributes()->each(function (AudienceAttribute $attribute) use ($request, &$values): void {
            $value = $request->validated('attributes.'.$attribute->key);

            if ($value !== '' && (is_string($value) || is_int($value) || is_float($value))) {
                $values[$attribute->key] = $value;
            } else {
                unset($values[$attribute->key]);
            }
        });

        $subscriber->update(['attribute_values' => $values]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Custom attributes updated.')]);

        return back();
    }
}
