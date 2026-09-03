<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateTeam
{
    /**
     * Create a new team and add the user as owner.
     */
    public function handle(User $user, string $name, ?UploadedFile $logo = null, bool $isPersonal = false): Team
    {
        return DB::transaction(function () use ($user, $name, $logo, $isPersonal) {
            $team = Team::create([
                'name' => $name,
                'is_personal' => $isPersonal,
            ]);

            $team->ensureDefaultRoles();

            if ($logo) {
                $storedPath = $logo->store('team-logos', 'public');

                if ($storedPath === false) {
                    throw new RuntimeException('Unable to store the uploaded workspace logo.');
                }

                $team->logo_path = $storedPath;
                $team->save();
            }

            $membership = $team->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Owner->value,
            ]);

            $user->switchTeam($team);

            return $team;
        });
    }
}
