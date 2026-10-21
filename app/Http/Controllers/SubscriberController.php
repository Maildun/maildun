<?php

namespace App\Http\Controllers;

use App\Actions\Audiences\ResubscribeSubscriber;
use App\Actions\Automations\DispatchTaggedTriggers;
use App\Enums\AutomationTrigger;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Http\Requests\BulkSubscriberLanguageRequest;
use App\Http\Requests\BulkSubscriberRequest;
use App\Http\Requests\BulkSubscriberTagsRequest;
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
use Illuminate\Support\Str;
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

    public function store(SaveSubscriberRequest $request, Team $currentTeam, Audience $audience, ManageContact $manageContact, DispatchTaggedTriggers $dispatchTaggedTriggers): RedirectResponse
    {
        Gate::authorize('create', [Subscriber::class, $audience]);

        $contact = $manageContact->findOrCreate($currentTeam, $this->contactProfile($request));

        $subscriber = $audience->subscribers()->create([
            'contact_id' => $contact->id,
            'email' => $contact->email,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'language' => $request->validated('language') ?? $audience->default_language,
            'status' => SubscriberStatus::Subscribed,
            'source' => SubscriberSource::Manual,
            'consent_text' => 'Marketing consent confirmed by a team member.',
            'consented_at' => now(),
            'consent_ip' => $request->ip(),
            'subscribed_at' => now(),
        ]);

        $addedTags = $manageContact->syncTags($currentTeam, $contact, $request->validated('tags', []));

        if ($subscriber->status === SubscriberStatus::Unsubscribed) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => __('This person unsubscribed from this audience before, so they were added back as unsubscribed. Resubscribe them only if they consented again.')]);

            return back();
        }

        event(new SubscriberLifecycleOccurred(AutomationTrigger::Subscribed, $subscriber));
        $dispatchTaggedTriggers->handle($contact, $addedTags);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscriber added.')]);

        return back();
    }

    public function update(SaveSubscriberRequest $request, Team $currentTeam, Audience $audience, Subscriber $subscriber, ManageContact $manageContact, DispatchTaggedTriggers $dispatchTaggedTriggers): RedirectResponse
    {
        Gate::authorize('update', $subscriber);
        $profile = Arr::only($request->validated(), ['email', 'first_name', 'last_name']);
        $this->authorizeContactProfileChange($currentTeam, $subscriber->contact, $profile);
        $contact = $subscriber->contact ?? $manageContact->findOrCreate($currentTeam, $this->contactProfile($request));
        $contact = $manageContact->update($contact, $profile);
        $subscriber->update([
            'contact_id' => $contact->id,
            ...($request->has('language') ? ['language' => $request->validated('language')] : []),
        ]);
        $addedTags = $manageContact->syncTags($currentTeam, $contact, $request->validated('tags', []));
        $dispatchTaggedTriggers->handle($contact, $addedTags);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscriber updated.')]);

        return back();
    }

    public function unsubscribe(Team $currentTeam, Audience $audience, Subscriber $subscriber): RedirectResponse
    {
        Gate::authorize('update', $subscriber);
        $subscriber->update([
            'status' => SubscriberStatus::Unsubscribed,
            'unsubscribed_at' => now(),
            'resubscribe_requested_at' => null,
        ]);

        event(new SubscriberLifecycleOccurred(AutomationTrigger::Unsubscribed, $subscriber));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscriber unsubscribed.')]);

        return back();
    }

    public function resubscribe(ResubscribeSubscriberRequest $request, Team $currentTeam, Audience $audience, Subscriber $subscriber, ResubscribeSubscriber $resubscribeSubscriber): RedirectResponse
    {
        Gate::authorize('update', $subscriber);
        $resubscribeSubscriber->handle($subscriber, $request->ip());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscriber resubscribed.')]);

        return back();
    }

    /**
     * The email and name belong to the shared contact, so changing them from an
     * audience edits every membership and needs the contact permission too.
     *
     * @param  array<string, mixed>  $profile
     */
    private function authorizeContactProfileChange(Team $team, ?Contact $contact, array $profile): void
    {
        if (! $contact instanceof Contact) {
            Gate::authorize('create', [Contact::class, $team]);

            return;
        }

        $changed = collect($profile)->contains(
            fn (mixed $value, string $key): bool => (string) $value !== (string) $contact->getAttribute($key),
        );

        if ($changed) {
            Gate::authorize('update', $contact);
        }
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

        $audience->subscribers()
            ->whereIn('uuid', $request->validated('ids'))
            ->whereNotNull('resubscribe_requested_at')
            ->update(['resubscribe_requested_at' => null]);

        foreach ($subscribers as $subscriber) {
            $subscriber->status = SubscriberStatus::Unsubscribed;
            $subscriber->setAttribute('unsubscribed_at', now());
            event(new SubscriberLifecycleOccurred(AutomationTrigger::Unsubscribed, $subscriber));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Selected subscribers unsubscribed.')]);

        return back();
    }

    public function bulkLanguage(BulkSubscriberLanguageRequest $request, Team $currentTeam, Audience $audience): RedirectResponse
    {
        Gate::authorize('create', [Subscriber::class, $audience]);

        $audience->subscribers()
            ->whereIn('uuid', $request->validated('ids'))
            ->update(['language' => $request->validated('language')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Language updated for selected subscribers.')]);

        return back();
    }

    /**
     * Tags belong to the contact, so adding or removing one here applies in
     * every audience the contact is subscribed to.
     */
    public function bulkTags(BulkSubscriberTagsRequest $request, Team $currentTeam, Audience $audience, ManageContact $manageContact, DispatchTaggedTriggers $dispatchTaggedTriggers): RedirectResponse
    {
        Gate::authorize('create', [Subscriber::class, $audience]);

        /** @var list<string> $names */
        $names = $request->validated('tags');
        $contacts = $audience->subscribers()
            ->whereIn('uuid', $request->validated('ids'))
            ->whereNotNull('contact_id')
            ->with('contact')
            ->get()
            ->pluck('contact')
            ->filter();

        if ($request->validated('action') === 'add') {
            foreach ($contacts as $contact) {
                $dispatchTaggedTriggers->handle($contact, $manageContact->addTags($currentTeam, $contact, $names));
            }

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Tags added to selected subscribers.')]);

            return back();
        }

        $lowerNames = array_map(fn (string $name): string => Str::lower(trim($name)), $names);
        $tagIds = $currentTeam->tags()
            ->get(['id', 'name'])
            ->filter(fn ($tag): bool => in_array(Str::lower($tag->name), $lowerNames, true))
            ->modelKeys();

        foreach ($contacts as $contact) {
            $contact->tags()->detach($tagIds);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tags removed from selected subscribers.')]);

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
