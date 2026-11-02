<?php

namespace App\Actions\Emails;

use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\Segment;
use App\Models\Tag;
use App\Models\TransactionalEmail;

class ResolveBuilderConditions
{
    /**
     * The reader groups a personalized section in a block email may target:
     * the campaign audience's segments and the workspace tags. Templates are
     * not tied to an audience, so they only get tags, and transactional email
     * has no subscriber to check, so it gets none.
     *
     * @return list<array{id: string, label: string, group: string}>
     */
    public function handle(Email|TransactionalEmail|EmailTemplate $record): array
    {
        if ($record instanceof TransactionalEmail) {
            return [];
        }

        $segments = $record instanceof Email
            ? ($record->audience?->segments()->orderByRaw('LOWER(name)')->get(['uuid', 'name']) ?? collect())
            : collect();

        return [
            ...$segments->map(fn (Segment $segment): array => [
                'id' => ResolveConditionalSections::segmentId($segment->uuid),
                'label' => $segment->name,
                'group' => 'Segments',
            ])->values()->all(),
            ...$record->team->tags()->orderByRaw('LOWER(name)')->get(['uuid', 'name'])
                ->map(fn (Tag $tag): array => [
                    'id' => ResolveConditionalSections::tagId($tag->uuid),
                    'label' => $tag->name,
                    'group' => 'Tags',
                ])->values()->all(),
        ];
    }
}
