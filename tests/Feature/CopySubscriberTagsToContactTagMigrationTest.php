<?php

use App\Models\Audience;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

function runCopySubscriberTagsMigration(): void
{
    (require base_path('database/migrations/2026_10_21_031116_copy_subscriber_tags_to_contact_tag.php'))->up();
}

/** @param list<array{0: Subscriber, 1: Tag}> $pairs */
function tagSubscribersTheV010Way(array $pairs): void
{
    DB::table('subscriber_tag')->insert(array_map(fn (array $pair): array => [
        'subscriber_id' => $pair[0]->id,
        'tag_id' => $pair[1]->id,
        'created_at' => '2026-08-01 10:00:00',
        'updated_at' => '2026-08-01 10:00:00',
    ], $pairs));
}

test('copies each contact\'s distinct subscriber tags onto the contact', function () {
    $team = Team::factory()->create();
    $vip = Tag::factory()->for($team)->create(['name' => 'VIP']);
    $beta = Tag::factory()->for($team)->create(['name' => 'Beta']);
    $newsletterMember = Subscriber::factory()->for(Audience::factory()->for($team))->create(['email' => 'ada@example.com']);
    $productMember = Subscriber::factory()->for(Audience::factory()->for($team))->create(['email' => 'ada@example.com']);
    $untagged = Subscriber::factory()->for(Audience::factory()->for($team))->create(['email' => 'grace@example.com']);
    tagSubscribersTheV010Way([[$newsletterMember, $vip], [$productMember, $vip], [$productMember, $beta]]);

    runCopySubscriberTagsMigration();
    runCopySubscriberTagsMigration();

    expect($newsletterMember->contact->tags()->orderBy('name')->pluck('name')->all())->toBe(['Beta', 'VIP'])
        ->and($untagged->contact->tags()->count())->toBe(0);
    $this->assertDatabaseCount('contact_tag', 2);
    $this->assertDatabaseHas('contact_tag', [
        'contact_id' => $newsletterMember->contact_id,
        'tag_id' => $vip->id,
        'created_at' => '2026-08-01 10:00:00',
    ]);
});

test('keeps tags the contact already has', function () {
    $team = Team::factory()->create();
    $vip = Tag::factory()->for($team)->create(['name' => 'VIP']);
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($team))->create();
    $subscriber->contact->tags()->attach($vip);
    tagSubscribersTheV010Way([[$subscriber, $vip]]);

    runCopySubscriberTagsMigration();

    expect($subscriber->contact->tags()->pluck('name')->all())->toBe(['VIP']);
});

test('does not copy a tag from another workspace', function () {
    $foreignTag = Tag::factory()->for(Team::factory())->create();
    $subscriber = Subscriber::factory()->for(Audience::factory()->for(Team::factory()))->create();
    tagSubscribersTheV010Way([[$subscriber, $foreignTag]]);

    runCopySubscriberTagsMigration();

    $this->assertDatabaseEmpty('contact_tag');
});
