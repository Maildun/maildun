<?php

use App\Mcp\Servers\MaildunServer;
use App\Mcp\Tools\Workspaces\ListWorkspacesTool;
use App\Models\Team;
use Illuminate\Testing\Fluent\AssertableJson;

test('lists active workspaces and excludes deleted workspaces', function () {
    Team::factory()->create(['name' => 'Alpha Workspace', 'slug' => 'alpha']);
    Team::factory()->trashed()->create(['name' => 'Archived Workspace', 'slug' => 'archived']);

    MaildunServer::tool(ListWorkspacesTool::class, [
        'search' => 'Alpha',
        'limit' => 10,
    ])->assertOk()->assertStructuredContent(fn (AssertableJson $json) => $json
        ->where('count', 1)
        ->where('workspaces.0.name', 'Alpha Workspace')
        ->where('workspaces.0.slug', 'alpha')
        ->etc());
});
