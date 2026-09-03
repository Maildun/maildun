<?php

use App\Models\Team;
use App\Models\TeamSender;
use Database\Seeders\TeamSenderSeeder;

test('seeds registered senders and the default for the demo workspace', function () {
    $team = Team::factory()->create(['slug' => 'maildun-studio']);

    $this->seed(TeamSenderSeeder::class);
    $this->seed(TeamSenderSeeder::class);

    $team->refresh();

    $this->assertDatabaseHas('team_senders', [
        'team_id' => $team->id,
        'name' => 'Maildun Studio',
        'email' => 'hello@maildun.test',
        'reply_to' => 'support@maildun.test',
    ]);
    $this->assertDatabaseHas('team_senders', [
        'team_id' => $team->id,
        'name' => 'Maildun Support',
        'email' => 'support@maildun.test',
        'reply_to' => 'support@maildun.test',
    ]);

    expect(TeamSender::query()->whereBelongsTo($team)->count())->toBe(2)
        ->and($team->activeSender)->not->toBeNull()
        ->and($team->activeSender?->email)->toBe('hello@maildun.test')
        ->and($team->activeSender?->isVerified())->toBeFalse()
        ->and($team->email_from_name)->toBe('Maildun Studio')
        ->and($team->email_reply_to)->toBe('support@maildun.test');
});

test('does not seed sender identities without the demo workspace', function () {
    Team::factory()->create(['slug' => 'another-workspace']);

    $this->seed(TeamSenderSeeder::class);

    expect(TeamSender::query()->count())->toBe(0);
});
