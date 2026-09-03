<?php

namespace App\Http\Controllers;

use App\Actions\Audiences\SyncSegmentSubscribers;
use App\Http\Requests\SaveSegmentRequest;
use App\Models\Audience;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SegmentController extends Controller
{
    public function store(SaveSegmentRequest $request, Team $currentTeam, Audience $audience, SyncSegmentSubscribers $syncSegmentSubscribers): RedirectResponse
    {
        Gate::authorize('create', [Segment::class, $audience]);
        $segment = $audience->segments()->create($request->validated());
        $syncSegmentSubscribers->handle($segment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Segment created.')]);

        return to_route('audiences.segments.show', [
            'current_team' => $audience->team,
            'audience' => $audience,
            'segment' => $segment,
        ]);
    }

    public function show(Team $currentTeam, Audience $audience, Segment $segment): Response
    {
        Gate::authorize('view', $segment);

        $subscribers = $segment->subscribers()
            ->with(['subscribeForm:id,uuid,name,deleted_at', 'tags:id,uuid,name,color'])
            ->orderByDesc('subscribers.created_at')
            ->paginate(20)
            ->through(fn (Subscriber $subscriber) => [
                'uuid' => $subscriber->uuid,
                'avatar' => $subscriber->avatar,
                'email' => $subscriber->email,
                'first_name' => $subscriber->first_name,
                'last_name' => $subscriber->last_name,
                'status' => $subscriber->status->value,
                'source' => $subscriber->source->value,
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
            ],
            'subscribers' => $subscribers,
            'forms' => $audience->subscribeForms()->withTrashed()->get(['uuid', 'name'])->map(fn ($form) => [
                'uuid' => $form->uuid,
                'name' => $form->name,
            ]),
            'canManage' => Gate::allows('update', $segment),
        ]);
    }

    public function update(SaveSegmentRequest $request, Team $currentTeam, Audience $audience, Segment $segment, SyncSegmentSubscribers $syncSegmentSubscribers): RedirectResponse
    {
        Gate::authorize('update', $segment);
        $segment->update($request->validated());
        $syncSegmentSubscribers->handle($segment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Segment updated.')]);

        return back();
    }

    public function destroy(Team $currentTeam, Audience $audience, Segment $segment): RedirectResponse
    {
        Gate::authorize('delete', $segment);
        $segment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Segment deleted.')]);

        return to_route('audiences.show', ['current_team' => $audience->team, 'audience' => $audience]);
    }
}
