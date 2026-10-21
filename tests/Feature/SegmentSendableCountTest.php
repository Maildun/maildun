<?php

use App\Models\Audience;
use App\Models\EmailAddressHealth;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('segment pages show how many members a campaign would reach', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $members = collect([
        Subscriber::factory()->for($audience)->create(),
        Subscriber::factory()->for($audience)->pendingConfirmation()->create(),
        Subscriber::factory()->for($audience)->unsubscribed()->create(),
        Subscriber::factory()->for($audience)->create(['email' => 'bounced@example.com']),
    ]);
    EmailAddressHealth::factory()->for($team)->suppressed()->create(['email' => 'bounced@example.com']);
    $segment = Segment::factory()->for($audience)->create();
    $segment->subscribers()->sync($members->pluck('id')->all());

    $this->actingAs($user)
        ->get(route('audiences.segments.show', [$team, $audience, $segment]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscribers.total', 4)
            ->where('segment.sendable_count', 1));

    $this->actingAs($user)
        ->get(route('audiences.show', [$team, $audience]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('segments.0.subscribers_count', 4)
            ->where('segments.0.sendable_count', 1));
});
