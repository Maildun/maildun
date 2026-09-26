<?php

use App\Enums\EmailDeliveryStatus;
use App\Jobs\SendTransactionalEmailDelivery;
use App\Mail\TransactionalEmailMessage;
use App\Models\TeamApiKey;
use App\Models\TeamEmailIntegration;
use App\Models\TeamSender;
use App\Models\TransactionalEmail;
use App\Models\TransactionalEmailDelivery;
use App\Services\TeamMailer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

test('a team api key queues a rendered published transactional email', function () {
    Queue::fake();
    $email = TransactionalEmail::factory()->published()->create([
        'subject' => 'Hi {{ first_name }}',
        'html' => '<p>Order {{ order_number }} for {{ first_name }}</p>',
    ]);
    $integration = TeamEmailIntegration::factory()->for($email->team)->ses()->create();
    $sender = TeamSender::factory()->for($email->team)->create();
    $email->team->forceFill([
        'active_sender_id' => $sender->id,
        'email_from_address' => $sender->email,
    ])->save();
    $issued = TeamApiKey::issue($email->team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.transactional-emails.send', $email->slug), [
            'to' => 'ADA@EXAMPLE.COM',
            'data' => [
                'first_name' => '<Ada>',
                'order_number' => 1001,
            ],
        ])
        ->assertAccepted()
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.transactional_email', $email->slug)
        ->assertJsonPath('data.to', 'ada@example.com')
        ->assertJsonPath('meta.idempotent_replay', false);

    $delivery = TransactionalEmailDelivery::query()->sole();

    expect($delivery->team_id)->toBe($email->team_id)
        ->and($delivery->subject)->toBe('Hi <Ada>')
        ->and($delivery->html)->toBe('<p>Order 1001 for &lt;Ada&gt;</p>')
        ->and($delivery->provider)->toBe('ses')
        ->and($delivery->uses_team_email_integration)->toBeTrue()
        ->and($delivery->status)->toBe(EmailDeliveryStatus::Queued);

    Queue::assertPushed(
        SendTransactionalEmailDelivery::class,
        fn (SendTransactionalEmailDelivery $job): bool => $job->deliveryId === $delivery->id,
    );
});

test('transactional sends require a valid key and a published same-team template', function () {
    $draft = TransactionalEmail::factory()->create();
    $otherEmail = TransactionalEmail::factory()->published()->create();
    $issued = TeamApiKey::issue($draft->team, 'Production');

    $this->postJson(route('api.v1.transactional-emails.send', $draft->slug), [
        'to' => 'ada@example.com',
    ])->assertUnauthorized();

    $this->withToken('not-a-key')
        ->postJson(route('api.v1.transactional-emails.send', $draft->slug), [
            'to' => 'ada@example.com',
        ])
        ->assertUnauthorized();

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.transactional-emails.send', $draft->slug), [
            'to' => 'ada@example.com',
        ])
        ->assertNotFound();

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.transactional-emails.send', $otherEmail->slug), [
            'to' => 'ada@example.com',
        ])
        ->assertNotFound();
});

test('transactional sends do not fall back after a workspace provider is disconnected', function () {
    Queue::fake();
    $email = TransactionalEmail::factory()->published()->create();
    $issued = TeamApiKey::issue($email->team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.transactional-emails.send', $email->slug), [
            'to' => 'ada@example.com',
        ])
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'Email delivery is disconnected for this workspace.');

    expect(TransactionalEmailDelivery::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('transactional sends validate recipient and scalar merge data', function () {
    $email = TransactionalEmail::factory()->published()->create();
    $issued = TeamApiKey::issue($email->team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.transactional-emails.send', $email->slug), [
            'to' => 'invalid',
            'data' => [
                '1bad' => 'value',
                'nested' => ['not' => 'scalar'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to', 'data', 'data.nested']);
});

test('idempotency keys return the original delivery without queueing twice', function () {
    Queue::fake();
    $email = TransactionalEmail::factory()->published()->create();
    TeamEmailIntegration::factory()->for($email->team)->smtp()->create();
    $issued = TeamApiKey::issue($email->team, 'Production');
    $headers = ['Idempotency-Key' => 'receipt-1001'];
    $payload = ['to' => 'ada@example.com', 'data' => ['order' => 1001]];

    $first = $this->withToken($issued['token'])
        ->withHeaders($headers)
        ->postJson(route('api.v1.transactional-emails.send', $email->slug), $payload)
        ->assertAccepted()
        ->assertJsonPath('meta.idempotent_replay', false);

    $this->withToken($issued['token'])
        ->withHeaders($headers)
        ->postJson(route('api.v1.transactional-emails.send', $email->slug), $payload)
        ->assertAccepted()
        ->assertJsonPath('data.id', $first->json('data.id'))
        ->assertJsonPath('meta.idempotent_replay', true);

    expect(TransactionalEmailDelivery::query()->count())->toBe(1);
    Queue::assertPushed(SendTransactionalEmailDelivery::class, 1);

    $this->withToken($issued['token'])
        ->withHeaders($headers)
        ->postJson(route('api.v1.transactional-emails.send', $email->slug), [
            'to' => 'different@example.com',
        ])
        ->assertConflict();
});

test('the delivery job sends once and records the result', function () {
    Mail::fake();
    $email = TransactionalEmail::factory()->published()->create();
    $apiKey = TeamApiKey::issue($email->team, 'Production')['key'];
    $delivery = TransactionalEmailDelivery::query()->create([
        'team_id' => $email->team_id,
        'transactional_email_id' => $email->id,
        'team_api_key_id' => $apiKey->id,
        'to_address' => 'ada@example.com',
        'subject' => 'Your receipt',
        'html' => '<p>Paid</p>',
        'from_name' => 'Maildun',
        'from_address' => 'mail@example.com',
        'provider' => 'array',
    ]);
    $integration = TeamEmailIntegration::factory()->for($email->team)->ses()->create();
    $sender = TeamSender::factory()->for($email->team)->create(['email' => 'mail@example.com']);
    $email->team->forceFill([
        'active_sender_id' => $sender->id,
        'email_from_address' => $sender->email,
    ])->save();

    $job = new SendTransactionalEmailDelivery($delivery->id);
    $job->handle(app(TeamMailer::class));
    $job->handle(app(TeamMailer::class));

    Mail::assertSent(TransactionalEmailMessage::class, 1);
    expect($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Sent)
        ->and($delivery->fresh()->provider)->toBe('ses')
        ->and($delivery->fresh()->uses_team_email_integration)->toBeTrue()
        ->and($delivery->fresh()->sent_at)->not->toBeNull();
});

test('api errors carry a machine readable code', function () {
    $email = TransactionalEmail::factory()->published()->create();
    $issued = TeamApiKey::issue($email->team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.transactional-emails.send', 'missing-template'), ['to' => 'ada@example.com'])
        ->assertNotFound()
        ->assertJsonPath('code', 'transactional_email_not_found');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.transactional-emails.send', $email->slug), ['to' => 'not-an-address'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('to');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.transactional-emails.send', $email->slug), ['to' => 'ada@example.com'])
        ->assertServiceUnavailable()
        ->assertJsonPath('code', 'delivery_disconnected');
});

test('an api key can check the status of a message its workspace sent', function () {
    $email = TransactionalEmail::factory()->published()->create(['slug' => 'receipt']);
    $delivery = TransactionalEmailDelivery::factory()->for($email)->for($email->team)->create([
        'to_address' => 'ada@example.com',
        'status' => EmailDeliveryStatus::Failed,
        'failure_reason' => 'Email delivery failed.',
        'html' => '<p>secret body</p>',
    ]);
    $issued = TeamApiKey::issue($email->team, 'Production');
    $otherTeamKey = TeamApiKey::issue(TransactionalEmail::factory()->create()->team, 'Other');

    $this->withToken($issued['token'])
        ->getJson(route('api.v1.transactional-deliveries.show', $delivery->uuid))
        ->assertOk()
        ->assertJsonPath('data.id', $delivery->uuid)
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.transactional_email', 'receipt')
        ->assertJsonPath('data.failure_reason', 'Email delivery failed.')
        ->assertDontSee('secret body');

    $this->withToken($otherTeamKey['token'])
        ->getJson(route('api.v1.transactional-deliveries.show', $delivery->uuid))
        ->assertNotFound()
        ->assertJsonPath('code', 'delivery_not_found');

    $this->withToken($issued['token'])
        ->getJson(route('api.v1.transactional-deliveries.show', 'not-a-uuid'))
        ->assertNotFound();
});
