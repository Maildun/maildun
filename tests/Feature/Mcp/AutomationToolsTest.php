<?php

use App\Enums\AutomationRunStatus;
use App\Mcp\Servers\MaildunServer;
use App\Mcp\Tools\Automations\GetAutomationTool;
use App\Mcp\Tools\Automations\ListAutomationsTool;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Team;
use Illuminate\Testing\Fluent\AssertableJson;

test('reads automations without exposing trigger tokens', function () {
    $team = Team::factory()->create(['slug' => 'automations']);
    $automation = Automation::factory()->for($team)->active()->create([
        'name' => 'Welcome Journey',
        'trigger_token' => 'super-secret-trigger-token',
    ]);
    AutomationRun::factory()->for($automation)->create(['status' => AutomationRunStatus::Running]);
    AutomationRun::factory()->for($automation)->create(['status' => AutomationRunStatus::Completed]);

    MaildunServer::tool(ListAutomationsTool::class, [
        'workspace' => $team->slug,
        'status' => 'active',
    ])->assertOk()
        ->assertDontSee('super-secret-trigger-token')
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('count', 1)
            ->where('automations.0.uuid', $automation->uuid)
            ->where('automations.0.enrolled_count', 2)
            ->where('automations.0.running_count', 1)
            ->etc());

    MaildunServer::tool(GetAutomationTool::class, [
        'workspace' => $team->slug,
        'uuid' => $automation->uuid,
    ])->assertOk()
        ->assertDontSee('super-secret-trigger-token')
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('automation.uuid', $automation->uuid)
            ->where('automation.graph.nodes.0.type', 'trigger')
            ->missing('automation.trigger_token')
            ->etc());
});
