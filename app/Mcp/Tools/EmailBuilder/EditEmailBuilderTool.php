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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('edit-email-builder')]
#[Description('Edits a block email using its native agent tools. Use get-email-builder for tool inputs and revision. Supports atomic apply_ops batches and dry_run previews; saves matching design and HTML together.')]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[IsDestructive]
class EditEmailBuilderTool extends Tool
{
    public function __construct(private EmailBuilderContext $context) {}

    public function handle(Request $request): ResponseFactory
    {
        return Response::structured($this->context->edit($request));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->context->schema($schema),
            'revision' => $schema->string()->description('Revision from get-email-builder or the latest successful edit.')->min(64)->max(64)->required(),
            'tool' => $schema->string()->enum(EmailBuilderContext::EDIT_TOOLS)->description('Native agent tool to execute.')->required(),
            'input' => $schema->object()->description('Arguments matching the native tool inputSchema returned by get-email-builder.')->required(),
            'dry_run' => $schema->boolean()->description('Preview the operations without saving; the revision remains unchanged.')->default(false),
        ];
    }
}
