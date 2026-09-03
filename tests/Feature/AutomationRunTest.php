<?php

use App\Actions\Automations\AdvanceAutomationRun;
use App\Actions\Automations\StartAutomationRun;
use App\Enums\AutomationAction;
use App\Enums\AutomationCondition;
use App\Enums\AutomationDelayUnit;
use App\Enums\AutomationRunStatus;
use App\Enums\SubscriberSource;
use App\Jobs\ProcessAutomationRun;
use App\Mail\AutomationEmail;
use App\Models\Audience;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\TeamEmailIntegration;
use App\Models\TransactionalEmail;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

// The engine hands the next step to the queue. Faking it keeps each test to the
// single step under assertion instead of walking the whole graph inline.
beforeEach(fn () => Queue::fake());

/**
 * @param  list<array<string, mixed>>  $nodes
 * @param  list<array<string, mixed>>  $edges
 */
function automationRun(array $nodes, array $edges, ?Subscriber $subscriber = null, ?Automation $automation = null): AutomationRun
{
    $automation ??= Automation::factory()->active()->create();
    $subscriber ??= Subscriber::factory()->for(Audience::factory()->for($automation->team))->create();

    $graph = [
        'nodes' => [
            ['id' => 'trigger', 'type' => 'trigger', 'data' => ['kind' => 'subscribed']],
            ...$nodes,
        ],
        'edges' => $edges,
    ];

    return $automation->runs()->create([
        'subscriber_id' => $subscriber->id,
        'status' => AutomationRunStatus::Pending,
        'graph' => $graph,
        'current_node_id' => $nodes[0]['id'],
    ]);
}

test('a condition only follows the branch it took', function () {
    // "no" is deliberately unwired: the run must end, not fall through to "yes".
    $run = automationRun(
        nodes: [
            ['id' => 'check', 'type' => 'condition', 'data' => ['kind' => AutomationCondition::HasTag->value, 'tag_uuid' => 'missing-tag']],
            ['id' => 'tagged', 'type' => 'action', 'data' => ['kind' => AutomationAction::AddTag->value, 'tag_uuid' => 'never']],
        ],
        edges: [
            ['source' => 'trigger', 'target' => 'check'],
            ['source' => 'check', 'sourceHandle' => 'yes', 'target' => 'tagged'],
        ],
    );

    app(AdvanceAutomationRun::class)->handle($run);

    $run->refresh();

    expect($run->status)->toBe(AutomationRunStatus::Completed)
        ->and($run->current_node_id)->toBeNull()
        ->and($run->steps()->where('node_id', 'check')->value('result'))->toBe(['matched' => false])
        ->and($run->steps()->where('node_id', 'tagged')->exists())->toBeFalse();
});

test('a condition follows the matching branch when it is wired', function () {
    $automation = Automation::factory()->active()->create();
    $tag = Tag::factory()->for($automation->team)->create();
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($automation->team))->create();
    $subscriber->tags()->attach($tag->id);

    $run = automationRun(
        nodes: [
            ['id' => 'check', 'type' => 'condition', 'data' => ['kind' => AutomationCondition::HasTag->value, 'tag_uuid' => $tag->uuid]],
            ['id' => 'next', 'type' => 'action', 'data' => ['kind' => AutomationAction::RemoveTag->value, 'tag_uuid' => $tag->uuid]],
        ],
        edges: [
            ['source' => 'trigger', 'target' => 'check'],
            ['source' => 'check', 'sourceHandle' => 'yes', 'target' => 'next'],
        ],
        subscriber: $subscriber,
        automation: $automation,
    );

    app(AdvanceAutomationRun::class)->handle($run);

    $run->refresh();

    expect($run->status)->toBe(AutomationRunStatus::Pending)
        ->and($run->current_node_id)->toBe('next')
        ->and($run->steps()->where('node_id', 'check')->value('result'))->toBe(['matched' => true]);
});

test('a source condition follows the branch matching the subscriber source', function (SubscriberSource $selectedSource, SubscriberSource $subscriberSource, bool $matched) {
    $automation = Automation::factory()->active()->create();
    $subscriber = Subscriber::factory()
        ->for(Audience::factory()->for($automation->team))
        ->create(['source' => $subscriberSource]);

    $run = automationRun(
        nodes: [
            ['id' => 'check', 'type' => 'condition', 'data' => [
                'kind' => AutomationCondition::Source->value,
                'source' => $selectedSource->value,
            ]],
        ],
        edges: [['source' => 'trigger', 'target' => 'check']],
        subscriber: $subscriber,
        automation: $automation,
    );

    app(AdvanceAutomationRun::class)->handle($run);

    expect($run->refresh()->steps()->where('node_id', 'check')->value('result'))
        ->toBe(['matched' => $matched]);
})->with([
    'manual subscriber matches Manual' => [SubscriberSource::Manual, SubscriberSource::Manual, true],
    'form subscriber matches Form' => [SubscriberSource::Form, SubscriberSource::Form, true],
    'form subscriber does not match Manual' => [SubscriberSource::Manual, SubscriberSource::Form, false],
    'api subscriber does not match Form' => [SubscriberSource::Form, SubscriberSource::Api, false],
]);

test('a retried send does not deliver the email twice', function () {
    Mail::fake();

    $automation = Automation::factory()->active()->create();
    TeamEmailIntegration::factory()->for($automation->team)->ses()->create();
    $email = TransactionalEmail::factory()->for($automation->team)->create();
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($automation->team))->create();

    $run = automationRun(
        nodes: [['id' => 'send', 'type' => 'action', 'data' => [
            'kind' => AutomationAction::SendEmail->value,
            'transactional_email_uuid' => $email->uuid,
        ]]],
        edges: [['source' => 'trigger', 'target' => 'send']],
        subscriber: $subscriber,
        automation: $automation,
    );

    app(AdvanceAutomationRun::class)->handle($run);

    // A retry lands on the same node after the transport already accepted it.
    $run->refresh()->update(['status' => AutomationRunStatus::Pending, 'current_node_id' => 'send']);
    app(AdvanceAutomationRun::class)->handle($run);

    Mail::assertSentCount(1);
    Mail::assertSent(AutomationEmail::class, fn (AutomationEmail $mail): bool => $mail->hasTo($subscriber->email));

    expect($run->refresh()->steps()->where('node_id', 'send')->value('result'))
        ->toBe(['transactional_email_uuid' => $email->uuid]);
});

test('only one worker can advance a run', function () {
    Mail::fake();

    $automation = Automation::factory()->active()->create();
    TeamEmailIntegration::factory()->for($automation->team)->ses()->create();
    $email = TransactionalEmail::factory()->for($automation->team)->create();

    $run = automationRun(
        nodes: [['id' => 'send', 'type' => 'action', 'data' => [
            'kind' => AutomationAction::SendEmail->value,
            'transactional_email_uuid' => $email->uuid,
        ]]],
        edges: [['source' => 'trigger', 'target' => 'send']],
        automation: $automation,
    );

    // Both workers hold the same pre-claim snapshot of the run.
    $first = AutomationRun::query()->findOrFail($run->id);
    $second = AutomationRun::query()->findOrFail($run->id);

    app(AdvanceAutomationRun::class)->handle($first);
    app(AdvanceAutomationRun::class)->handle($second);

    Mail::assertSentCount(1);

    expect($run->refresh()->steps()->where('node_id', 'send')->count())->toBe(1);
});

test('an unsubscribed subscriber skips the send but keeps walking the graph', function () {
    Mail::fake();

    $automation = Automation::factory()->active()->create();
    $email = TransactionalEmail::factory()->for($automation->team)->create();
    $subscriber = Subscriber::factory()
        ->for(Audience::factory()->for($automation->team))
        ->unsubscribed()
        ->create();

    $run = automationRun(
        nodes: [
            ['id' => 'send', 'type' => 'action', 'data' => [
                'kind' => AutomationAction::SendEmail->value,
                'transactional_email_uuid' => $email->uuid,
            ]],
            ['id' => 'after', 'type' => 'action', 'data' => ['kind' => AutomationAction::AddTag->value, 'tag_uuid' => 'x']],
        ],
        edges: [
            ['source' => 'trigger', 'target' => 'send'],
            ['source' => 'send', 'target' => 'after'],
        ],
        subscriber: $subscriber,
        automation: $automation,
    );

    app(AdvanceAutomationRun::class)->handle($run);

    Mail::assertNothingSent();

    expect($run->refresh()->current_node_id)->toBe('after')
        ->and($run->steps()->where('node_id', 'send')->value('result'))->toBe(['reason' => 'unsubscribed']);
});

test('a delay parks the run until its scheduled time', function () {
    $run = automationRun(
        nodes: [
            ['id' => 'wait', 'type' => 'delay', 'data' => ['amount' => 2, 'unit' => AutomationDelayUnit::Hours->value]],
            ['id' => 'after', 'type' => 'action', 'data' => ['kind' => AutomationAction::AddTag->value, 'tag_uuid' => 'x']],
        ],
        edges: [
            ['source' => 'trigger', 'target' => 'wait'],
            ['source' => 'wait', 'target' => 'after'],
        ],
    );

    app(AdvanceAutomationRun::class)->handle($run);

    $run->refresh();

    expect($run->status)->toBe(AutomationRunStatus::Waiting)
        ->and($run->current_node_id)->toBe('wait')
        ->and($run->scheduled_at?->diffInMinutes(now()->addHours(2)))->toBeLessThan(1);

    Queue::assertPushed(ProcessAutomationRun::class);

    // Still early: the run stays parked and the step is logged as waiting.
    app(AdvanceAutomationRun::class)->handle($run);

    expect($run->refresh()->status)->toBe(AutomationRunStatus::Waiting)
        ->and($run->steps()->where('node_id', 'wait')->value('status'))->toBe('waiting');

    $this->travelTo(now()->addHours(3));

    app(AdvanceAutomationRun::class)->handle($run);

    expect($run->refresh()->current_node_id)->toBe('after')
        ->and($run->steps()->where('node_id', 'wait')->value('status'))->toBe('completed');
});

test('a run is cancelled when its automation is deleted', function () {
    $user = User::factory()->create();
    $automation = Automation::factory()->active()->for($user->currentTeam)->create();

    $run = automationRun(
        nodes: [['id' => 'after', 'type' => 'action', 'data' => ['kind' => AutomationAction::AddTag->value, 'tag_uuid' => 'x']]],
        edges: [['source' => 'trigger', 'target' => 'after']],
        automation: $automation,
    );

    $automation->delete();

    app(AdvanceAutomationRun::class)->handle($run);

    $run->refresh();

    expect($run->status)->toBe(AutomationRunStatus::Cancelled)
        ->and($run->failure_reason)->toBe('The automation was deleted.');
});

test('starting a run logs the trigger step and refuses a second open run', function () {
    $automation = Automation::factory()->active()->create([
        'graph' => [
            'nodes' => [
                ['id' => 'trigger', 'type' => 'trigger', 'data' => ['kind' => 'subscribed']],
                ['id' => 'tag', 'type' => 'action', 'data' => ['kind' => AutomationAction::AddTag->value, 'tag_uuid' => 'x']],
            ],
            'edges' => [['source' => 'trigger', 'target' => 'tag']],
        ],
    ]);
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($automation->team))->create();

    $run = app(StartAutomationRun::class)->handle($automation, $subscriber);

    expect($run)->not->toBeNull()
        ->and($run->current_node_id)->toBe('tag')
        ->and($run->steps()->where('node_id', 'trigger')->value('status'))->toBe('completed');

    expect(app(StartAutomationRun::class)->handle($automation, $subscriber))->toBeNull()
        ->and($automation->runs()->count())->toBe(1);

    Queue::assertPushed(ProcessAutomationRun::class, 1);
});

test('all outgoing branches execute independently and the run completes after the last branch', function () {
    $automation = Automation::factory()->active()->create();
    $newsletter = Tag::factory()->for($automation->team)->create();
    $engaged = Tag::factory()->for($automation->team)->create();
    $delayed = Tag::factory()->for($automation->team)->create();
    $subscriber = Subscriber::factory()
        ->for(Audience::factory()->for($automation->team))
        ->create(['source' => SubscriberSource::Form]);

    $automation->update(['graph' => [
        'nodes' => [
            ['id' => 'trigger', 'type' => 'trigger', 'data' => ['kind' => 'subscribed']],
            ['id' => 'newsletter', 'type' => 'action', 'data' => ['kind' => AutomationAction::AddTag->value, 'tag_uuid' => $newsletter->uuid]],
            ['id' => 'wait', 'type' => 'delay', 'data' => ['amount' => 1, 'unit' => AutomationDelayUnit::Hours->value]],
            ['id' => 'delayed', 'type' => 'action', 'data' => ['kind' => AutomationAction::AddTag->value, 'tag_uuid' => $delayed->uuid]],
            ['id' => 'source', 'type' => 'condition', 'data' => ['kind' => AutomationCondition::Source->value, 'source' => SubscriberSource::Form->value]],
            ['id' => 'engaged', 'type' => 'action', 'data' => ['kind' => AutomationAction::AddTag->value, 'tag_uuid' => $engaged->uuid]],
        ],
        'edges' => [
            ['source' => 'trigger', 'target' => 'newsletter'],
            ['source' => 'trigger', 'target' => 'wait'],
            ['source' => 'trigger', 'target' => 'source'],
            ['source' => 'wait', 'target' => 'delayed'],
            ['source' => 'source', 'sourceHandle' => 'yes', 'target' => 'engaged'],
        ],
    ]]);

    $run = app(StartAutomationRun::class)->handle($automation, $subscriber);

    expect($run)->not->toBeNull()
        ->and($run->steps()->where('status', 'pending')->pluck('node_id')->all())
        ->toBe(['newsletter', 'wait', 'source']);
    Queue::assertPushed(ProcessAutomationRun::class, 3);

    app(AdvanceAutomationRun::class)->handle($run, 'newsletter');
    app(AdvanceAutomationRun::class)->handle($run, 'wait');

    expect($subscriber->tags()->whereKey($newsletter->id)->exists())->toBeTrue()
        ->and($run->refresh()->status)->toBe(AutomationRunStatus::Pending)
        ->and($run->steps()->where('node_id', 'wait')->value('status'))->toBe('waiting');

    app(AdvanceAutomationRun::class)->handle($run, 'source');
    app(AdvanceAutomationRun::class)->handle($run, 'engaged');

    expect($subscriber->tags()->whereKey($engaged->id)->exists())->toBeTrue()
        ->and($run->refresh()->status)->toBe(AutomationRunStatus::Waiting)
        ->and($run->scheduled_at)->not->toBeNull();

    $this->travelTo(now()->addHours(2));
    app(AdvanceAutomationRun::class)->handle($run, 'wait');
    app(AdvanceAutomationRun::class)->handle($run, 'delayed');

    expect($subscriber->tags()->whereKey($delayed->id)->exists())->toBeTrue()
        ->and($run->refresh()->status)->toBe(AutomationRunStatus::Completed)
        ->and($run->steps()->where('status', 'completed')->count())->toBe(6);
});

test('a queued branch job advances only its assigned node', function () {
    $automation = Automation::factory()->active()->create();
    $firstTag = Tag::factory()->for($automation->team)->create();
    $secondTag = Tag::factory()->for($automation->team)->create();
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($automation->team))->create();
    $automation->update(['graph' => [
        'nodes' => [
            ['id' => 'trigger', 'type' => 'trigger', 'data' => ['kind' => 'subscribed']],
            ['id' => 'first', 'type' => 'action', 'data' => ['kind' => AutomationAction::AddTag->value, 'tag_uuid' => $firstTag->uuid]],
            ['id' => 'second', 'type' => 'action', 'data' => ['kind' => AutomationAction::AddTag->value, 'tag_uuid' => $secondTag->uuid]],
        ],
        'edges' => [
            ['source' => 'trigger', 'target' => 'first'],
            ['source' => 'trigger', 'target' => 'second'],
        ],
    ]]);
    $run = app(StartAutomationRun::class)->handle($automation, $subscriber);

    (new ProcessAutomationRun($run->id, 'second'))->handle(app(AdvanceAutomationRun::class));

    expect($subscriber->tags()->whereKey($firstTag->id)->exists())->toBeFalse()
        ->and($subscriber->tags()->whereKey($secondTag->id)->exists())->toBeTrue()
        ->and($run->steps()->where('node_id', 'first')->value('status'))->toBe('pending')
        ->and($run->steps()->where('node_id', 'second')->value('status'))->toBe('completed')
        ->and($run->refresh()->status)->toBe(AutomationRunStatus::Pending);
});

test('a failed branch fails the run and cancels its siblings', function () {
    $automation = Automation::factory()->active()->create();
    $tag = Tag::factory()->for($automation->team)->create();
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($automation->team))->create();
    $automation->update(['graph' => [
        'nodes' => [
            ['id' => 'trigger', 'type' => 'trigger', 'data' => ['kind' => 'subscribed']],
            ['id' => 'broken', 'type' => 'action', 'data' => ['kind' => AutomationAction::AddTag->value, 'tag_uuid' => 'missing']],
            ['id' => 'sibling', 'type' => 'action', 'data' => ['kind' => AutomationAction::AddTag->value, 'tag_uuid' => $tag->uuid]],
        ],
        'edges' => [
            ['source' => 'trigger', 'target' => 'broken'],
            ['source' => 'trigger', 'target' => 'sibling'],
        ],
    ]]);

    $run = app(StartAutomationRun::class)->handle($automation, $subscriber);
    app(AdvanceAutomationRun::class)->handle($run, 'broken');
    app(AdvanceAutomationRun::class)->handle($run, 'sibling');

    expect($run->refresh()->status)->toBe(AutomationRunStatus::Failed)
        ->and($run->steps()->where('node_id', 'broken')->value('status'))->toBe('failed')
        ->and($run->steps()->where('node_id', 'sibling')->value('status'))->toBe('cancelled')
        ->and($subscriber->tags()->whereKey($tag->id)->exists())->toBeFalse();
});

test('the database refuses a second open run for the same subscriber', function () {
    $automation = Automation::factory()->active()->create();
    $subscriber = Subscriber::factory()->for(Audience::factory()->for($automation->team))->create();

    $automation->runs()->create([
        'subscriber_id' => $subscriber->id,
        'status' => AutomationRunStatus::Waiting,
    ]);

    // The partial index is what stops two workers racing past the check in
    // StartAutomationRun, so assert the constraint itself, not the guard.
    expect(fn () => $automation->runs()->create([
        'subscriber_id' => $subscriber->id,
        'status' => AutomationRunStatus::Pending,
    ]))->toThrow(UniqueConstraintViolationException::class);

    // A closed run for the same subscriber is still allowed.
    $automation->runs()->create([
        'subscriber_id' => $subscriber->id,
        'status' => AutomationRunStatus::Completed,
    ]);

    expect($automation->runs()->count())->toBe(2);
});

test('a job that exhausts its retries logs the failed step and the reason', function () {
    $run = automationRun(
        nodes: [['id' => 'send', 'type' => 'action', 'data' => ['kind' => AutomationAction::AddTag->value, 'tag_uuid' => 'x']]],
        edges: [['source' => 'trigger', 'target' => 'send']],
    );

    (new ProcessAutomationRun($run->id))->failed(new RuntimeException('The mailer refused the message.'));

    $run->refresh();

    expect($run->status)->toBe(AutomationRunStatus::Failed)
        ->and($run->failure_reason)->toBe('The mailer refused the message.')
        ->and($run->failed_at)->not->toBeNull()
        ->and($run->steps()->where('node_id', 'send')->value('status'))->toBe('failed')
        ->and($run->steps()->where('node_id', 'send')->value('result'))->toBe(['error' => 'The mailer refused the message.']);
});

test('a run whose current step vanished from the graph logs the failure', function () {
    $run = automationRun(
        nodes: [['id' => 'send', 'type' => 'action', 'data' => ['kind' => AutomationAction::AddTag->value, 'tag_uuid' => 'x']]],
        edges: [['source' => 'trigger', 'target' => 'send']],
    );

    $run->update(['current_node_id' => 'gone']);

    app(AdvanceAutomationRun::class)->handle($run);

    $run->refresh();

    expect($run->status)->toBe(AutomationRunStatus::Failed)
        ->and($run->steps()->where('node_id', 'gone')->value('status'))->toBe('failed');
});
