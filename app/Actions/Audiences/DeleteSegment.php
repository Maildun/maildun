<?php

namespace App\Actions\Audiences;

use App\Enums\EmailStatus;
use App\Models\Email;
use App\Models\Segment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteSegment
{
    /**
     * Delete the segment unless a scheduled or sending campaign still targets it.
     *
     * A campaign reads its segment while it loads recipients, and emails.segment_id
     * is nulled on delete, so removing it mid-send or before a scheduled send would
     * widen the send to the whole audience. Every campaign row that references the
     * segment stays locked from the check to the delete, so scheduling or starting
     * one of them in the meantime waits and is then seen by the check.
     *
     * @throws ValidationException
     */
    public function handle(Segment $segment, string $errorKey = 'segment'): void
    {
        DB::transaction(function () use ($segment, $errorKey): void {
            $campaigns = Email::query()
                ->where('segment_id', $segment->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'name', 'status', 'scheduled_at'])
                ->filter(fn (Email $email): bool => $email->status->isActive()
                    || ($email->status === EmailStatus::Draft && $email->scheduled_at !== null))
                ->sortBy('name')
                ->pluck('name');

            if ($campaigns->isNotEmpty()) {
                throw ValidationException::withMessages([
                    $errorKey => __('This segment is used by scheduled or sending campaigns: :campaigns.', [
                        'campaigns' => $campaigns->join(', '),
                    ]),
                ]);
            }

            $segment->delete();
        });
    }
}
