<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Reports whether Maildun\'s MCP integration is ready and identifies the current application environment.')]
#[IsOpenWorld(false)]
#[IsReadOnly]
#[Name('application-status')]
class ApplicationStatusTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        return Response::structured([
            'application' => (string) config('app.name'),
            'environment' => app()->environment(),
            'server' => 'maildun',
            'status' => 'ready',
        ]);
    }

    /**
     * Get the tool's output schema.
     *
     * @return array<string, Type>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'application' => $schema->string()
                ->description('The configured application name.')
                ->required(),
            'environment' => $schema->string()
                ->description('The current Laravel application environment.')
                ->required(),
            'server' => $schema->string()
                ->description('The local MCP server handle.')
                ->required(),
            'status' => $schema->string()
                ->description('The MCP integration readiness state.')
                ->required(),
        ];
    }
}
