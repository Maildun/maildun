<?php

use App\Actions\Automations\AdvanceAutomationRun;
use App\Actions\Emails\ProcessSesEvent;
use App\Actions\Emails\RecordEmailAddressHealth;
use App\Enums\AutomationAction;
use App\Enums\AutomationRunStatus;
use App\Enums\AutomationStatus;
use App\Enums\EmailAddressHealthReason;
use App\Enums\EmailAddressHealthStatus;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailProvider;
use App\Enums\SubscriberStatus;
use App\Enums\TransactionalEmailStatus;
use App\Exceptions\EmailTransportException;
use App\Jobs\ProcessAutomationRun;
use App\Jobs\SendAutomationEmailDelivery;
use App\Mail\AutomationEmail;
use App\Models\Audience;
use App\Models\Automation;
use App\Models\AutomationEmailDelivery;
use App\Models\AutomationRun;
use App\Models\EmailAddressHealth;
use App\Models\EmailProviderEvent;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\TransactionalEmail;
use App\Services\TeamMailer;
use Illuminate\Database\QueryException;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Symfony\Component\Mailer\Exception\TransportException;

/**
 * Walk a trigger → send run up to its queued email, leaving the send job on
 * the faked queue.
 *
 * @return array{AutomationRun, AutomationEmailDelivery}
 */
function queuedAutomationSend(): array
{
    $automation = Automation::factory()->active()->create();
    TeamEmailIntegration::factory()->for($automation->team)->ses()->create();
    $email = TransactionalEmail::factory()->for($automation->team)->published()->create();
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($automation->team))->create();
    $run = $automation->runs()->create([
        'subscriber_id' => $subscriber->id,
        'status' => AutomationRunStatus::Pending,
        'graph' => [
            'nodes' => [
                ['id' => 'trigger', 'type' => 'trigger', 'data' => ['kind' => 'subscribed']],
                ['id' => 'send', 'type' => 'action', 'data' => [
                    'kind' => AutomationAction::SendEmail->value,
                    'transactional_email_uuid' => $email->uuid,
                ]],
            ],
            'edges' => [['source' => 'trigger', 'target' => 'send']],
        ],
        'current_node_id' => 'send',
    ]);

    app(AdvanceAutomationRun::class)->handle($run, 'send');

    return [$run->refresh(), AutomationEmailDelivery::query()->where('automation_run_id', $run->id)->sole()];
}

test('automation mail carries its feedback correlation tag', function () {
    $team = Team::factory()->create();
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $automation = Automation::factory()->for($team)->create();
    $run = AutomationRun::factory()->for($automation)->for($subscriber)->create();
    $email = TransactionalEmail::factory()->for($team)->published()->create();
    $delivery = AutomationEmailDelivery::factory()->for($run, 'run')->create([
        'team_id' => $team->id,
        'transactional_email_id' => $email->id,
        'subscriber_id' => $subscriber->id,
        'to_address' => $subscriber->email,
        'provider' => EmailProvider::AmazonSes,
    ]);

    $mailable = new AutomationEmail($email, $subscriber, 'Subject', '<p>Body</p>', $delivery);

    expect($mailable->envelope()->metadata)->toBe([
        'automation_delivery_uuid' => $delivery->uuid,
    ]);
});

test('an automation complaint is persisted and suppresses the workspace address', function () {
    $topicArn = 'arn:aws:sns:us-east-1:123456789012:automation-feedback';
    $team = Team::factory()->create();
    $audience = Audience::factory()->for($team)->create();
    $subscriber = Subscriber::factory()->for($audience)->create();
    $automation = Automation::factory()->for($team)->create();
    $run = AutomationRun::factory()->for($automation)->for($subscriber)->create();
    $delivery = AutomationEmailDelivery::factory()->for($run, 'run')->create([
        'team_id' => $team->id,
        'subscriber_id' => $subscriber->id,
        'to_address' => $subscriber->email,
        'status' => EmailDeliveryStatus::Sent,
        'provider' => EmailProvider::AmazonSes,
        'provider_message_id' => 'automation-message-id',
        'ses_sns_topic_arn_hash' => hash('sha256', $topicArn),
    ]);

    app(ProcessSesEvent::class)->handle('automation-complaint-event', [
        'eventType' => 'Complaint',
        'mail' => [
            'messageId' => 'automation-message-id',
            'timestamp' => now()->subSecond()->toISOString(),
            'tags' => ['automation_delivery_uuid' => [$delivery->uuid]],
        ],
        'complaint' => ['timestamp' => now()->toISOString()],
    ], $topicArn);

    $health = EmailAddressHealth::query()->sole();
    $event = EmailProviderEvent::query()->sole();

    expect($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Complained)
        ->and($delivery->fresh()->complained_at)->not->toBeNull()
        ->and($subscriber->fresh()->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($health->status)->toBe(EmailAddressHealthStatus::Suppressed)
        ->and($health->reason)->toBe(EmailAddressHealthReason::Complaint)
        ->and($health->complaint_count)->toBe(1)
        ->and($event->automation_email_delivery_id)->toBe($delivery->id);
});

test('a send step queues its email instead of handing it to the transport inline', function () {
    Queue::fake([SendAutomationEmailDelivery::class, ProcessAutomationRun::class]);
    Mail::fake();

    [$run, $delivery] = queuedAutomationSend();

    Mail::assertNothingSent();
    Queue::assertPushed(
        SendAutomationEmailDelivery::class,
        fn (SendAutomationEmailDelivery $job): bool => $job->deliveryId === $delivery->id,
    );

    expect($delivery->status)->toBe(EmailDeliveryStatus::Queued)
        ->and($delivery->send_attempted_at)->toBeNull()
        ->and($delivery->automation_run_step_id)->toBe($run->steps()->where('node_id', 'send')->value('id'))
        ->and($run->steps()->where('node_id', 'send')->value('status'))->toBe('running')
        ->and($run->status)->toBe(AutomationRunStatus::Running);
});

test('a sent automation email is recorded and a late failure report does not undo it', function () {
    Queue::fake([SendAutomationEmailDelivery::class, ProcessAutomationRun::class]);
    Mail::fake();
    $this->freezeSecond();

    [$run, $delivery] = queuedAutomationSend();

    app()->call([new SendAutomationEmailDelivery($delivery->id), 'handle']);

    // A failure reported after the transport accepted the email changes nothing.
    (new SendAutomationEmailDelivery($delivery->id))->failed(new EmailTransportException);

    Mail::assertSent(AutomationEmail::class, 1);
    expect($delivery->refresh()->status)->toBe(EmailDeliveryStatus::Sent)
        ->and($delivery->sent_at?->equalTo(now()))->toBeTrue()
        ->and($delivery->send_attempted_at?->equalTo(now()))->toBeTrue()
        ->and($delivery->failure_reason)->toBeNull()
        ->and($run->steps()->where('node_id', 'send')->value('status'))->toBe('completed')
        ->and($run->steps()->where('node_id', 'send')->value('result'))->toBe(['transactional_email_uuid' => $delivery->transactionalEmail->uuid])
        ->and($run->refresh()->status)->toBe(AutomationRunStatus::Completed)
        ->and(EmailAddressHealth::query()->count())->toBe(0);
});

test('an automation email waits for a send slot instead of failing its run', function () {
    Queue::fake([SendAutomationEmailDelivery::class, ProcessAutomationRun::class]);
    Mail::fake();
    config()->set('delivery.rate_limit.per_second', 1);
    config()->set('delivery.rate_limit.release_after', 5);
    $this->freezeTime();
    [, $first] = queuedAutomationSend();
    [$waitingRun, $waiting] = queuedAutomationSend();
    $runThroughMiddleware = function (int $deliveryId): SendAutomationEmailDelivery {
        $job = (new SendAutomationEmailDelivery($deliveryId))->withFakeQueueInteractions();
        (new Pipeline(app()))
            ->send($job)
            ->through($job->middleware())
            ->then(fn (SendAutomationEmailDelivery $job) => app()->call([$job, 'handle']));

        return $job;
    };

    $runThroughMiddleware($first->id)->assertNotReleased();
    $runThroughMiddleware($waiting->id)->assertReleased(5);

    Mail::assertSentCount(1);
    expect($waiting->refresh()->status)->toBe(EmailDeliveryStatus::Queued)
        ->and($waiting->send_attempted_at)->toBeNull()
        ->and($waitingRun->steps()->where('node_id', 'send')->value('status'))->toBe('running')
        ->and($waitingRun->refresh()->status)->toBe(AutomationRunStatus::Running);
});

test('a transport failure hands the automation email back for the queue to retry', function () {
    Queue::fake([SendAutomationEmailDelivery::class, ProcessAutomationRun::class]);
    Mail::fake();

    [$run, $delivery] = queuedAutomationSend();

    $teamMailer = Mockery::mock(TeamMailer::class);
    $teamMailer->shouldReceive('resolve')
        ->once()
        ->andReturn(app(TeamMailer::class)->resolve($run->automation->team));
    $teamMailer->shouldReceive('sendResolved')
        ->once()
        ->andThrow(EmailTransportException::fromTransport(new TransportException('Maximum sending rate exceeded.')));

    expect(fn () => (new SendAutomationEmailDelivery($delivery->id))->handle($teamMailer, app(AdvanceAutomationRun::class)))
        ->toThrow(EmailTransportException::class, 'The provider is throttling sends. Try again in a moment.');

    $run->refresh();

    expect($delivery->refresh()->status)->toBe(EmailDeliveryStatus::Queued)
        ->and($delivery->send_attempted_at)->toBeNull()
        ->and($run->steps()->where('node_id', 'send')->value('status'))->toBe('running')
        ->and($run->status)->toBe(AutomationRunStatus::Running)
        ->and($run->failure_reason)->toBeNull()
        ->and(EmailAddressHealth::query()->count())->toBe(0);
});

test('an automation email that runs out of retries fails its run', function (Throwable $exception, string $reason, int $healthFailures) {
    Queue::fake([SendAutomationEmailDelivery::class, ProcessAutomationRun::class]);
    Mail::fake();

    [$run, $delivery] = queuedAutomationSend();
    $run->steps()->create(['node_id' => 'other', 'status' => 'pending']);

    // A late second report of the same failure changes nothing.
    (new SendAutomationEmailDelivery($delivery->id))->failed($exception);
    (new SendAutomationEmailDelivery($delivery->id))->failed($exception);

    $run->refresh();

    expect($delivery->refresh()->status)->toBe(EmailDeliveryStatus::Failed)
        ->and($delivery->failure_reason)->toBe($reason)
        ->and($run->steps()->where('node_id', 'send')->value('status'))->toBe('failed')
        ->and($run->steps()->where('node_id', 'send')->value('result'))->toBe(['error' => $reason])
        ->and($run->steps()->where('node_id', 'other')->value('status'))->toBe('cancelled')
        ->and($run->status)->toBe(AutomationRunStatus::Failed)
        ->and($run->failure_reason)->toBe($reason)
        ->and(EmailAddressHealth::query()->where('failure_count', 1)->count())->toBe($healthFailures);
})->with([
    'the provider refused it' => [fn () => new EmailTransportException, EmailTransportException::MESSAGE, 1],
    'the workspace connection was unusable' => [fn () => EmailTransportException::providerUnavailable(), EmailTransportException::MESSAGE, 0],
    'it waited out its retries' => [fn () => new MaxAttemptsExceededException('x'), 'Delivery failed.', 0],
]);

test('a queued email is not sent once its run has ended', function () {
    Queue::fake([SendAutomationEmailDelivery::class, ProcessAutomationRun::class]);
    Mail::fake();

    [$run, $delivery] = queuedAutomationSend();
    (new ProcessAutomationRun($run->id, 'other'))->failed(new RuntimeException('Sibling failed.'));

    app()->call([new SendAutomationEmailDelivery($delivery->id), 'handle']);

    Mail::assertNothingSent();
    expect($delivery->refresh()->status)->toBe(EmailDeliveryStatus::Cancelled);
});

test('pausing the automation hands a queued email back', function () {
    Queue::fake([SendAutomationEmailDelivery::class, ProcessAutomationRun::class]);
    Mail::fake();

    [$run, $delivery] = queuedAutomationSend();
    $run->automation->update(['status' => AutomationStatus::Paused]);

    app()->call([new SendAutomationEmailDelivery($delivery->id), 'handle']);

    Mail::assertNothingSent();
    expect($delivery->refresh()->status)->toBe(EmailDeliveryStatus::Cancelled)
        ->and($run->steps()->where('node_id', 'send')->value('status'))->toBe('pending')
        ->and($run->refresh()->status)->toBe(AutomationRunStatus::Pending);
});

test('a subscriber who opted out while the email waited is skipped', function (Closure $optOut, EmailDeliveryStatus $status, string $reason) {
    Queue::fake([SendAutomationEmailDelivery::class, ProcessAutomationRun::class]);
    Mail::fake();

    [$run, $delivery] = queuedAutomationSend();
    $optOut($run);

    app()->call([new SendAutomationEmailDelivery($delivery->id), 'handle']);

    Mail::assertNothingSent();
    expect($delivery->refresh()->status)->toBe($status)
        ->and($run->steps()->where('node_id', 'send')->value('status'))->toBe('skipped')
        ->and($run->steps()->where('node_id', 'send')->value('result'))->toBe(['reason' => $reason])
        ->and($run->refresh()->status)->toBe(AutomationRunStatus::Completed);
})->with([
    'unsubscribed' => [
        fn (AutomationRun $run) => $run->subscriber->update(['status' => SubscriberStatus::Unsubscribed, 'unsubscribed_at' => now()]),
        EmailDeliveryStatus::Cancelled,
        'unsubscribed',
    ],
    'suppressed' => [
        fn (AutomationRun $run) => EmailAddressHealth::factory()->for($run->automation->team)->suppressed()->create([
            'email' => $run->subscriber->email,
            'reason' => EmailAddressHealthReason::PermanentBounce,
        ]),
        EmailDeliveryStatus::Rejected,
        'suppressed',
    ],
]);

test('an email unpublished while it waited fails the run without sending', function () {
    Queue::fake([SendAutomationEmailDelivery::class, ProcessAutomationRun::class]);
    Mail::fake();

    [$run, $delivery] = queuedAutomationSend();
    $delivery->transactionalEmail->update(['status' => TransactionalEmailStatus::Draft]);

    app()->call([new SendAutomationEmailDelivery($delivery->id), 'handle']);

    Mail::assertNothingSent();
    expect($delivery->refresh()->status)->toBe(EmailDeliveryStatus::Failed)
        ->and($run->refresh()->status)->toBe(AutomationRunStatus::Failed)
        ->and($run->failure_reason)->toBe('The transactional email is no longer published.');
});

test('a job that loses its email to a duplicate leaves the step to that duplicate', function (Closure $arrange) {
    Queue::fake([SendAutomationEmailDelivery::class, ProcessAutomationRun::class]);
    Mail::fake();

    [$run, $delivery] = queuedAutomationSend();
    $arrange($run, $delivery);

    // A duplicate job claims the delivery right after this one has read it.
    $duplicateClaimed = false;
    AutomationEmailDelivery::retrieved(function (AutomationEmailDelivery $retrieved) use (&$duplicateClaimed, $delivery): void {
        if ($duplicateClaimed || $retrieved->id !== $delivery->id) {
            return;
        }

        $duplicateClaimed = true;
        AutomationEmailDelivery::query()->whereKey($delivery->id)->update([
            'status' => EmailDeliveryStatus::Sending,
            'send_attempted_at' => now(),
        ]);
    });

    app()->call([new SendAutomationEmailDelivery($delivery->id), 'handle']);

    expect($delivery->refresh()->status)->toBe(EmailDeliveryStatus::Sending)
        ->and($run->steps()->where('node_id', 'send')->value('status'))->toBe('running')
        ->and($run->refresh()->status)->toBe(AutomationRunStatus::Running)
        ->and($run->failure_reason)->toBeNull();

    // Activating the automation again finds nothing to send a second time.
    $run->automation->update(['status' => AutomationStatus::Active]);
    app(AdvanceAutomationRun::class)->handle($run, 'send');

    Mail::assertNothingSent();
    expect(AutomationEmailDelivery::query()->count())->toBe(1);
})->with([
    'the automation was paused' => [
        fn (AutomationRun $run) => $run->automation->update(['status' => AutomationStatus::Paused]),
    ],
    'the subscriber unsubscribed' => [
        fn (AutomationRun $run) => $run->subscriber->update(['status' => SubscriberStatus::Unsubscribed, 'unsubscribed_at' => now()]),
    ],
    'the email was unpublished' => [
        fn (AutomationRun $run, AutomationEmailDelivery $delivery) => $delivery->transactionalEmail->update(['status' => TransactionalEmailStatus::Draft]),
    ],
]);

test('a database error while the email waits is retried instead of failing the run', function () {
    Queue::fake([SendAutomationEmailDelivery::class, ProcessAutomationRun::class]);
    Mail::fake();

    [$run, $delivery] = queuedAutomationSend();
    $this->mock(RecordEmailAddressHealth::class, fn (MockInterface $mock) => $mock
        ->shouldReceive('isSuppressed')
        ->andThrow(new QueryException('sqlite', 'select 1', [], new PDOException('database is locked'))));

    expect(fn () => app()->call([new SendAutomationEmailDelivery($delivery->id), 'handle']))
        ->toThrow(QueryException::class);

    Mail::assertNothingSent();
    expect($delivery->refresh()->status)->toBe(EmailDeliveryStatus::Queued)
        ->and($delivery->failure_reason)->toBeNull()
        ->and($run->steps()->where('node_id', 'send')->value('status'))->toBe('running')
        ->and($run->refresh()->status)->toBe(AutomationRunStatus::Running)
        ->and($run->failure_reason)->toBeNull();
});

test('a retried job finishes the step of an email that already went out', function () {
    Queue::fake([SendAutomationEmailDelivery::class, ProcessAutomationRun::class]);
    Mail::fake();

    [$run, $delivery] = queuedAutomationSend();

    // The worker stopped between recording the send and completing the step.
    $delivery->update(['status' => EmailDeliveryStatus::Sent, 'send_attempted_at' => now(), 'sent_at' => now()]);

    app()->call([new SendAutomationEmailDelivery($delivery->id), 'handle']);

    Mail::assertNothingSent();
    expect($run->steps()->where('node_id', 'send')->value('status'))->toBe('completed')
        ->and($run->steps()->where('node_id', 'send')->value('result'))->toBe(['transactional_email_uuid' => $delivery->transactionalEmail->uuid])
        ->and($run->refresh()->status)->toBe(AutomationRunStatus::Completed);
});

test('a branch that fails while the email is in flight keeps the step it cancelled', function () {
    Queue::fake([SendAutomationEmailDelivery::class, ProcessAutomationRun::class]);
    Mail::fake();

    [$run, $delivery] = queuedAutomationSend();

    $teamMailer = Mockery::mock(TeamMailer::class);
    $teamMailer->shouldReceive('resolve')
        ->once()
        ->andReturn(app(TeamMailer::class)->resolve($run->automation->team));
    $teamMailer->shouldReceive('sendResolved')
        ->once()
        ->andReturnUsing(function () use ($run): null {
            (new ProcessAutomationRun($run->id, 'other'))->failed(new RuntimeException('Sibling failed.'));

            return null;
        });

    (new SendAutomationEmailDelivery($delivery->id))->handle($teamMailer, app(AdvanceAutomationRun::class));

    expect($delivery->refresh()->status)->toBe(EmailDeliveryStatus::Sent)
        ->and($run->steps()->where('node_id', 'send')->value('status'))->toBe('cancelled')
        ->and($run->refresh()->status)->toBe(AutomationRunStatus::Failed)
        ->and($run->failure_reason)->toBe('Sibling failed.');
});
