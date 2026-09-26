<?php

use App\Enums\EmailDeliveryStatus;
use App\Enums\TeamRole;
use App\Exceptions\EmailTransportException;
use App\Jobs\SendTransactionalEmailDelivery;
use App\Models\Team;
use App\Models\TeamApiKey;
use App\Models\TransactionalEmail;
use App\Models\TransactionalEmailDelivery;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the delivery log lists sends newest first and filters by recipient and status', function () {
    $user = User::factory()->create();
    $email = TransactionalEmail::factory()->for($user->currentTeam)->create();
    $key = TeamApiKey::factory()->for($user->currentTeam)->create(['name' => 'Production']);
    TransactionalEmailDelivery::factory()->for($email)->for($user->currentTeam)->create([
        'to_address' => 'ada@example.com',
        'status' => EmailDeliveryStatus::Sent,
        'team_api_key_id' => $key->id,
    ]);
    TransactionalEmailDelivery::factory()->for($email)->for($user->currentTeam)->create([
        'to_address' => 'grace@example.com',
        'status' => EmailDeliveryStatus::Failed,
        'failure_reason' => 'Email delivery failed.',
    ]);

    $this->actingAs($user)
        ->get(route('transactional_emails.log', [$user->currentTeam, $email]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactional/log')
            ->has('deliveries.data', 2)
            ->where('deliveries.data.0.to', 'grace@example.com')
            ->where('deliveries.data.1.source', 'Production'));

    $this->actingAs($user)
        ->get(route('transactional_emails.log', [$user->currentTeam, $email, 'status' => 'failed', 'q' => 'GRACE']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['q' => 'GRACE', 'status' => 'failed'])
            ->has('deliveries.data', 1));

    $this->actingAs($user)
        ->get(route('transactional_emails.log', [$user->currentTeam, $email, 'status' => 'nonsense']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.status', ''));
});

test('the delivery log is not reachable through another workspace', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $email = TransactionalEmail::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->get(route('transactional_emails.log', [$otherTeam, $email]))
        ->assertNotFound();
});

test('a failed transactional job stores only a sanitized reason and never overwrites a sent delivery', function () {
    $email = TransactionalEmail::factory()->create();
    $pending = TransactionalEmailDelivery::factory()->for($email)->for($email->team)->create(['status' => EmailDeliveryStatus::Sending]);
    $sent = TransactionalEmailDelivery::factory()->for($email)->for($email->team)->create(['status' => EmailDeliveryStatus::Sent]);

    (new SendTransactionalEmailDelivery($pending->id))->failed(new RuntimeException('SMTP password hunter2 rejected'));
    (new SendTransactionalEmailDelivery($sent->id))->failed(new EmailTransportException);

    expect($pending->fresh())
        ->status->toBe(EmailDeliveryStatus::Failed)
        ->failure_reason->toBe('Delivery failed.')
        ->and($sent->fresh()->status)->toBe(EmailDeliveryStatus::Sent);
});
