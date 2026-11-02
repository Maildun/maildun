<?php

use App\Actions\Emails\StartEmailSend;
use App\Enums\EmailStatus;
use App\Enums\TeamRole;
use App\Models\Audience;
use App\Models\Email;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\mock;

function schedulableCampaign(Team $team, array $attributes = []): Email
{
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->create();

    return Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'subject' => 'Hello',
        'html' => '<p>Hi</p>',
        ...$attributes,
    ]);
}

test('a ready draft can be scheduled for a later time', function () {
    $user = User::factory()->create();
    TeamEmailIntegration::factory()->for($user->currentTeam)->smtp()->create();
    $email = schedulableCampaign($user->currentTeam);
    $sendAt = now()->addDay()->startOfMinute();

    $this->actingAs($user)
        ->post(route('emails.schedule', [$user->currentTeam, $email]), [
            'scheduled_at' => $sendAt->toISOString(),
        ])
        ->assertRedirect(route('emails.edit', [$user->currentTeam, $email]));

    expect($email->fresh())
        ->status->toBe(EmailStatus::Draft)
        ->scheduled_at->toISOString()->toBe($sendAt->toISOString());
});

test('scheduling runs the same readiness checks as sending now', function () {
    $user = User::factory()->create();
    $email = schedulableCampaign($user->currentTeam);

    $this->actingAs($user)
        ->post(route('emails.schedule', [$user->currentTeam, $email]), [
            'scheduled_at' => now()->addDay()->toISOString(),
        ])
        ->assertSessionHasErrors('scheduled_at');

    expect($email->fresh()->scheduled_at)->toBeNull();
});

test('a schedule must be in the future', function () {
    $user = User::factory()->create();
    TeamEmailIntegration::factory()->for($user->currentTeam)->smtp()->create();
    $email = schedulableCampaign($user->currentTeam);

    $this->actingAs($user)
        ->post(route('emails.schedule', [$user->currentTeam, $email]), [
            'scheduled_at' => now()->subHour()->toISOString(),
        ])
        ->assertSessionHasErrors('scheduled_at');
});

test('cancelling a schedule returns the campaign to a plain draft', function () {
    $user = User::factory()->create();
    $email = schedulableCampaign($user->currentTeam, ['scheduled_at' => now()->addDay()]);

    $this->actingAs($user)
        ->delete(route('emails.unschedule', [$user->currentTeam, $email]))
        ->assertRedirect();

    expect($email->fresh()->scheduled_at)->toBeNull();
});

test('the scheduler starts due campaigns once and leaves future ones alone', function () {
    Bus::fake();
    $team = Team::factory()->create();
    TeamEmailIntegration::factory()->for($team)->smtp()->create();
    $due = schedulableCampaign($team, ['scheduled_at' => now()->subMinute()]);
    $later = schedulableCampaign($team, ['scheduled_at' => now()->addHour()]);

    $this->artisan('emails:send-scheduled')->assertSuccessful();
    $this->artisan('emails:send-scheduled')->assertSuccessful();

    expect($due->fresh())
        ->status->toBe(EmailStatus::Queued)
        ->scheduled_at->toBeNull()
        ->and($later->fresh())
        ->status->toBe(EmailStatus::Draft)
        ->scheduled_at->not->toBeNull();
    Bus::assertBatchCount(1);
});

test('the scheduler starts every due campaign when more are due than fit in one page', function () {
    $team = Team::factory()->create();
    Email::factory()->for($team)->count(1005)->create(['scheduled_at' => now()->subMinute()]);
    mock(StartEmailSend::class)->shouldReceive('handle')->times(1005);

    $this->artisan('emails:send-scheduled')->assertSuccessful();

    expect(Email::query()->whereNotNull('scheduled_at')->count())->toBe(0);
});

test('a scheduled send that cannot start keeps the reason on the draft', function () {
    Bus::fake();
    $team = Team::factory()->create();
    $email = schedulableCampaign($team, ['scheduled_at' => now()->subMinute()]);

    $this->artisan('emails:send-scheduled')->assertSuccessful();

    expect($email->fresh())
        ->status->toBe(EmailStatus::Draft)
        ->scheduled_at->toBeNull()
        ->schedule_error->toBe('Connect an email provider before sending campaigns.');
    Bus::assertNothingBatched();
});

test('sending now clears a pending schedule', function () {
    Bus::fake();
    $user = User::factory()->create();
    TeamEmailIntegration::factory()->for($user->currentTeam)->smtp()->create();
    $email = schedulableCampaign($user->currentTeam, [
        'scheduled_at' => now()->addDay(),
        'schedule_error' => 'Old problem.',
    ]);

    $this->actingAs($user)->post(route('emails.send', [$user->currentTeam, $email]));

    expect($email->fresh())
        ->status->toBe(EmailStatus::Queued)
        ->scheduled_at->toBeNull()
        ->schedule_error->toBeNull();
});

test('the campaign list shows scheduled drafts and filters them', function () {
    $user = User::factory()->create();
    $scheduled = schedulableCampaign($user->currentTeam, ['scheduled_at' => now()->addDay()]);
    schedulableCampaign($user->currentTeam);

    $this->actingAs($user)
        ->get(route('emails.index', [$user->currentTeam, 'status' => 'scheduled']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('emails.data', 1)
            ->where('emails.data.0.uuid', $scheduled->uuid)
            ->where('emails.data.0.status', 'scheduled'));

    $this->actingAs($user)
        ->get(route('emails.index', [$user->currentTeam, 'status' => 'draft']))
        ->assertInertia(fn (Assert $page) => $page->has('emails.data', 1)
            ->whereNot('emails.data.0.uuid', $scheduled->uuid));
});

test('a scheduled campaign waits for its time and then starts exactly once', function () {
    Bus::fake();
    $this->travelTo('2026-10-23 09:00:00');
    $user = User::factory()->create();
    TeamEmailIntegration::factory()->for($user->currentTeam)->create();
    $email = schedulableCampaign($user->currentTeam);

    $this->actingAs($user)
        ->post(route('emails.schedule', [$user->currentTeam, $email]), [
            'scheduled_at' => '2026-10-23T09:30:00+00:00',
        ])
        ->assertSessionHasNoErrors();

    $this->travelTo('2026-10-23 09:29:00');
    $this->artisan('emails:send-scheduled')->assertSuccessful();

    expect($email->fresh()->status)->toBe(EmailStatus::Draft);
    Bus::assertNothingBatched();

    $this->travelTo('2026-10-23 09:30:00');
    $this->artisan('emails:send-scheduled')->assertSuccessful();
    $this->artisan('emails:send-scheduled')->assertSuccessful();

    expect($email->fresh())
        ->status->toBe(EmailStatus::Queued)
        ->scheduled_at->toBeNull();
    Bus::assertBatchCount(1);
});

test('a schedule is stored in UTC whatever timezone it was picked in', function () {
    $user = User::factory()->create();
    TeamEmailIntegration::factory()->for($user->currentTeam)->create();
    $email = schedulableCampaign($user->currentTeam);
    $sendAt = now()->addDay()->startOfMinute();

    $this->actingAs($user)
        ->post(route('emails.schedule', [$user->currentTeam, $email]), [
            'scheduled_at' => $sendAt->copy()->setTimezone('Asia/Jakarta')->toIso8601String(),
        ])
        ->assertSessionHasNoErrors();

    expect($email->fresh()->scheduled_at->toISOString())->toBe($sendAt->toISOString());
});

test('a schedule must be within the next year', function () {
    $user = User::factory()->create();
    TeamEmailIntegration::factory()->for($user->currentTeam)->create();
    $email = schedulableCampaign($user->currentTeam);

    $this->actingAs($user)
        ->post(route('emails.schedule', [$user->currentTeam, $email]), [
            'scheduled_at' => now()->addYear()->addDay()->toISOString(),
        ])
        ->assertSessionHasErrors(['scheduled_at' => 'Pick a time within the next year.']);

    expect($email->fresh()->scheduled_at)->toBeNull();
});

test('a scheduled draft can be moved to a new time', function () {
    $user = User::factory()->create();
    TeamEmailIntegration::factory()->for($user->currentTeam)->create();
    $email = schedulableCampaign($user->currentTeam, [
        'scheduled_at' => now()->addDay(),
        'schedule_error' => 'Old problem.',
    ]);
    $newTime = now()->addDays(3)->startOfMinute();

    $this->actingAs($user)
        ->post(route('emails.schedule', [$user->currentTeam, $email]), [
            'scheduled_at' => $newTime->toISOString(),
        ])
        ->assertSessionHasNoErrors();

    expect($email->fresh())
        ->scheduled_at->toISOString()->toBe($newTime->toISOString())
        ->schedule_error->toBeNull();
});

test('only a draft can be scheduled', function () {
    $user = User::factory()->create();
    TeamEmailIntegration::factory()->for($user->currentTeam)->create();
    $email = schedulableCampaign($user->currentTeam, ['status' => EmailStatus::Sending]);

    $this->actingAs($user)
        ->post(route('emails.schedule', [$user->currentTeam, $email]), [
            'scheduled_at' => now()->addDay()->toISOString(),
        ])
        ->assertSessionHasErrors(['scheduled_at' => 'Only a draft campaign can be scheduled.']);

    expect($email->fresh())
        ->status->toBe(EmailStatus::Sending)
        ->scheduled_at->toBeNull();
});

test('a cancelled schedule never fires', function () {
    Bus::fake();
    $user = User::factory()->create();
    TeamEmailIntegration::factory()->for($user->currentTeam)->create();
    $email = schedulableCampaign($user->currentTeam, ['scheduled_at' => now()->addMinutes(5)]);

    $this->actingAs($user)
        ->delete(route('emails.unschedule', [$user->currentTeam, $email]))
        ->assertRedirect();

    $this->travel(10)->minutes();
    $this->artisan('emails:send-scheduled')->assertSuccessful();

    expect($email->fresh()->status)->toBe(EmailStatus::Draft);
    Bus::assertNothingBatched();
});

test('members who cannot send campaigns cannot schedule or unschedule them', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = $owner->currentTeam;
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    TeamEmailIntegration::factory()->for($team)->create();
    $scheduled = schedulableCampaign($team, ['scheduled_at' => now()->addDay()]);
    $draft = schedulableCampaign($team);

    $this->actingAs($member)
        ->post(route('emails.schedule', [$team, $draft]), [
            'scheduled_at' => now()->addDay()->toISOString(),
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('emails.unschedule', [$team, $scheduled]))
        ->assertForbidden();

    expect($draft->fresh()->scheduled_at)->toBeNull()
        ->and($scheduled->fresh()->scheduled_at)->not->toBeNull();
});
