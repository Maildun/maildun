<?php

namespace App\Ai\Tools;

use App\Actions\Media\SearchReadyTeamMedia;
use App\Mcp\Support\ResourcePayload;
use App\Models\Media;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListDesignMedia implements Tool
{
    public function __construct(
        private readonly Team $team,
        private readonly SearchReadyTeamMedia $search,
        private readonly ResourcePayload $payload,
    ) {}

    public function name(): string
    {
        return 'list_media';
    }

    public function description(): Stringable|string
    {
        return 'Lists images from the workspace media library with absolute URLs, alt text and size. Use these URLs for image, avatar and section image params instead of placeholders.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $media = $this->search->handle($this->team, (string) ($validated['search'] ?? ''));

        if ($media->isEmpty()) {
            return 'No matching images in the media library. Use https://example.com placeholders and tell the author to replace them.';
        }

        return (string) json_encode(
            $media->map(fn (Media $item): array => $this->payload->media($item))->values()->all(),
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Optional file name or alt text search, e.g. "logo".'),
        ];
    }
}
