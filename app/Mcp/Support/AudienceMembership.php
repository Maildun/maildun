<?php

namespace App\Mcp\Support;

use App\Actions\Audiences\AddContactToAudiences;
use App\Enums\SubscriberStatus;
use App\Models\Audience;
use App\Models\AudienceOptOut;
use App\Models\Contact;
use App\Models\Team;
use App\Services\ManageContact;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AudienceMembership
{
    public function __construct(
        private ManageContact $manageContact,
        private AddContactToAudiences $addContactToAudiences,
    ) {}

    /**
     * Find or create the workspace contact and subscribe it to each audience
     * on a team member's consent. An existing contact keeps its profile and
     * tags, and anyone who unsubscribed is refused here: winning them back is
     * an explicit resubscribe, never a side effect of adding them again.
     *
     * @param  array{email: string, first_name?: string|null, last_name?: string|null}  $profile
     * @param  Collection<int, Audience>  $audiences
     * @param  list<string>  $tags
     * @return array{0: Contact, 1: int}
     */
    public function join(Team $team, array $profile, Collection $audiences, array $tags = []): array
    {
        $unsubscribedFrom = $audiences->filter(fn (Audience $audience): bool => AudienceOptOut::existsFor($audience->id, $profile['email'])
            || $audience->subscribers()
                ->where('email', $profile['email'])
                ->where('status', SubscriberStatus::Unsubscribed)
                ->exists());

        if ($unsubscribedFrom->isNotEmpty()) {
            throw ValidationException::withMessages([
                'email' => 'This person unsubscribed from '.$unsubscribedFrom->pluck('name')->join(', ')
                    .'. Use resubscribe-subscriber only if they consented again.',
            ]);
        }

        return DB::transaction(function () use ($team, $profile, $audiences, $tags): array {
            $contact = $this->manageContact->findOrCreate($team, $profile);

            if ($contact->wasRecentlyCreated) {
                $this->manageContact->syncTags($team, $contact, $tags);
            }

            return [$contact, $this->addContactToAudiences->handle($contact, $audiences, null)];
        });
    }
}
