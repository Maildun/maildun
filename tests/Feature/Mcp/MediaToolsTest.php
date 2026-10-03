<?php

use App\Mcp\Servers\MaildunServer;
use App\Mcp\Tools\Media\ListMediaTool;
use App\Models\Media;
use App\Models\Team;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

test('lists ready workspace images with absolute URLs for email designs', function () {
    $team = Team::factory()->create();
    Media::factory()->for($team)->create(['name' => 'hero.png', 'alt' => 'Team photo', 'path' => 'media/team/hero.png']);
    Media::factory()->for($team)->processing()->create(['name' => 'pending.png']);
    Media::factory()->create(['name' => 'other-workspace.png']);

    MaildunServer::tool(ListMediaTool::class, ['workspace' => $team->slug])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('count', 1)
            ->where('media.0.name', 'hero.png')
            ->where('media.0.alt', 'Team photo')
            ->where('media.0.url', url('/storage/media/team/hero.png'))
            ->etc());
});

test('searches images by name or alt text', function () {
    $team = Team::factory()->create();
    Media::factory()->for($team)->create(['name' => 'logo.png', 'alt' => null]);
    Media::factory()->for($team)->create(['name' => 'IMG_001.png', 'alt' => 'Product launch banner']);

    MaildunServer::tool(ListMediaTool::class, ['workspace' => $team->slug, 'search' => 'launch'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('count', 1)
            ->where('media.0.name', 'IMG_001.png')
            ->etc());
});

test('refuses media from a workspace the user cannot access', function () {
    $team = Team::factory()->create();
    Media::factory()->for($team)->create();

    MaildunServer::actingAs(User::factory()->create())
        ->tool(ListMediaTool::class, ['workspace' => $team->slug])
        ->assertHasErrors(['workspace was not found']);
});
