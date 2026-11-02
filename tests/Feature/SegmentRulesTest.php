<?php

use App\Actions\Audiences\SyncSegmentSubscribers;
use App\Actions\Emails\RenderCampaignContent;
use App\Enums\SegmentMatchType;
use App\Enums\SubscriberStatus;
use App\Enums\TeamRole;
use App\Jobs\PrepareEmailSendChunk;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Email;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\User;
use App\Services\TeamMailer;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;

test('segments saved through the editor match every remaining text and custom field operator', function (string $field, string $operator, string $value, array $expected) {
    $user = User::factory()->create();
    $audience = Audience::factory()->for($user->currentTeam)->create();
    $plan = AudienceAttribute::factory()->for($audience)->create(['key' => 'plan']);
    $seats = AudienceAttribute::factory()->number()->for($audience)->create(['key' => 'seats']);
    Subscriber::factory()->for($audience)->create([
        'email' => 'ada@example.com',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'attribute_values' => ['plan' => 'Pro Annual', 'seats' => 5],
    ]);
    Subscriber::factory()->for($audience)->create([
        'email' => 'grace@example.net',
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'attribute_values' => ['plan' => 'Basic', 'seats' => '25'],
    ]);
    Subscriber::factory()->for($audience)->create([
        'email' => 'nobody@example.org',
        'first_name' => null,
        'last_name' => null,
    ]);
    $rule = match ($field) {
        'plan' => ['field' => 'attribute', 'attribute' => $plan->uuid],
        'seats' => ['field' => 'attribute', 'attribute' => $seats->uuid],
        default => ['field' => $field],
    };

    $this->actingAs($user)
        ->post(route('audiences.segments.store', [$audience->team, $audience]), [
            'name' => 'Operator check',
            'match_type' => 'all',
            'rules' => [[...$rule, 'operator' => $operator, 'value' => $value]],
        ])
        ->assertSessionHasNoErrors();

    $emails = Segment::query()->sole()->subscribers()
        ->orderBy('subscribers.email')
        ->pluck('subscribers.email')
        ->all();

    expect($emails)->toBe($expected);
})->with([
    'email is not' => ['email', 'not_equals', 'ADA@example.com', ['grace@example.net', 'nobody@example.org']],
    'email contains' => ['email', 'contains', '.NET', ['grace@example.net']],
    'email does not contain' => ['email', 'does_not_contain', 'example.net', ['ada@example.com', 'nobody@example.org']],
    'first name is' => ['first_name', 'equals', 'grace', ['grace@example.net']],
    'first name contains' => ['first_name', 'contains', 'AD', ['ada@example.com']],
    'first name does not contain' => ['first_name', 'does_not_contain', 'ad', ['grace@example.net', 'nobody@example.org']],
    'last name is' => ['last_name', 'equals', 'HOPPER', ['grace@example.net']],
    'last name is not' => ['last_name', 'not_equals', 'hopper', ['ada@example.com', 'nobody@example.org']],
    'text custom field does not contain' => ['plan', 'does_not_contain', 'annual', ['grace@example.net', 'nobody@example.org']],
    'number custom field is not' => ['seats', 'not_equals', '5', ['grace@example.net', 'nobody@example.org']],
]);

test('a match any segment saved through the editor keeps members who match one rule', function () {
    $user = User::factory()->create();
    $audience = Audience::factory()->for($user->currentTeam)->create();
    Subscriber::factory()->for($audience)->create(['email' => 'both@vip.test', 'language' => 'fr']);
    Subscriber::factory()->for($audience)->create(['email' => 'domain@vip.test', 'language' => 'en']);
    Subscriber::factory()->for($audience)->create(['email' => 'language@other.test', 'language' => 'fr']);
    Subscriber::factory()->for($audience)->create(['email' => 'neither@other.test', 'language' => 'en']);

    $this->actingAs($user)
        ->post(route('audiences.segments.store', [$audience->team, $audience]), [
            'name' => 'VIP or French',
            'match_type' => 'any',
            'rules' => [
                ['field' => 'email', 'operator' => 'contains', 'value' => '@vip.test'],
                ['field' => 'language', 'operator' => 'equals', 'value' => 'fr'],
            ],
        ])
        ->assertSessionHasNoErrors();

    $segment = Segment::query()->sole();

    expect($segment->match_type)->toBe(SegmentMatchType::Any)
        ->and($segment->subscribers()->orderBy('subscribers.email')->pluck('subscribers.email')->all())
        ->toBe(['both@vip.test', 'domain@vip.test', 'language@other.test']);
});

test('switching a segment from match all to match any resyncs its members', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->create(['email' => 'both@vip.test', 'language' => 'fr']);
    Subscriber::factory()->for($audience)->create(['email' => 'domain@vip.test', 'language' => 'en']);
    Subscriber::factory()->for($audience)->create(['email' => 'neither@other.test', 'language' => 'en']);
    $rules = [
        ['field' => 'email', 'operator' => 'contains', 'value' => '@vip.test'],
        ['field' => 'language', 'operator' => 'equals', 'value' => 'fr'],
    ];
    $segment = Segment::factory()->for($audience)->create(['match_type' => SegmentMatchType::All, 'rules' => $rules]);
    app(SyncSegmentSubscribers::class)->handle($segment);

    expect($segment->subscribers()->pluck('subscribers.email')->all())->toBe(['both@vip.test']);

    $this->actingAs($user)
        ->patch(route('audiences.segments.update', [$team, $audience, $segment]), [
            'name' => 'VIP or French',
            'description' => 'Either rule',
            'match_type' => 'any',
            'rules' => $rules,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $segment->refresh();

    expect($segment->only(['name', 'description']))->toBe(['name' => 'VIP or French', 'description' => 'Either rule'])
        ->and($segment->match_type)->toBe(SegmentMatchType::Any)
        ->and($segment->subscribers()->orderBy('subscribers.email')->pluck('subscribers.email')->all())
        ->toBe(['both@vip.test', 'domain@vip.test']);
});

test('a segment needs a name, a match type and at least one rule', function () {
    $user = User::factory()->create();
    $audience = Audience::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->post(route('audiences.segments.store', [$audience->team, $audience]), [])
        ->assertInvalid(['name', 'match_type', 'rules']);

    expect($audience->segments()->exists())->toBeFalse();
});

test('the segments sync command adds new matches and drops members who no longer match', function () {
    $audience = Audience::factory()->create();
    $leaving = Subscriber::factory()->for($audience)->create(['email' => 'leaving@example.com']);
    $staying = Subscriber::factory()->for($audience)->create(['email' => 'staying@example.com']);
    $segment = Segment::factory()->for($audience)->create([
        'rules' => [['field' => 'status', 'operator' => 'equals', 'value' => 'subscribed']],
    ]);
    app(SyncSegmentSubscribers::class)->handle($segment);
    $leaving->update(['status' => SubscriberStatus::Unsubscribed, 'unsubscribed_at' => now()]);
    $joining = Subscriber::factory()->for($audience)->create(['email' => 'joining@example.com']);

    expect($segment->subscribers()->pluck('subscribers.id')->all())
        ->toEqualCanonicalizing([$leaving->id, $staying->id]);

    $this->artisan('segments:sync')->assertSuccessful();

    expect($segment->subscribers()->pluck('subscribers.id')->all())
        ->toEqualCanonicalizing([$staying->id, $joining->id]);
});

test('a campaign to a segment only reaches the segment members', function () {
    Bus::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    TeamEmailIntegration::factory()->for($team)->create();
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->create(['email' => 'first@vip.test']);
    Subscriber::factory()->for($audience)->create(['email' => 'second@vip.test']);
    Subscriber::factory()->for($audience)->create(['email' => 'outsider@other.test']);
    $segment = Segment::factory()->for($audience)->create([
        'rules' => [['field' => 'email', 'operator' => 'contains', 'value' => '@vip.test']],
    ]);
    app(SyncSegmentSubscribers::class)->handle($segment);
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'segment_id' => $segment->id,
        'html' => '<p>Members only.</p>',
    ]);

    $this->actingAs($user)
        ->post(route('emails.send', [$team, $email]))
        ->assertRedirect(route('emails.show', [$team, $email]));

    expect($email->fresh()->recipient_count)->toBe(2);

    [$loader] = (new PrepareEmailSendChunk($email->id))->withFakeBatch();
    $loader->handle(app(RenderCampaignContent::class), app(TeamMailer::class));

    expect($email->deliveries()->orderBy('email_address')->pluck('email_address')->all())
        ->toBe(['first@vip.test', 'second@vip.test']);
});

test('workspace members can view a segment but cannot create, edit or delete one', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $audience = Audience::factory()->for($team)->create();
    $segment = Segment::factory()->for($audience)->create(['name' => 'Original']);
    $payload = [
        'name' => 'Changed',
        'match_type' => 'all',
        'rules' => [['field' => 'status', 'operator' => 'equals', 'value' => 'subscribed']],
    ];

    $this->actingAs($member)
        ->get(route('audiences.segments.show', [$team, $audience, $segment]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('segments/show')
            ->where('canManage', false));

    $this->actingAs($member)
        ->post(route('audiences.segments.store', [$team, $audience]), $payload)
        ->assertForbidden();

    $this->actingAs($member)
        ->patch(route('audiences.segments.update', [$team, $audience, $segment]), $payload)
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('audiences.segments.destroy', [$team, $audience, $segment]))
        ->assertForbidden();

    expect($audience->segments()->count())->toBe(1)
        ->and($segment->refresh()->name)->toBe('Original');
});

test('segments of another workspace or audience are not found', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $otherAudience = Audience::factory()->for($team)->create();
    $otherAudienceSegment = Segment::factory()->for($otherAudience)->create();
    $foreignAudience = Audience::factory()->for(User::factory()->create()->currentTeam)->create();
    $foreignSegment = Segment::factory()->for($foreignAudience)->create();

    $this->actingAs($user)
        ->get(route('audiences.segments.show', [$team, $audience, $otherAudienceSegment]))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('audiences.segments.show', [$team, $foreignAudience, $foreignSegment]))
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('audiences.segments.destroy', [$team, $foreignAudience, $foreignSegment]))
        ->assertNotFound();

    expect(Segment::query()->whereKey($foreignSegment->id)->exists())->toBeTrue();
});
