<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class WorkspaceMembersDemoSeeder extends Seeder
{
    private const TEAM_SLUG = 'maildun-studio';

    /**
     * @var list<array{name: string, email: string, role: TeamRole}>
     */
    private const MEMBERS = [
        ['name' => 'Maya Chen', 'email' => 'maya@members-demo.test', 'role' => TeamRole::Admin],
        ['name' => 'Theo James', 'email' => 'theo@members-demo.test', 'role' => TeamRole::Admin],
        ['name' => 'Olivia Park', 'email' => 'olivia@members-demo.test', 'role' => TeamRole::Admin],
        ['name' => 'Noah Bennett', 'email' => 'noah@members-demo.test', 'role' => TeamRole::Member],
        ['name' => 'Ava Morgan', 'email' => 'ava@members-demo.test', 'role' => TeamRole::Member],
        ['name' => 'Ethan Lee', 'email' => 'ethan@members-demo.test', 'role' => TeamRole::Member],
        ['name' => 'Sophia Patel', 'email' => 'sophia@members-demo.test', 'role' => TeamRole::Member],
        ['name' => 'Liam Walker', 'email' => 'liam@members-demo.test', 'role' => TeamRole::Member],
        ['name' => 'Isabella Kim', 'email' => 'isabella@members-demo.test', 'role' => TeamRole::Member],
        ['name' => 'Lucas Wright', 'email' => 'lucas@members-demo.test', 'role' => TeamRole::Member],
        ['name' => 'Mia Rogers', 'email' => 'mia@members-demo.test', 'role' => TeamRole::Member],
        ['name' => 'Henry Brooks', 'email' => 'henry@members-demo.test', 'role' => TeamRole::Member],
        ['name' => 'Charlotte Reed', 'email' => 'charlotte@members-demo.test', 'role' => TeamRole::Member],
        ['name' => 'James Cooper', 'email' => 'james@members-demo.test', 'role' => TeamRole::Member],
        ['name' => 'Amelia Foster', 'email' => 'amelia@members-demo.test', 'role' => TeamRole::Member],
        ['name' => 'Benjamin Price', 'email' => 'benjamin@members-demo.test', 'role' => TeamRole::Member],
        ['name' => 'Harper Ellis', 'email' => 'harper@members-demo.test', 'role' => TeamRole::Member],
        ['name' => 'Daniel Ross', 'email' => 'daniel@members-demo.test', 'role' => TeamRole::Member],
        ['name' => 'Evelyn Stone', 'email' => 'evelyn@members-demo.test', 'role' => TeamRole::Member],
        ['name' => 'Michael Grant', 'email' => 'michael@members-demo.test', 'role' => TeamRole::Member],
        ['name' => 'Abigail Hayes', 'email' => 'abigail@members-demo.test', 'role' => TeamRole::Member],
        ['name' => 'Alexander Cole', 'email' => 'alexander@members-demo.test', 'role' => TeamRole::Member],
        ['name' => 'Ella Simmons', 'email' => 'ella@members-demo.test', 'role' => TeamRole::Member],
        ['name' => 'William Shaw', 'email' => 'william@members-demo.test', 'role' => TeamRole::Member],
    ];

    /**
     * @var list<array{email: string, role: TeamRole}>
     */
    private const INVITATIONS = [
        ['email' => 'grace@invitation-demo.test', 'role' => TeamRole::Admin],
        ['email' => 'jack@invitation-demo.test', 'role' => TeamRole::Member],
        ['email' => 'chloe@invitation-demo.test', 'role' => TeamRole::Member],
        ['email' => 'sam@invitation-demo.test', 'role' => TeamRole::Member],
        ['email' => 'nora@invitation-demo.test', 'role' => TeamRole::Member],
        ['email' => 'leo@invitation-demo.test', 'role' => TeamRole::Member],
        ['email' => 'zoe@invitation-demo.test', 'role' => TeamRole::Member],
        ['email' => 'kai@invitation-demo.test', 'role' => TeamRole::Member],
        ['email' => 'riley@invitation-demo.test', 'role' => TeamRole::Member],
    ];

    /**
     * Seed a workspace that demonstrates the paginated members settings page.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);

        $owner = User::query()->oldest('id')->firstOrFail();
        $team = Team::query()->firstOrCreate(
            ['slug' => self::TEAM_SLUG],
            ['name' => 'Maildun Studio', 'is_personal' => false],
        );

        $this->membership($team, $owner, TeamRole::Owner);

        $password = Hash::make('password');

        foreach (self::MEMBERS as $member) {
            $user = User::query()->firstOrCreate(
                ['email' => $member['email']],
                [
                    'name' => $member['name'],
                    'email_verified_at' => now(),
                    'password' => $password,
                ],
            );

            $this->membership($team, $user, $member['role']);
        }

        foreach (self::INVITATIONS as $invitation) {
            TeamInvitation::query()->updateOrCreate(
                ['team_id' => $team->id, 'email' => $invitation['email']],
                [
                    'role' => $invitation['role'],
                    'invited_by' => $owner->id,
                    'expires_at' => now()->addDays(14),
                    'accepted_at' => null,
                ],
            );
        }
    }

    /**
     * Ensure a user has the expected role in the demo workspace.
     */
    private function membership(Team $team, User $user, TeamRole $role): void
    {
        $membership = $team->memberships()->firstOrCreate(
            ['user_id' => $user->id],
            ['role' => $role],
        );

        if ($membership->role !== $role->value) {
            $membership->update(['role' => $role->value]);
        }
    }
}
