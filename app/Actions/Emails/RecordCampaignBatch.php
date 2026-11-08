<?php

namespace App\Actions\Emails;

use App\Models\Email;
use Illuminate\Bus\Batch;

/**
 * A campaign batch's "before" callback: point the campaign at its new batch
 * before any job is pushed. FinalizeEmailSend only closes the campaign for its
 * current batch, so a batch whose jobs finished before the dispatching request
 * wrote batch_id would otherwise never finalize it.
 */
class RecordCampaignBatch
{
    public function __construct(public int $emailId) {}

    public function __invoke(Batch $batch): void
    {
        Email::query()->whereKey($this->emailId)->update(['batch_id' => $batch->id]);
    }
}
