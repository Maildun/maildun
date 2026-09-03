<?php

namespace App\Http\Controllers;

use App\Enums\AutomationTrigger;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Http\Requests\BulkSubscriberRequest;
use App\Http\Requests\ResubscribeSubscriberRequest;
use App\Http\Requests\SaveSubscriberRequest;
use App\Models\Audience;
use App\Models\Contact;
use App\Models\Subscriber;
use App\Models\Team;
use App\Services\ManageContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SubscriberController extends Controller
{
    public function show(Team $currentTeam, Audience $audience, Subscriber $subscriber): RedirectResponse
    {
        Gate::authorize('view', $subscriber);

        $contact = $currentTeam->contacts()
            ->whereKey($subscriber->contact_id)
            ->firstOrFail();

        return to_route('contacts.show', [
            'current_team' => $currentTeam,
            'contact' => $contact,
            'audience' => $audience->uuid,
        ]);
    }

    public function store(SaveSubscriberRequest $request, Team $currentTeam, Audience $audience, ManageContact $manageContact): RedirectResponse
    {
        Gate::authorize('create', [Subscriber::class, $audience]);

        $contact = $manageContact->findOrCreate($currentTeam, $this->contactProfile($request));

        $subscriber = $audience->subscribers()->create([
            'contact_id' => $contact->id,
            'email' => $contact->email,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'status' => SubscriberStatus::Subscribed,
            'source' => SubscriberSource::Manual,
            'consent_text' => 'Marketing consent confirmed by a team member.',
            'consented_at' => now(),
            'consent_ip' => $request->ip(),
            'subscribed_at' => now(),
        ]);

        $addedTags = $manageContact->syncTags($currentTeam, $contact, $request->validated('tags', []));

        event(new SubscriberLifecycleOccurred(AutomationTrigger::Subscribed, $subscriber));
        $this->dispatchTagged($contact, $addedTags);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscriber added.')]);

        return back();
    }

    public function update(SaveSubscriberRequest $request, Team $currentTeam, Audience $audience, Subscriber $subscriber, ManageContact $manageContact): RedirectResponse
    {
        Gate::authorize('update', $subscriber);
        $contact = $subscriber->contact ?? $manageContact->findOrCreate($currentTeam, $this->contactProfile($request));
        $contact = $manageContact->update($contact, Arr::only($request->validated(), ['email', 'first_name', 'last_name']));
        $subscriber->update(['contact_id' => $contact->id]);
        $addedTags = $manageContact->syncTags($currentTeam, $contact, $request->validated('tags', []));
        $this->dispatchTagged($contact, $addedTags);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscriber updated.')]);

        return back();
    }

    public function unsubscribe(Team $currentTeam, Audience $audience, Subscriber $subscriber): RedirectResponse
    {
        Gate::authorize('update', $subscriber);
        $subscriber->update([
            'status' => SubscriberStatus::Unsubscribed,
            'unsubscribed_at' => now(),
        ]);

        event(new SubscriberLifecycleOccurred(AutomationTrigger::Unsubscribed, $subscriber));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscriber unsubscribed.')]);

        return back();
    }

    public function resubscribe(ResubscribeSubscriberRequest $request, Team $currentTeam, Audience $audience, Subscriber $subscriber): RedirectResponse
    {
        Gate::authorize('update', $subscriber);
        $subscriber->update([
            'status' => SubscriberStatus::Subscribed,
            'consent_text' => 'Marketing consent confirmed by a team member.',
            'consented_at' => now(),
            'consent_ip' => $request->ip(),
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);

        event(new SubscriberLifecycleOccurred(AutomationTrigger::Resubscribed, $subscriber->fresh() ?? $subscriber));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscriber resubscribed.')]);

        return back();
    }

    /** @return array{email: string, first_name: string|null, last_name: string|null} */
    private function contactProfile(SaveSubscriberRequest $request): array
    {
        return [
            'email' => $request->string('email')->toString(),
            'first_name' => $request->has('first_name') ? $request->string('first_name')->toString() : null,
            'last_name' => $request->has('last_name') ? $request->string('last_name')->toString() : null,
        ];
    }

    /** @param list<string> $tagUuids */
    private function dispatchTagged(Contact $contact, array $tagUuids): void
    {
        foreach ($contact->subscribers()->get() as $subscriber) {
            foreach ($tagUuids as $tagUuid) {
                event(new SubscriberLifecycleOccurred(
                    AutomationTrigger::Tagged,
                    $subscriber,
                    ['tag_uuid' => $tagUuid],
                ));
            }
        }
    }

    public function destroy(Team $currentTeam, Audience $audience, Subscriber $subscriber): RedirectResponse
    {
        Gate::authorize('delete', $subscriber);

        $fromProfile = str_contains(url()->previous(), '/subscribers/'.$subscriber->uuid);
        $subscriber->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscriber permanently deleted.')]);

        if ($fromProfile) {
            return to_route('audiences.show', [$currentTeam, $audience]);
        }

        return back();
    }

    public function bulkUnsubscribe(BulkSubscriberRequest $request, Team $currentTeam, Audience $audience): RedirectResponse
    {
        Gate::authorize('create', [Subscriber::class, $audience]);

        $subscribers = $audience->subscribers()
            ->whereIn('uuid', $request->validated('ids'))
            ->where('status', SubscriberStatus::Subscribed)
            ->get();

        $audience->subscribers()
            ->whereIn('id', $subscribers->modelKeys())
            ->update([
                'status' => SubscriberStatus::Unsubscribed,
                'unsubscribed_at' => now(),
            ]);

        foreach ($subscribers as $subscriber) {
            $subscriber->status = SubscriberStatus::Unsubscribed;
            $subscriber->setAttribute('unsubscribed_at', now());
            event(new SubscriberLifecycleOccurred(AutomationTrigger::Unsubscribed, $subscriber));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Selected subscribers unsubscribed.')]);

        return back();
    }

    public function bulkDestroy(BulkSubscriberRequest $request, Team $currentTeam, Audience $audience): RedirectResponse
    {
        Gate::authorize('create', [Subscriber::class, $audience]);

        $audience->subscribers()
            ->whereIn('uuid', $request->validated('ids'))
            ->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Selected subscribers permanently deleted.')]);

        return back();
    }
}
