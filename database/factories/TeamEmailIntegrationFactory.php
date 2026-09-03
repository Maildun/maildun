<?php

namespace Database\Factories;

use App\Enums\EmailProvider;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSender;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TeamEmailIntegration>
 */
class TeamEmailIntegrationFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (TeamEmailIntegration $integration): void {
            $team = Team::query()->find($integration->team_id);

            if (! $team instanceof Team || ! $integration->isVerified()) {
                return;
            }

            if (! $team->senders()->whereNotNull('email_verified_at')->exists()) {
                $team->senders()->create([
                    'name' => $team->email_from_name ?: $team->name,
                    'email' => $team->email_from_address ?: 'hello@example.com',
                    'reply_to' => $team->email_reply_to,
                    'email_verified_at' => now(),
                    'verified_email_integration_id' => $integration->id,
                    'verified_email_integration_version' => $integration->verification_version,
                ]);
            }

            $team->senders()
                ->whereNotNull('email_verified_at')
                ->update([
                    'verified_email_integration_id' => $integration->id,
                    'verified_email_integration_version' => $integration->verification_version,
                ]);

            if ($team->active_sender_id === null) {
                $sender = $team->senders()
                    ->when(
                        filled($team->email_from_address),
                        fn ($query) => $query->where('email', Str::lower((string) $team->email_from_address)),
                    )
                    ->first() ?? $team->senders()->first();

                if ($sender instanceof TeamSender) {
                    $team->forceFill([
                        'active_sender_id' => $sender->id,
                        'email_from_name' => $sender->name,
                        'email_from_address' => $sender->email,
                        'email_reply_to' => $sender->reply_to,
                    ])->save();
                }
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'provider' => EmailProvider::Smtp,
            'name' => 'Primary SMTP',
            'settings' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'username' => fake()->userName(),
                'password' => fake()->password(16, 32),
                'encryption' => 'tls',
            ],
            'connected_at' => now(),
            'last_tested_at' => now(),
            'verification_version' => 1,
            'test_from_address' => 'delivery@example.com',
        ];
    }

    public function unverifiedSender(): static
    {
        return $this->afterCreating(function (TeamEmailIntegration $integration): void {
            $integration->verifiedSenders()->update([
                'verified_email_integration_id' => null,
                'verified_email_integration_version' => null,
            ]);
        });
    }

    public function withoutSender(): static
    {
        return $this->afterCreating(function (TeamEmailIntegration $integration): void {
            $team = Team::query()->find($integration->team_id);

            if (! $team instanceof Team) {
                return;
            }

            $integration->verifiedSenders()->delete();
            $team->forceFill([
                'active_sender_id' => null,
                'email_from_name' => null,
                'email_from_address' => null,
                'email_reply_to' => null,
            ])->save();
        });
    }

    public function untested(): static
    {
        return $this->state(fn (): array => [
            'last_tested_at' => null,
            'test_from_address' => null,
        ]);
    }

    public function smtp(): static
    {
        return $this->state(fn (): array => [
            'provider' => EmailProvider::Smtp,
            'name' => 'Primary SMTP',
            'settings' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'username' => fake()->userName(),
                'password' => fake()->password(16, 32),
                'encryption' => 'tls',
            ],
        ]);
    }

    public function ses(): static
    {
        return $this->state(fn (): array => [
            'provider' => EmailProvider::AmazonSes,
            'name' => 'Primary Amazon SES',
            'settings' => [
                'region' => 'us-east-1',
                'access_key_id' => 'AKIA'.Str::upper(Str::random(16)),
                'secret_access_key' => Str::random(40),
                'configuration_set' => 'maildun-feedback',
                'sns_topic_arn' => 'arn:aws:sns:us-east-1:123456789012:maildun-feedback',
            ],
        ]);
    }

    public function sendgrid(): static
    {
        return $this->state(fn (): array => [
            'provider' => EmailProvider::Sendgrid,
            'name' => 'Primary SendGrid',
            'settings' => [
                'api_key' => 'SG.'.Str::random(69),
            ],
        ]);
    }

    public function mailgun(): static
    {
        return $this->state(fn (): array => [
            'provider' => EmailProvider::Mailgun,
            'name' => 'Primary Mailgun',
            'settings' => [
                'region' => 'us',
                'username' => 'postmaster@'.fake()->domainName(),
                'password' => Str::random(32),
            ],
        ]);
    }

    public function resend(): static
    {
        return $this->state(fn (): array => [
            'provider' => EmailProvider::Resend,
            'name' => 'Primary Resend',
            'settings' => [
                'api_key' => 're_'.Str::random(32),
            ],
        ]);
    }

    public function postmark(): static
    {
        return $this->state(fn (): array => [
            'provider' => EmailProvider::Postmark,
            'name' => 'Primary Postmark',
            'settings' => [
                'token' => (string) Str::uuid(),
            ],
        ]);
    }
}
