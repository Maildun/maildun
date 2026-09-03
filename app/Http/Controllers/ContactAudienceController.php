<?php

namespace App\Http\Controllers;

use App\Actions\Audiences\AddContactToAudiences;
use App\Http\Requests\StoreContactAudienceRequest;
use App\Models\Contact;
use App\Models\Subscriber;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ContactAudienceController extends Controller
{
    public function store(
        StoreContactAudienceRequest $request,
        Team $currentTeam,
        Contact $contact,
        AddContactToAudiences $addContactToAudiences,
    ): RedirectResponse {
        Gate::authorize('update', $contact);

        $audience = $currentTeam->audiences()
            ->where('uuid', $request->validated('audience_uuid'))
            ->firstOrFail();

        $addContactToAudiences->handle($contact, $audience->newCollection([$audience]), $request->ip());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact added to audience.')]);

        return back();
    }

    public function destroy(Team $currentTeam, Contact $contact, Subscriber $subscriber): RedirectResponse
    {
        Gate::authorize('update', $contact);
        abort_unless($subscriber->contact_id === $contact->id, 404);

        $subscriber->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact removed from audience.')]);

        return back();
    }
}
