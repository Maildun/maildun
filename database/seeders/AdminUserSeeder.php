<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public const string DEMO_EMAIL = 'admin@maildun.com';

    public function run(): void
    {
        $user = User::query()->oldest('id')->first();

        if (! $user instanceof User) {
            $password = Str::password(32);
            $user = User::query()->create([
                'name' => 'Demo Administrator',
                'email' => self::DEMO_EMAIL,
                'email_verified_at' => now(),
                'password' => Hash::make($password),
            ]);

            $this->command->warn("Demo administrator created with a one-time password: {$password}");
        }

        $team = $this->personalWorkspace($user);

        $membership = $team->memberships()->firstOrCreate(
            ['user_id' => $user->id],
            ['role' => TeamRole::Owner->value],
        );

        if ($membership->role !== TeamRole::Owner->value) {
            $membership->update(['role' => TeamRole::Owner->value]);
        }

        $user->switchTeam($team);
    }

    /**
     * Find the oldest personal workspace the given user owns.
     *
     * Demo seeders use this instead of personalTeam(), which can return another user's
     * personal workspace the user was only invited to.
     */
    public static function ownedPersonalWorkspace(User $user): ?Team
    {
        return $user->ownedTeams()->where('teams.is_personal', true)->oldest('teams.id')->first();
    }

    /**
     * Find the personal workspace the administrator owns, or one an earlier seed left without an owner.
     *
     * Another user's personal workspace is never taken over; a new one is created instead.
     */
    private function personalWorkspace(User $user): Team
    {
        return self::ownedPersonalWorkspace($user)
            ?? $user->teams()
                ->where('teams.is_personal', true)
                ->whereDoesntHave('memberships', fn (Builder $query) => $query->where('role', TeamRole::Owner->value))
                ->oldest('teams.id')
                ->first()
            ?? Team::query()->create([
                'name' => "{$user->name}'s Team",
                'is_personal' => true,
            ]);
    }
}
