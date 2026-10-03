<?php

namespace App\Mcp\Tools\EmailBuilder;

use App\Mcp\Support\EmailBuilderContext;
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

#[Name('get-email-builder')]
#[Description('Reads a block email outline, its revision, and native agent tool schemas and instructions. Start here before editing; optionally request one block by block_id.')]
#[IsOpenWorld(false)]
#[IsReadOnly]
class GetEmailBuilderTool extends Tool
{
    public function __construct(private EmailBuilderContext $context) {}

    public function handle(Request $request): ResponseFactory
    {
        return Response::structured($this->context->inspect($request, 'get_document'));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->context->schema($schema),
            'block_id' => $schema->string()->description('Optional block ID from the outline; returns that block and its children.')->max(255),
        ];
    }
}
