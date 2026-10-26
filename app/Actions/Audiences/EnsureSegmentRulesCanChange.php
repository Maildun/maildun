<?php

namespace App\Actions\Audiences;

use App\Enums\EmailStatus;
use App\Enums\SegmentMatchType;
use App\Models\Segment;
use Illuminate\Validation\ValidationException;

class EnsureSegmentRulesCanChange
{
    /**
     * Refuse a rules or match type change while a campaign to the segment is
     * queued or sending.
     *
     * Each send chunk reads the segment's members as it goes, so a change
     * mid-send would mail people the campaign was never approved for. Name and
     * description stay editable.
     *
     * @param  array<string, mixed>  $changes
     *
     * @throws ValidationException
     */
    public function handle(Segment $segment, array $changes): void
    {
        if (! $this->changesRules($segment, $changes)) {
            return;
        }

        $campaigns = $segment->campaignNamesWithStatus(EmailStatus::active());

        if ($campaigns->isNotEmpty()) {
            throw ValidationException::withMessages([
                'rules' => __('The rules cannot change while these campaigns are sending: :campaigns.', [
                    'campaigns' => $campaigns->join(', '),
                ]),
            ]);
        }
    }

    /** @param  array<string, mixed>  $changes */
    private function changesRules(Segment $segment, array $changes): bool
    {
        if (array_key_exists('match_type', $changes) && SegmentMatchType::tryFrom((string) $changes['match_type']) !== $segment->match_type) {
            return true;
        }

        return array_key_exists('rules', $changes)
            && $this->normalize($changes['rules']) !== $this->normalize($segment->rules);
    }

    /**
     * @return list<array{field: string, operator: string, value: string}>
     */
    private function normalize(mixed $rules): array
    {
        return array_values(array_map(fn (mixed $rule): array => [
            'field' => (string) ($rule['field'] ?? ''),
            'operator' => (string) ($rule['operator'] ?? ''),
            'value' => (string) ($rule['value'] ?? ''),
        ], is_array($rules) ? $rules : []));
    }
}
