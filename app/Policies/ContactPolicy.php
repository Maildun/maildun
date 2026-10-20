<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Contact;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->belongsToTeam($contact->team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageContact);
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->hasTeamPermission($contact->team, TeamPermission::ManageContact);
    }

    /**
     * Deleting a contact also deletes its audience memberships, so the user must be allowed to remove each one.
     */
    public function delete(User $user, Contact $contact): bool
    {
        return $this->update($user, $contact)
            && $contact->subscribers->every(fn (Subscriber $subscriber): bool => $user->can('delete', $subscriber));
    }
}
