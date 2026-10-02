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

#[Name('check-email-builder')]
#[Description('Validates and lints a block email and returns its rendered HTML, plain text, and warnings without saving.')]
#[IsOpenWorld(false)]
#[IsReadOnly]
class CheckEmailBuilderTool extends Tool
{
    public function __construct(private EmailBuilderContext $context) {}

    public function handle(Request $request): ResponseFactory
    {
        return Response::structured($this->context->inspect($request, 'check_email'));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->context->schema($schema),

        ];
    }
}
