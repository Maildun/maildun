<?php

namespace App\Models\Builders;

use App\Enums\SubscriberStatus;
use App\Models\AudienceOptOut;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * @extends Builder<Subscriber>
 */
class SubscriberBuilder extends Builder
{
    /**
     * Every subscriber delete, one model or a whole query, keeps the opt-outs
     * of the unsubscribed members it removes. Deleting a membership must never
     * erase the fact that the person asked to stop receiving email.
     */
    public function delete(): mixed
    {
        return DB::transaction(function (): mixed {
            AudienceOptOut::rememberMembers(
                $this->clone()
                    ->where('subscribers.status', SubscriberStatus::Unsubscribed)
                    ->toBase()
                    ->reorder()
                    ->get(['subscribers.audience_id', 'subscribers.email', 'subscribers.unsubscribed_at']),
            );

            return parent::delete();
        });
    }
}
