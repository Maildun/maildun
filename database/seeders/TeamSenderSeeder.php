<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSenderSeeder extends Seeder
{
    private const string TEAM_SLUG = 'maildun-studio';

    /**
     * @var list<array{email: string, name: string, reply_to: string}>
     */
    private const SENDERS = [
        [
            'email' => 'hello@maildun.test',
            'name' => 'Maildun Studio',
            'reply_to' => 'support@maildun.test',
        ],
        [
            'email' => 'support@maildun.test',
            'name' => 'Maildun Support',
            'reply_to' => 'support@maildun.test',
        ],
    ];

    /**
     * Seed verified sender identities for the dedicated demo workspace only.
     */
    public function run(): void
    {
        $team = Team::query()->where('slug', self::TEAM_SLUG)->first();

        if (! $team instanceof Team) {
            return;
        }

        foreach (self::SENDERS as $attributes) {
            $team->senders()->updateOrCreate(
                ['email' => $attributes['email']],
                [
                    'name' => $attributes['name'],
                    'reply_to' => $attributes['reply_to'],
                    'email_verified_at' => now(),
                ],
            );
        }

        $defaultSender = $team->senders()
            ->where('email', 'hello@maildun.test')
            ->sole();

        $team->forceFill([
            'active_sender_id' => $defaultSender->id,
            'email_from_name' => $defaultSender->name,
            'email_from_address' => $defaultSender->email,
            'email_reply_to' => $defaultSender->reply_to,
        ])->save();
    }
}
