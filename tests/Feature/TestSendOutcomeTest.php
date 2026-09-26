<?php

use App\Enums\TestSendStatus;
use App\Exceptions\EmailTransportException;
use App\Jobs\SendCampaignTestEmail;
use App\Jobs\SendTransactionalEmailTest;
use App\Models\Email;
use App\Models\TransactionalEmail;
use App\Models\User;
use App\Services\TeamMailer;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\mock;

test('requesting a campaign test copy records it as queued for that address', function () {
    Queue::fake([SendCampaignTestEmail::class]);
    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create(['last_test_error' => 'old failure']);

    $this->actingAs($user)
        ->post(route('emails.test', [$user->currentTeam, $email]), ['to' => 'reviewer@example.com'])
        ->assertRedirect();

    expect($email->fresh())
        ->last_test_status->toBe(TestSendStatus::Queued)
        ->last_test_recipient->toBe('reviewer@example.com')
        ->last_test_error->toBeNull();
    Queue::assertPushed(SendCampaignTestEmail::class);
});

test('a delivered campaign test copy is recorded as sent', function () {
    $user = User::factory()->create();
    mock(TeamMailer::class)->shouldReceive('send')->once();
    $email = Email::factory()->for($user->currentTeam)->create([
        'last_test_status' => TestSendStatus::Queued,
        'last_test_recipient' => 'reviewer@example.com',
    ]);

    app()->call([new SendCampaignTestEmail($email->id, 'reviewer@example.com', 'Subject', '<p>Hi</p>', 'Hi'), 'handle']);

    expect($email->fresh())
        ->last_test_status->toBe(TestSendStatus::Sent)
        ->last_tested_at->not->toBeNull();
});

test('a test copy that exhausts its retries is recorded as failed with the reason', function (string $job, string $model) {
    $email = $model::factory()->create([
        'last_test_status' => TestSendStatus::Queued,
        'last_test_recipient' => 'reviewer@example.com',
    ]);
    $instance = $job === SendCampaignTestEmail::class
        ? new SendCampaignTestEmail($email->id, 'reviewer@example.com', 'Subject', '<p>Hi</p>', 'Hi')
        : new SendTransactionalEmailTest($email->id, 'reviewer@example.com', 'Subject', '<p>Hi</p>');

    $instance->failed(new EmailTransportException('The SMTP server refused the sender.'));

    expect($email->fresh())
        ->last_test_status->toBe(TestSendStatus::Failed)
        ->last_test_error->toBe('The SMTP server refused the sender.');
})->with([
    'campaign' => [SendCampaignTestEmail::class, Email::class],
    'transactional' => [SendTransactionalEmailTest::class, TransactionalEmail::class],
]);

test('a failure for an older test does not overwrite a newer test to another address', function () {
    $email = Email::factory()->create([
        'last_test_status' => TestSendStatus::Queued,
        'last_test_recipient' => 'newer@example.com',
    ]);

    (new SendCampaignTestEmail($email->id, 'older@example.com', 'Subject', '<p>Hi</p>', 'Hi'))
        ->failed(new RuntimeException('boom'));

    expect($email->fresh()->last_test_status)->toBe(TestSendStatus::Queued);
});

test('the transactional editor shows the last test outcome', function () {
    $user = User::factory()->create();
    $email = TransactionalEmail::factory()->for($user->currentTeam)->create([
        'last_test_status' => TestSendStatus::Failed,
        'last_test_recipient' => 'reviewer@example.com',
        'last_test_error' => 'The SMTP server refused the sender.',
    ]);

    $this->actingAs($user)
        ->get(route('transactional_emails.edit', [$user->currentTeam, $email]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('email.last_test.status', 'failed')
            ->where('email.last_test.error', 'The SMTP server refused the sender.'));
});
