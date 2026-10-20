<?php

namespace App\Actions\Audiences;

use App\Enums\EmailStatus;
use App\Models\Email;
use App\Models\Segment;
use Illuminate\Validation\ValidationException;

class DeleteSegment
{
    /**
     * Delete the segment unless a scheduled or sending campaign still targets it.
     *
     * A campaign reads its segment while it loads recipients, and emails.segment_id
     * is nulled on delete, so removing it mid-send or before a scheduled send would
     * widen the send to the whole audience.
     *
     * @throws ValidationException
     */
    public function handle(Segment $segment, string $errorKey = 'segment'): void
    {
        $campaigns = Email::query()
            ->where('segment_id', $segment->id)
            ->where(fn ($query) => $query
                ->whereIn('status', EmailStatus::active())
                ->orWhere(fn ($scheduled) => $scheduled->where('status', EmailStatus::Draft)->whereNotNull('scheduled_at')))
            ->orderBy('name')
            ->pluck('name');

        if ($campaigns->isNotEmpty()) {
            throw ValidationException::withMessages([
                $errorKey => __('This segment is used by scheduled or sending campaigns: :campaigns.', [
                    'campaigns' => $campaigns->join(', '),
                ]),
            ]);
        }

        $segment->delete();
    }
}
