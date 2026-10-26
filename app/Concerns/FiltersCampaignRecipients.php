<?php

namespace App\Concerns;

use App\Enums\EmailDeliveryStatus;
use App\Models\EmailDelivery;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The campaign report's recipient tabs and search, shared by the report and
 * its export so both always select the same rows.
 */
trait FiltersCampaignRecipients
{
    use SearchesText;

    /**
     * @param  Builder<EmailDelivery>  $query
     * @return Builder<EmailDelivery>
     */
    protected function applyRecipientFilter(Builder $query, ?string $filter, Team $team): Builder
    {
        return match ($filter) {
            'retryable' => $query->retryableFor($team),
            'opened' => $query->where('opens_count', '>', 0),
            'clicked' => $query->where('clicks_count', '>', 0),
            null => $query,
            default => $query->where('status', $filter),
        };
    }

    /**
     * @param  Builder<EmailDelivery>  $query
     * @return Builder<EmailDelivery>
     */
    protected function applyRecipientSearch(Builder $query, string $search): Builder
    {
        if ($search === '') {
            return $query;
        }

        return $this->whereAnyColumnContains($query, ['email_address', 'first_name', 'last_name'], $search);
    }

    protected function recipientSearch(Request $request): string
    {
        return Str::limit($request->string('q')->trim()->toString(), 100, '');
    }

    protected function recipientFilter(Request $request): ?string
    {
        $status = $request->string('status')->toString();
        $allowed = [
            'retryable',
            'opened',
            'clicked',
            ...array_column(EmailDeliveryStatus::cases(), 'value'),
        ];

        return in_array($status, $allowed, true) ? $status : null;
    }
}
