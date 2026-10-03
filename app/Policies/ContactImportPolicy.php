<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\User;

class ContactImportPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->can('viewAny', [Contact::class, $team]);
    }

    public function view(User $user, ContactImport $contactImport): bool
    {
        return $user->can('viewAny', [Contact::class, $contactImport->team]);
    }

    public function update(User $user, ContactImport $contactImport): bool
    {
        return $contactImport->audience === null
            ? $user->can('create', [Contact::class, $contactImport->team])
            : $user->can('create', [Subscriber::class, $contactImport->audience]);
    }

    public function delete(User $user, ContactImport $contactImport): bool
    {
        return $this->update($user, $contactImport);
    }
}
