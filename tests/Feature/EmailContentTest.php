<?php

use App\Actions\Emails\BuildTrackedEmailHtml;
use App\Actions\Emails\RenderCampaignContent;
use App\Actions\Emails\StartEmailSend;
use App\Contracts\DnsResolver;
use App\Jobs\PrepareEmailSendChunk;
use App\Mail\ComposedEmailTest;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\SubscribeForm;
use App\Models\Subscriber;
use App\Models\TeamEmailIntegration;
use App\Models\User;
use App\Services\TeamMailer;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

test('campaign content options are saved and exposed to the composer', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    AudienceAttribute::factory()->for($audience)->create([
        'name' => 'Company',
        'key' => 'company',
    ]);
    $email = Email::factory()->for($team)->create();

    $this->actingAs($user)
        ->patch(route('emails.update', [$team, $email]), [
            'name' => $email->name,
            'subject' => 'Hello {{ name }}',
            'html' => '<p>Hello {{ name }}</p>',
            'plain_text' => 'Hello {{ name }}',
            'query_string' => '?utm_source=campaign',
            'track_clicks' => false,
            'track_opens' => false,
            'audience' => $audience->uuid,
        ])
        ->assertRedirect();

    $email->refresh();

    expect($email->plain_text)->toBe('Hello {{ name }}')
        ->and($email->query_string)->toBe('utm_source=campaign')
        ->and($email->track_clicks)->toBeFalse()
        ->and($email->track_opens)->toBeFalse();

    $this->actingAs($user)
        ->get(route('emails.edit', [$team, $email]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('email.plain_text', 'Hello {{ name }}')
            ->where('email.query_string', 'utm_source=campaign')
            ->where('email.track_clicks', false)
            ->where('email.track_opens', false)
            ->where('audiences.0.attributes.0.key', 'company'));
});

test('campaign attachments accept supported private files and are included in test mail', function () {
    Storage::fake('local');
    Mail::fake();

    $user = User::factory()->create();
    TeamEmailIntegration::factory()->for($user->currentTeam)->ses()->create();
    $email = Email::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->post(route('emails.attachments.store', [$user->currentTeam, $email]), [
            'attachments' => [
                UploadedFile::fake()->create('guide.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->image('header.jpg'),
            ],
        ])
        ->assertRedirect();

    expect($email->attachments()->count())->toBe(2);

    $attachment = $email->attachments()->where('original_name', 'guide.pdf')->firstOrFail();
    Storage::disk('local')->assertExists($attachment->path);

    $this->actingAs($user)
        ->post(route('emails.test', [$user->currentTeam, $email]), [
            'to' => 'reviewer@example.com',
        ])
        ->assertRedirect();

    Mail::assertSent(ComposedEmailTest::class, fn (ComposedEmailTest $mail): bool => $mail->hasAttachment(
        Attachment::fromStorageDisk('local', $attachment->path)
            ->as('guide.pdf')
            ->withMime('application/pdf'),
    ));
});

test('campaign attachments use the configured private storage disk', function () {
    config()->set('filesystems.default', 's3');
    Storage::fake('s3');

    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->post(route('emails.attachments.store', [$user->currentTeam, $email]), [
            'attachments' => [
                UploadedFile::fake()->create('guide.pdf', 100, 'application/pdf'),
            ],
        ])
        ->assertRedirect();

    $attachment = $email->attachments()->sole();

    expect($attachment->disk)->toBe('s3');
    Storage::disk('s3')->assertExists($attachment->path);
});

test('campaign attachments reject unsupported file types', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->post(route('emails.attachments.store', [$user->currentTeam, $email]), [
            'attachments' => [
                UploadedFile::fake()->create('payload.exe', 10, 'application/octet-stream'),
            ],
        ])
        ->assertInvalid('attachments.0');

    expect($email->attachments()->count())->toBe(0);
});

test('campaign personalization is snapshotted and query strings are applied before tracking', function () {
    Bus::fake();
    $this->travelTo('2014-05-02 10:00:00');

    $user = User::factory()->create();
    TeamEmailIntegration::factory()->for($user->currentTeam)->ses()->create();
    $audience = Audience::factory()->for($user->currentTeam)->create();
    $subscriber = Subscriber::factory()->for($audience)->create([
        'email' => 'reader@example.com',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'attribute_values' => ['company' => 'Analytical Engines'],
    ]);
    $email = Email::factory()->for($user->currentTeam)->create([
        'audience_id' => $audience->id,
        'subject' => 'Hello {{ name }} on {{ day_name }}',
        'html' => '<p>{{ company }}</p><a href="https://example.com/news#top">Read</a>',
        'query_string' => 'utm_source=maildun',
    ]);

    app(StartEmailSend::class)->handle($email);

    [$loader] = (new PrepareEmailSendChunk($email->id))->withFakeBatch();
    $loader->handle(app(RenderCampaignContent::class), app(TeamMailer::class));

    $delivery = $email->deliveries()->sole();

    expect($delivery->merge_data)->toMatchArray([
        'name' => 'Ada Lovelace',
        'email' => 'reader@example.com',
        'day' => '02',
        'day_name' => 'Friday',
        'month' => '05',
        'month_name' => 'May',
        'year' => '2014',
        'company' => 'Analytical Engines',
    ])->and($email->links()->value('url'))
        ->toBe('https://example.com/news?utm_source=maildun#top');

    $html = app(BuildTrackedEmailHtml::class)->build($delivery);
    $renderer = app(RenderCampaignContent::class);

    expect($html)->toContain('Analytical Engines')
        ->and($html)->toContain('/track/emails/'.$delivery->uuid.'/links/')
        ->and($renderer->text($email->subject, $delivery->merge_data ?? []))
        ->toBe('Hello Ada Lovelace on Friday');

    $subscriber->update(['first_name' => 'Changed']);

    expect($delivery->fresh()->merge_data['name'])->toBe('Ada Lovelace');
});

test('disabled tracking keeps tagged links direct and does not count opens', function () {
    $email = Email::factory()->create([
        'html' => '<a href="https://example.com/news">Read</a>',
        'query_string' => 'utm_source=maildun',
        'track_clicks' => false,
        'track_opens' => false,
    ]);
    $delivery = EmailDelivery::factory()->for($email)->create();

    $html = app(BuildTrackedEmailHtml::class)->build($delivery);

    expect($html)->toContain('https://example.com/news?utm_source=maildun')
        ->and($html)->not->toContain('/track/emails/')
        ->and($html)->not->toContain('open.gif');

    $this->get(URL::signedRoute('emails.track.open', [
        'delivery' => $delivery,
    ]))->assertOk();

    expect($delivery->fresh()->opens_count)->toBe(0);
});

test('the link checker reports broken links without requesting private hosts', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://good.example/*' => Http::response('', 204),
        'https://broken.example/*' => Http::response('', 404),
    ]);

    $resolver = Mockery::mock(DnsResolver::class);
    $resolver->shouldReceive('resolve')
        ->with('good.example')
        ->once()
        ->andReturn(['93.184.216.34']);
    $resolver->shouldReceive('resolve')
        ->with('broken.example')
        ->once()
        ->andReturn(['93.184.216.34']);
    $resolver->shouldReceive('resolve')
        ->with('private.example')
        ->once()
        ->andReturn(['127.0.0.1']);
    $this->app->instance(DnsResolver::class, $resolver);

    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'html' => <<<'HTML'
            <a href="https://good.example/page">Good</a>
            <a href="https://broken.example/page">Broken</a>
            <a href="https://private.example/admin">Private</a>
            HTML,
    ]);

    $this->actingAs($user)
        ->getJson(route('emails.check-links', [$user->currentTeam, $email]))
        ->assertOk()
        ->assertJsonPath('checked', 3)
        ->assertJsonCount(2, 'broken')
        ->assertJsonPath('broken.0.status', 404)
        ->assertJsonPath('broken.1.status', null);

    Http::assertSentCount(2);
});

test('a send replaces the subscribe tag with the published form url', function () {
    $audience = Audience::factory()->create();
    $form = SubscribeForm::factory()->for($audience)->published()->create();
    $email = Email::factory()->for($audience->team)->create([
        'audience_id' => $audience->id,
        'html' => '<p><a href="{{ subscribe_url }}">Subscribe here</a></p>',
    ]);
    $delivery = EmailDelivery::factory()->for($email)->create();

    $html = app(BuildTrackedEmailHtml::class)->build($delivery);

    expect($html)
        ->toContain(route('public.subscribe_forms.show', $form))
        ->toContain('Subscribe here')
        ->not->toContain('{{ subscribe_url }}');
});
