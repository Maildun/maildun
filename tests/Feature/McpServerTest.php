<?php

use App\Mcp\Servers\MaildunServer;
use App\Mcp\Tools\ApplicationStatusTool;
use Laravel\Mcp\Facades\Mcp;

test('registers the local Maildun MCP server', function () {
    expect(Mcp::getLocalServer('maildun'))->not->toBeNull();
});

test('reports that the Maildun MCP integration is ready', function () {
    config(['app.name' => 'Maildun Test']);

    $response = MaildunServer::tool(ApplicationStatusTool::class);

    $response
        ->assertOk()
        ->assertName('application-status')
        ->assertDescription('Reports whether Maildun\'s MCP integration is ready and identifies the current application environment.')
        ->assertStructuredContent([
            'application' => 'Maildun Test',
            'environment' => 'testing',
            'server' => 'maildun',
            'status' => 'ready',
        ]);
});
