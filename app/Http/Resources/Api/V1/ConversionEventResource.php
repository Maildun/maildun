<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EmailConversionEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmailConversionEvent
 */
class ConversionEventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'event_id' => $this->external_id,
            'event' => $this->event_key,
            'email' => $this->email,
            'occurred_at' => $this->occurred_at->toISOString(),
            'received_at' => $this->created_at?->toISOString(),
            'value_minor' => $this->value_minor,
            'currency' => $this->currency,
            'metadata' => $this->metadata,
            'attribution' => [
                'type' => $this->attribution_type->value,
                'model' => 'last_touch',
                'campaign_series' => $this->campaignSeries?->uuid,
                'campaign' => $this->campaign?->uuid,
                'delivery' => $this->delivery?->uuid,
                'touched_at' => $this->touched_at?->toISOString(),
            ],
        ];
    }
}
