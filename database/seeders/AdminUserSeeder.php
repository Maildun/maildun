<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
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

        $team = $user->personalTeam() ?? Team::query()->create([
            'name' => "{$user->name}'s Team",
            'is_personal' => true,
        ]);

        $membership = $team->memberships()->firstOrCreate(
            ['user_id' => $user->id],
            ['role' => TeamRole::Admin],
        );

        if ($membership->role !== TeamRole::Admin->value) {
            $membership->update(['role' => TeamRole::Admin->value]);
        }

        $user->switchTeam($team);
    }
}
