<?php

use App\Actions\Emails\BuildTrackedEmailHtml;
use App\Actions\Emails\RenderCampaignContent;
use App\Actions\Emails\StartEmailSend;
use App\Actions\Emails\StoreEmailAttachments;
use App\Contracts\DnsResolver;
use App\Enums\EmailStatus;
use App\Enums\SubscriberLanguage;
use App\Jobs\PrepareEmailSendChunk;
use App\Jobs\SendCampaignTestEmail;
use App\Mail\ComposedEmailTest;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Email;
use App\Models\EmailAttachment;
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
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

test('campaign attachments re-check the 10 attachment cap under the campaign lock', function () {
    Storage::fake('local');
    $email = Email::factory()->create();
    EmailAttachment::factory()->count(9)->for($email)->create();

    expect(fn () => app(StoreEmailAttachments::class)->handle($email, [
        UploadedFile::fake()->create('first.pdf', 10, 'application/pdf'),
        UploadedFile::fake()->create('second.pdf', 10, 'application/pdf'),
    ]))->toThrow(ValidationException::class, 'A campaign may have up to 10 attachments.');

    expect($email->attachments()->count())->toBe(9);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('campaign attachments are refused once the campaign has left draft', function () {
    Storage::fake('local');
    $email = Email::factory()->create();
    Email::query()->whereKey($email->id)->update(['status' => EmailStatus::Queued]);

    expect(fn () => app(StoreEmailAttachments::class)->handle($email, [
        UploadedFile::fake()->create('guide.pdf', 10, 'application/pdf'),
    ]))->toThrow(fn (HttpException $exception) => expect($exception->getStatusCode())->toBe(409));

    expect($email->attachments()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('campaign attachment files already stored are removed when the upload fails', function () {
    Storage::fake('local');
    $email = Email::factory()->create();
    EmailAttachment::creating(function (EmailAttachment $attachment): void {
        if ($attachment->original_name === 'second.pdf') {
            throw new RuntimeException('The attachment row could not be saved.');
        }
    });

    expect(fn () => app(StoreEmailAttachments::class)->handle($email, [
        UploadedFile::fake()->create('first.pdf', 10, 'application/pdf'),
        UploadedFile::fake()->create('second.pdf', 10, 'application/pdf'),
    ]))->toThrow(RuntimeException::class, 'The attachment row could not be saved.');

    expect($email->attachments()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
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
        'language' => SubscriberLanguage::Indonesian,
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
        'language' => 'id',
        'language_name' => 'Indonesian',
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

test('the link checker follows redirects and reports where they end', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://moved.example/old' => Http::response('', 301, ['Location' => '/new']),
        'https://moved.example/new' => Http::response('', 404),
        'https://short.example/go' => Http::response('', 302, ['Location' => 'https://landing.example/page']),
        'https://landing.example/page' => Http::response('', 200),
        'https://sneaky.example/go' => Http::response('', 302, ['Location' => 'https://internal.example/admin']),
    ]);

    $resolver = Mockery::mock(DnsResolver::class);
    $resolver->shouldReceive('resolve')->with('moved.example')->andReturn(['93.184.216.34']);
    $resolver->shouldReceive('resolve')->with('short.example')->andReturn(['93.184.216.34']);
    $resolver->shouldReceive('resolve')->with('landing.example')->andReturn(['93.184.216.34']);
    $resolver->shouldReceive('resolve')->with('sneaky.example')->andReturn(['93.184.216.34']);
    $resolver->shouldReceive('resolve')->with('internal.example')->andReturn(['10.0.0.5']);
    $this->app->instance(DnsResolver::class, $resolver);

    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'html' => <<<'HTML'
            <a href="https://moved.example/old">Moved</a>
            <a href="https://short.example/go">Short</a>
            <a href="https://sneaky.example/go">Sneaky</a>
            HTML,
    ]);

    $this->actingAs($user)
        ->getJson(route('emails.check-links', [$user->currentTeam, $email]))
        ->assertOk()
        ->assertJsonPath('checked', 3)
        ->assertJsonCount(2, 'broken')
        ->assertJsonPath('broken.0.url', 'https://moved.example/old')
        ->assertJsonPath('broken.0.status', 404)
        ->assertJsonPath('broken.0.reason', 'Redirects to https://moved.example/new, which returns HTTP 404.')
        ->assertJsonPath('broken.1.url', 'https://sneaky.example/go')
        ->assertJsonPath('broken.1.status', null);

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'internal.example'));
});

test('a redirect to a malformed address is reported without stopping the check', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://garbled.example/go' => Http::response('', 302, ['Location' => 'http:///not-a-host']),
        'https://fine.example/page' => Http::response('', 200),
    ]);

    $resolver = Mockery::mock(DnsResolver::class);
    $resolver->shouldReceive('resolve')->with('garbled.example')->andReturn(['93.184.216.34']);
    $resolver->shouldReceive('resolve')->with('fine.example')->andReturn(['93.184.216.34']);
    $this->app->instance(DnsResolver::class, $resolver);

    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'html' => <<<'HTML'
            <a href="https://garbled.example/go">Garbled</a>
            <a href="https://fine.example/page">Fine</a>
            HTML,
    ]);

    $this->actingAs($user)
        ->getJson(route('emails.check-links', [$user->currentTeam, $email]))
        ->assertOk()
        ->assertJsonPath('checked', 2)
        ->assertJsonCount(1, 'broken')
        ->assertJsonPath('broken.0.url', 'https://garbled.example/go')
        ->assertJsonPath('broken.0.status', 302)
        ->assertJsonPath('broken.0.reason', 'Redirects to an address that is not valid.');
});

test('the link checker retries with GET when a server refuses HEAD', function () {
    Http::preventStrayRequests();
    Http::fake(function ($request) {
        return $request->method() === 'HEAD'
            ? Http::response('', 405)
            : Http::response('ok', 200);
    });

    $resolver = Mockery::mock(DnsResolver::class);
    $resolver->shouldReceive('resolve')->with('nohead.example')->andReturn(['93.184.216.34']);
    $this->app->instance(DnsResolver::class, $resolver);

    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'html' => '<a href="https://nohead.example/page">Page</a>',
    ]);

    $this->actingAs($user)
        ->getJson(route('emails.check-links', [$user->currentTeam, $email]))
        ->assertOk()
        ->assertJsonPath('checked', 1)
        ->assertJsonCount(0, 'broken');

    Http::assertSentCount(2);
});

test('a send opens the body with the personalised preheader', function () {
    $email = Email::factory()->create([
        'preheader' => 'Fresh picks for {{ first_name }}',
        'html' => '<html><body><p>Hello</p></body></html>',
    ]);
    $delivery = EmailDelivery::factory()->for($email)->create(['merge_data' => ['first_name' => 'Ada']]);

    $html = app(BuildTrackedEmailHtml::class)->build($delivery);

    expect($html)
        ->toStartWith('<html><body><div style="display:none')
        ->toContain('Fresh picks for Ada')
        ->and(strpos($html, 'Fresh picks for Ada'))->toBeLessThan(strpos($html, '<p>Hello</p>'));
});

test('a send without a preheader adds no hidden preview text', function () {
    $email = Email::factory()->create([
        'preheader' => null,
        'html' => '<html><body><p>Hello</p></body></html>',
    ]);
    $delivery = EmailDelivery::factory()->for($email)->create();

    expect(app(BuildTrackedEmailHtml::class)->build($delivery))
        ->toStartWith('<html><body><p>Hello</p>')
        ->not->toContain('mso-hide:all');
});

test('a generated text part keeps link targets and leaves out styles and hidden preview text', function () {
    $email = Email::factory()->make([
        'plain_text' => null,
        'html' => '<!DOCTYPE html><html><head><title>Weekly</title><style>p { color: red; }</style></head>'
            .'<body><div style="display:none;max-height:0">Hidden preview</div>'
            .'<p>Big news &amp; more</p><p><a href="https://example.com/news">Read the story</a></p>'
            .'<p><a href="https://example.com">https://example.com</a> <a href="#top">Top</a></p></body></html>',
    ]);

    $text = app(RenderCampaignContent::class)->plainText($email, []);

    expect($text)->toBe("Big news & more\nRead the story (https://example.com/news)\nhttps://example.com Top");
});

test('a generated text part breaks lines at line breaks and builder blocks', function () {
    $email = Email::factory()->make([
        'plain_text' => null,
        'html' => '<table><tr><td>First line<br>Second line<br />Third</td></tr></table>'
            .'<table><tr><td><a href="https://example.com/banner"><img src="b.png" alt=""></a></td></tr></table>'
            .'<table><tr><td>Closing words</td></tr></table>',
    ]);

    expect(app(RenderCampaignContent::class)->plainText($email, []))
        ->toBe("First line\nSecond line\nThird\n\nhttps://example.com/banner\n\nClosing words");
});

test('a preheader holding the unsubscribe tag does not stand in for the visible footer', function () {
    $email = Email::factory()->create([
        'preheader' => 'Leave any time: {{ unsubscribe_url }}',
        'html' => '<html><body><p>Hello</p></body></html>',
    ]);
    $delivery = EmailDelivery::factory()->for($email)->create();

    expect(app(BuildTrackedEmailHtml::class)->build($delivery))->toContain('>Unsubscribe</a>');
});

test('a document without a body tag gets its preheader after the head', function () {
    $email = Email::factory()->create([
        'preheader' => 'Inside the document',
        'html' => '<!DOCTYPE html><html><head><title>T</title></head><p>Hello</p></html>',
    ]);
    $delivery = EmailDelivery::factory()->for($email)->create();

    expect(app(BuildTrackedEmailHtml::class)->build($delivery))
        ->toStartWith('<!DOCTYPE html><html><head><title>T</title></head><div style="display:none');
});

test('a campaign test send fills the text part links with pages that opt nobody out', function () {
    Queue::fake();

    $user = User::factory()->create();
    TeamEmailIntegration::factory()->for($user->currentTeam)->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'html' => '<p>Hello</p>',
        'plain_text' => 'Hello. Browser copy: {{ web_view_url }}',
    ]);

    $this->actingAs($user)
        ->post(route('emails.test', [$user->currentTeam, $email]), ['to' => 'reviewer@example.com'])
        ->assertRedirect();

    $expected = 'Hello. Browser copy: '.route('public.web_view.test')."\n\nUnsubscribe: ".route('public.unsubscribe.test');

    Queue::assertPushed(SendCampaignTestEmail::class, fn (SendCampaignTestEmail $job): bool => $job->plainText === $expected);
});

test('a campaign test send carries the preheader', function () {
    Queue::fake();

    $user = User::factory()->create();
    TeamEmailIntegration::factory()->for($user->currentTeam)->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'preheader' => 'Sneak peek inside',
        'html' => '<p>Hello</p>',
    ]);

    $this->actingAs($user)
        ->post(route('emails.test', [$user->currentTeam, $email]), ['to' => 'reviewer@example.com'])
        ->assertRedirect();

    Queue::assertPushed(SendCampaignTestEmail::class, fn (SendCampaignTestEmail $job): bool => str_starts_with($job->html, '<div style="display:none')
        && str_contains($job->html, 'Sneak peek inside'));
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
