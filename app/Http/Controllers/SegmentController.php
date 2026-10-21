<?php

namespace App\Http\Controllers;

use App\Actions\Audiences\BuildSegmentRuleOptions;
use App\Actions\Audiences\DeleteSegment;
use App\Actions\Audiences\EnsureSegmentRulesCanChange;
use App\Actions\Audiences\SyncSegmentSubscribers;
use App\Enums\SubscriberLanguage;
use App\Http\Requests\SaveSegmentRequest;
use App\Models\Audience;
use App\Models\Contact;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SegmentController extends Controller
{
    public function store(SaveSegmentRequest $request, Team $currentTeam, Audience $audience, SyncSegmentSubscribers $syncSegmentSubscribers): RedirectResponse
    {
        Gate::authorize('create', [Segment::class, $audience]);
        $segment = DB::transaction(function () use ($request, $audience, $syncSegmentSubscribers): Segment {
            $segment = $audience->segments()->create($request->validated());
            $syncSegmentSubscribers->handle($segment);

            return $segment;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Segment created.')]);

        return to_route('audiences.segments.show', [
            'current_team' => $audience->team,
            'audience' => $audience,
            'segment' => $segment,
        ]);
    }

    public function show(Team $currentTeam, Audience $audience, Segment $segment, BuildSegmentRuleOptions $buildSegmentRuleOptions): Response
    {
        Gate::authorize('view', $segment);

        $subscribers = $segment->subscribers()
            ->with(['subscribeForm:id,uuid,name,deleted_at', 'tags:id,uuid,name,color'])
            ->orderByDesc('subscribers.created_at')
            ->orderByDesc('subscribers.id')
            ->paginate(20)
            ->through(fn (Subscriber $subscriber) => [
                'uuid' => $subscriber->uuid,
                'avatar' => $subscriber->avatar,
                'email' => $subscriber->email,
                'first_name' => $subscriber->first_name,
                'last_name' => $subscriber->last_name,
                'status' => $subscriber->status->value,
                'source' => $subscriber->source->value,
                'language' => $subscriber->language?->value,
                'language_name' => $subscriber->language?->label(),
                'source_form' => $subscriber->subscribeForm ? [
                    'uuid' => $subscriber->subscribeForm->uuid,
                    'name' => $subscriber->subscribeForm->name,
                ] : null,
                'tags' => array_values($subscriber->tags->map(fn (Tag $tag): array => [
                    'uuid' => $tag->uuid,
                    'name' => $tag->name,
                    'color' => $tag->color,
                ])->all()),
                'subscribed_at' => $subscriber->subscribed_at?->toISOString(),
                'pending_confirmation' => $subscriber->isPendingConfirmation() || $subscriber->isPendingResubscription(),
            ]);

        return Inertia::render('segments/show', [
            'audience' => [
                'uuid' => $audience->uuid,
                'name' => $audience->name,
            ],
            'segment' => [
                'uuid' => $segment->uuid,
                'name' => $segment->name,
                'description' => $segment->description,
                'match_type' => $segment->match_type->value,
                'rules' => $segment->rules,
                'rules_synced_at' => $segment->rules_synced_at?->toISOString(),
                // What a campaign to this segment would reach right now.
                'sendable_count' => $segment->subscribers()->sendableFor($currentTeam)->count(),
            ],
            'subscribers' => $subscribers,
            'segmentRuleOptions' => $buildSegmentRuleOptions->handle($audience),
            'languages' => SubscriberLanguage::options(),
            'tags' => $currentTeam->tags()->orderBy('name')->get(['uuid', 'name', 'color']),
            'canManage' => Gate::allows('update', $segment),
            'canManageSubscribers' => Gate::allows('update', $audience),
            'canManageContacts' => Gate::allows('create', [Contact::class, $currentTeam]),
        ]);
    }

    public function update(SaveSegmentRequest $request, Team $currentTeam, Audience $audience, Segment $segment, SyncSegmentSubscribers $syncSegmentSubscribers, EnsureSegmentRulesCanChange $ensureSegmentRulesCanChange): RedirectResponse
    {
        Gate::authorize('update', $segment);
        $ensureSegmentRulesCanChange->handle($segment, $request->validated());
        DB::transaction(function () use ($request, $segment, $syncSegmentSubscribers): void {
            $segment->update($request->validated());
            $syncSegmentSubscribers->handle($segment);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Segment updated.')]);

        return back();
    }

    public function destroy(Team $currentTeam, Audience $audience, Segment $segment, DeleteSegment $deleteSegment): RedirectResponse
    {
        Gate::authorize('delete', $segment);
        $deleteSegment->handle($segment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Segment deleted.')]);

        return to_route('audiences.show', ['current_team' => $audience->team, 'audience' => $audience]);
    }
}
