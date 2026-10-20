<?php

namespace App\Mcp\Tools\SavedSections;

use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\SavedSection;
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

#[Description("Lists the workspace's saved sections (the author's own header, footer, logo), newest first, with their blocks. Prefer them over building those parts again: insert one with edit-email-builder, tool insert_blocks and the section's blocks as input.blocks.")]
#[IsOpenWorld(false)]
#[IsReadOnly]
#[Name('list-saved-sections')]
class ListSavedSectionsTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $this->context->authorize($request, 'viewAny', [SavedSection::class, $team]);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $sections = $team->savedSections()
            ->when($search !== '', fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%']))
            ->newestFirst()
            ->get();

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'count' => $sections->count(),
            'sections' => $sections->map(fn (SavedSection $section): array => $section->toEditorArray())->values()->all(),
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace' => $schema->string()->description('Workspace slug.')->max(255)->required(),
            'search' => $schema->string()->description('Optional case-insensitive name search, e.g. "footer".')->max(255),
        ];
    }
}
