<?php

namespace App\Ai\Tools;

use App\Models\SavedSection;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListSavedSections implements Tool
{
    public function __construct(private readonly Team $team) {}

    public function name(): string
    {
        return 'list_saved_sections';
    }

    public function description(): Stringable|string
    {
        return "Lists the author's saved sections in this workspace (their own header, footer, logo …). Prefer them over building those parts again.";
    }

    public function handle(Request $request): Stringable|string
    {
        return self::summary($this->team);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * One line per saved section, also what insert_saved_section replies when
     * it cannot find the one asked for.
     */
    public static function summary(Team $team): string
    {
        $sections = $team->savedSections()->newestFirst()->get();

        if ($sections->isEmpty()) {
            return 'No saved sections in this workspace. The author saves them in the editor, from Your sections in the block palette.';
        }

        return "Saved sections:\n".$sections
            ->map(fn (SavedSection $section): string => sprintf(
                '- %s (id %s): %s',
                $section->name,
                $section->uuid,
                implode(', ', array_map(fn (array $block): string => (string) ($block['type'] ?? 'block'), $section->blocks)),
            ))
            ->implode("\n");
    }
}
