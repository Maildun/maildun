<?php

namespace App\Actions\Audiences;

use App\Models\Audience;
use App\Models\AudienceOptOut;
use App\Models\Subscriber;
use App\Services\ManageContact;

class RestoreOptedOutMember
{
    public function __construct(private ManageContact $manageContact) {}

    /**
     * Bring back the unsubscribed member of an address whose membership was
     * deleted after it opted out, so the caller treats the signup exactly as
     * it would treat an existing opt-out. Returns null when there is nothing
     * to restore.
     *
     * @param  array{email: string, first_name?: string|null, last_name?: string|null}  $profile
     */
    public function handle(Audience $audience, array $profile): ?Subscriber
    {
        if (! AudienceOptOut::existsFor($audience->id, $profile['email'])
            || $audience->subscribers()->where('email', $profile['email'])->exists()) {
            return null;
        }

        $contact = $this->manageContact->findOrCreate($audience->team, $profile);

        return $audience->subscribers()->create([
            'contact_id' => $contact->id,
            'email' => $contact->email,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'language' => $audience->default_language,
        ]);
    }
}
