<?php

namespace App\Ai\Tools;

use App\Ai\DesignSession;
use App\Models\SavedSection;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Inserts a saved section through the native insert_blocks tool, so the
 * blocks join the same proposal as the assistant's other edits.
 */
class InsertSavedSection implements Tool
{
    public function __construct(
        private readonly DesignSession $session,
        private readonly Team $team,
    ) {}

    public function name(): string
    {
        return 'insert_saved_section';
    }

    public function description(): Stringable|string
    {
        return 'Inserts one of the saved sections (from list_saved_sections) into the email. Without parentId it goes at the end of the email.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parentId' => ['nullable', 'string', 'max:255'],
            'index' => ['nullable', 'integer', 'min:0'],
        ]);
        $wanted = trim($validated['name']);
        $section = $this->team->savedSections()
            ->where(fn ($query) => $query
                ->where('uuid', $wanted)
                ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($wanted)]))
            ->newestFirst()
            ->first();

        if (! $section instanceof SavedSection) {
            return "No saved section \"{$wanted}\". ".ListSavedSections::summary($this->team);
        }

        return $this->session->run('insert_blocks', array_filter([
            'blocks' => $section->blocks,
            'parentId' => $validated['parentId'] ?? null,
            'index' => $validated['index'] ?? null,
        ], fn (mixed $value): bool => $value !== null));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Name or id from list_saved_sections.')->required(),
            'parentId' => $schema->string()->description('Container to insert into (default: the email itself).'),
            'index' => $schema->integer()->min(0)->description('Position among its siblings (default: last).'),
        ];
    }
}
