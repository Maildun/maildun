<?php

namespace App\Actions\Audiences;

use App\Enums\SubscriberSource;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\SubscribeForm;
use App\Models\Tag;

class BuildSegmentRuleOptions
{
    /**
     * The choices the segment rule editor offers, shared by every page that
     * opens it. Deleted forms stay listed and flagged, because existing rules
     * and subscribers can still point at them.
     *
     * @return array{
     *     forms: list<array{uuid: string, name: string, deleted: bool}>,
     *     sources: list<array{value: string, label: string}>,
     *     tags: list<array{uuid: string, name: string}>,
     *     attributes: list<array{uuid: string, name: string, type: string}>,
     * }
     */
    public function handle(Audience $audience): array
    {
        return [
            'forms' => array_values($audience->subscribeForms()
                ->withTrashed()
                ->orderBy('name')
                ->orderBy('id')
                ->get(['uuid', 'name', 'deleted_at'])
                ->map(fn (SubscribeForm $form): array => [
                    'uuid' => $form->uuid,
                    'name' => $form->name,
                    'deleted' => $form->trashed(),
                ])
                ->all()),
            'sources' => array_map(fn (SubscriberSource $source): array => [
                'value' => $source->value,
                'label' => $source->label(),
            ], SubscriberSource::cases()),
            'tags' => array_values($audience->team->tags()
                ->orderBy('name')
                ->orderBy('id')
                ->get(['uuid', 'name'])
                ->map(fn (Tag $tag): array => ['uuid' => $tag->uuid, 'name' => $tag->name])
                ->all()),
            'attributes' => array_values($audience->audienceAttributes()
                ->orderBy('position')
                ->orderBy('id')
                ->get(['uuid', 'name', 'type'])
                ->map(fn (AudienceAttribute $attribute): array => [
                    'uuid' => $attribute->uuid,
                    'name' => $attribute->name,
                    'type' => $attribute->type->value,
                ])
                ->all()),
        ];
    }
}
