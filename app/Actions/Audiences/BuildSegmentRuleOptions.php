<?php

namespace App\Actions\Audiences;

use App\Enums\SubscriberSource;
use App\Models\Audience;
use App\Models\SubscribeForm;

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
        ];
    }
}
