<?php

use App\Actions\Media\SearchReadyTeamMedia;
use App\Models\Media;
use App\Models\Team;

test('only ready images of the team are returned, newest first', function () {
    $team = Team::factory()->create();
    Media::factory()->for($team)->create(['name' => 'older.png', 'created_at' => now()->subDay()]);
    Media::factory()->for($team)->create(['name' => 'newer.png']);
    Media::factory()->for($team)->processing()->create(['name' => 'pending.png']);
    Media::factory()->for($team)->failed()->create(['name' => 'broken.png']);
    Media::factory()->create(['name' => 'other-workspace.png']);

    $names = app(SearchReadyTeamMedia::class)->handle($team)->pluck('name')->all();

    expect($names)->toBe(['newer.png', 'older.png']);
});

test('search matches the file name or alt text', function () {
    $team = Team::factory()->create();
    Media::factory()->for($team)->create(['name' => 'IMG_001.png', 'alt' => 'Product launch banner']);
    Media::factory()->for($team)->create(['name' => 'logo.png', 'alt' => null]);

    $search = app(SearchReadyTeamMedia::class);

    expect($search->handle($team, 'launch')->pluck('name')->all())->toBe(['IMG_001.png'])
        ->and($search->handle($team, 'logo')->pluck('name')->all())->toBe(['logo.png']);
});
