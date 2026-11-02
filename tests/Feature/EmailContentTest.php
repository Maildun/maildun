<?php

use App\Actions\Emails\BuildTrackedEmailHtml;
use App\Actions\Emails\RenderCampaignContent;
use App\Actions\Emails\StartEmailSend;
use App\Actions\Emails\StoreEmailAttachments;
use App\Contracts\DnsResolver;
use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailStatus;
use App\Enums\SubscriberLanguage;
use App\Enums\TeamRole;
use App\Jobs\PrepareEmailSendChunk;
use App\Jobs\SendCampaignTestEmail;
use App\Jobs\SendEmailDelivery;
use App\Mail\CampaignEmail;
use App\Mail\ComposedEmailTest;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\EmailDelivery;
use App\Models\Segment;
use App\Models\SubscribeForm;
use App\Models\Subscriber;
use App\Models\Tag;
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

test('campaigns send an audience field with no stored value as a blank', function () {
    Bus::fake();

    $user = User::factory()->create();
    TeamEmailIntegration::factory()->for($user->currentTeam)->create();
    $audience = Audience::factory()->for($user->currentTeam)->create();
    AudienceAttribute::factory()->for($audience)->create(['name' => 'Company', 'key' => 'company']);
    Subscriber::factory()->for($audience)->create([
        'first_name' => 'Ada',
        'attribute_values' => [],
    ]);
    $email = Email::factory()->for($user->currentTeam)->create([
        'audience_id' => $audience->id,
        'subject' => 'News from {{ company }}',
        'html' => '<p>Hi {{ first_name }} from {{ company }}</p>',
    ]);

    app(StartEmailSend::class)->handle($email);
    [$loader] = (new PrepareEmailSendChunk($email->id))->withFakeBatch();
    $loader->handle(app(RenderCampaignContent::class), app(TeamMailer::class));

    $delivery = $email->deliveries()->sole();

    expect(app(BuildTrackedEmailHtml::class)->build($delivery))->toContain('<p>Hi Ada from </p>');
    expect(app(RenderCampaignContent::class)->text($email->subject, $delivery->merge_data ?? []))
        ->toBe('News from ');
});

test('campaigns send personalized sections by each subscriber\'s segments and tags', function () {
    Bus::fake();

    $user = User::factory()->create();
    TeamEmailIntegration::factory()->for($user->currentTeam)->create();
    $audience = Audience::factory()->for($user->currentTeam)->create();
    $pro = Subscriber::factory()->for($audience)->create(['email' => 'pro@example.com']);
    $vip = Subscriber::factory()->for($audience)->create(['email' => 'vip@example.com']);
    Subscriber::factory()->for($audience)->create(['email' => 'reader@example.com']);
    $segment = Segment::factory()->for($audience)->create(['name' => 'Pro customers']);
    $segment->subscribers()->attach($pro);
    $tag = Tag::factory()->for($user->currentTeam)->create(['name' => 'VIP']);
    $vip->tags()->attach($tag);
    $proSection = '<!--meb:if:b_pro {"conditions":[{"id":"segment:'.$segment->uuid.'"}]}-->'
        .'<p>Pro perks, <a href="{{ unsubscribe_url }}">opt out</a></p>'
        .'<!--meb:endif:b_pro-->';
    $vipSection = '<!--meb:if:b_vip {"conditions":[{"id":"tag:'.$tag->uuid.'","not":true}]}-->'
        .'<p>Become a VIP</p>'
        .'<!--meb:endif:b_vip-->';
    $email = Email::factory()->for($user->currentTeam)->create([
        'audience_id' => $audience->id,
        'html' => "<html><body><p>News for everyone</p>{$proSection}{$vipSection}</body></html>",
        'plain_text' => null,
    ]);

    app(StartEmailSend::class)->handle($email);
    [$loader] = (new PrepareEmailSendChunk($email->id))->withFakeBatch();
    $loader->handle(app(RenderCampaignContent::class), app(TeamMailer::class));

    $deliveries = $email->deliveries()->with('subscriber')->get()->keyBy('subscriber.email');
    $builder = app(BuildTrackedEmailHtml::class);
    $renderer = app(RenderCampaignContent::class);
    $proHtml = $builder->build($deliveries['pro@example.com']);
    $vipHtml = $builder->build($deliveries['vip@example.com']);

    expect($proHtml)->toContain('Pro perks')
        ->and($proHtml)->toContain('Become a VIP')
        ->and($proHtml)->not->toContain('meb:')
        ->and($vipHtml)->toContain('News for everyone')
        ->and($vipHtml)->not->toContain('Pro perks')
        ->and($vipHtml)->not->toContain('Become a VIP')
        // The hidden section held the author's opt-out link, so the footer one is added.
        ->and($vipHtml)->toContain('/unsubscribe/')
        ->and($renderer->plainText($email, $deliveries['vip@example.com']->merge_data ?? []))
        ->toBe('News for everyone')
        ->and($renderer->plainText($email, $deliveries['reader@example.com']->merge_data ?? []))
        ->toBe("News for everyone\nBecome a VIP");
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

test('campaign attachments reject files over 10 MB', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->post(route('emails.attachments.store', [$user->currentTeam, $email]), [
            'attachments' => [
                UploadedFile::fake()->create('brochure.pdf', 10241, 'application/pdf'),
            ],
        ])
        ->assertInvalid('attachments.0');

    expect($email->attachments()->count())->toBe(0);
});

test('a campaign holds at most 10 attachments counting the ones already uploaded', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create();
    EmailAttachment::factory()->count(9)->for($email)->create();

    $this->actingAs($user)
        ->post(route('emails.attachments.store', [$user->currentTeam, $email]), [
            'attachments' => [
                UploadedFile::fake()->create('one.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('two.pdf', 10, 'application/pdf'),
            ],
        ])
        ->assertInvalid(['attachments' => 'up to 10 attachments']);

    expect($email->attachments()->count())->toBe(9)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('campaign attachments cannot be added or removed once the campaign is queued', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create(['status' => EmailStatus::Sending]);
    $attachment = EmailAttachment::factory()->for($email)->create();
    Storage::disk('local')->put($attachment->path, 'pdf');

    $this->actingAs($user)
        ->post(route('emails.attachments.store', [$user->currentTeam, $email]), [
            'attachments' => [UploadedFile::fake()->create('late.pdf', 10, 'application/pdf')],
        ])
        ->assertConflict();

    $this->actingAs($user)
        ->delete(route('emails.attachments.destroy', [$user->currentTeam, $email, $attachment]))
        ->assertConflict();

    expect($email->attachments()->count())->toBe(1);
    Storage::disk('local')->assertExists($attachment->path);
});

test('removing a draft attachment deletes its stored file', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create();
    $attachment = EmailAttachment::factory()->for($email)->create();
    Storage::disk('local')->put($attachment->path, 'pdf');

    $this->actingAs($user)
        ->delete(route('emails.attachments.destroy', [$user->currentTeam, $email, $attachment]))
        ->assertRedirect();

    expect($attachment->fresh())->toBeNull();
    Storage::disk('local')->assertMissing($attachment->path);
});

test('an attachment cannot be removed through another campaign', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create();
    $otherAttachment = EmailAttachment::factory()
        ->for(Email::factory()->for($user->currentTeam))
        ->create();

    $this->actingAs($user)
        ->delete(route('emails.attachments.destroy', [$user->currentTeam, $email, $otherAttachment]))
        ->assertNotFound();

    expect($otherAttachment->fresh())->not->toBeNull();
});

test('members who cannot edit campaigns cannot add or remove attachments', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = $owner->currentTeam;
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $email = Email::factory()->for($team)->create();
    $attachment = EmailAttachment::factory()->for($email)->create();

    $this->actingAs($member)
        ->post(route('emails.attachments.store', [$team, $email]), [
            'attachments' => [UploadedFile::fake()->create('guide.pdf', 10, 'application/pdf')],
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('emails.attachments.destroy', [$team, $email, $attachment]))
        ->assertForbidden();

    expect($email->attachments()->count())->toBe(1);
});

test('campaign attachments go out with every delivered campaign message', function () {
    Storage::fake('local');
    Mail::fake();

    $email = Email::factory()->create(['status' => EmailStatus::Sending]);
    TeamEmailIntegration::factory()->for($email->team)->ses()->create();
    $attachment = EmailAttachment::factory()->for($email)->create([
        'original_name' => 'price-list.pdf',
        'mime_type' => 'application/pdf',
    ]);
    Storage::disk('local')->put($attachment->path, 'pdf');
    $delivery = EmailDelivery::factory()->for($email)->create([
        'email_address' => 'reader@example.com',
        'status' => EmailDeliveryStatus::Queued,
    ]);

    app()->call([new SendEmailDelivery($delivery->id), 'handle']);

    Mail::assertSent(CampaignEmail::class, fn (CampaignEmail $mail): bool => $mail->hasTo('reader@example.com')
        && $mail->hasAttachment(
            Attachment::fromStorageDisk('local', $attachment->path)
                ->as('price-list.pdf')
                ->withMime('application/pdf'),
        ));
});

test('the link checker gives up on a redirect chain longer than five hops', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://loop.example/*' => Http::response('', 302, ['Location' => 'https://loop.example/again']),
    ]);

    $resolver = Mockery::mock(DnsResolver::class);
    $resolver->shouldReceive('resolve')->with('loop.example')->andReturn(['93.184.216.34']);
    $this->app->instance(DnsResolver::class, $resolver);

    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'html' => '<a href="https://loop.example/start">Loop</a>',
    ]);

    $this->actingAs($user)
        ->getJson(route('emails.check-links', [$user->currentTeam, $email]))
        ->assertOk()
        ->assertJsonPath('broken.0.url', 'https://loop.example/start')
        ->assertJsonPath('broken.0.status', null)
        ->assertJsonPath('broken.0.reason', 'Redirects more than 5 times.');

    Http::assertSentCount(6);
});

test('the link checker only requests web links, with the campaign query string applied', function () {
    Http::preventStrayRequests();
    Http::fake(['https://shop.example/*' => Http::response('', 200)]);

    $resolver = Mockery::mock(DnsResolver::class);
    $resolver->shouldReceive('resolve')->with('shop.example')->andReturn(['93.184.216.34']);
    $this->app->instance(DnsResolver::class, $resolver);

    $user = User::factory()->create();
    $email = Email::factory()->for($user->currentTeam)->create([
        'html' => <<<'HTML'
            <a href="https://shop.example/sale">Sale</a>
            <a href="https://shop.example/sale">Sale again</a>
            <a href="mailto:hello@example.com">Mail us</a>
            <a href="{{ unsubscribe_url }}">Unsubscribe</a>
            HTML,
        'query_string' => 'utm_source=maildun',
    ]);

    $this->actingAs($user)
        ->getJson(route('emails.check-links', [$user->currentTeam, $email]))
        ->assertOk()
        ->assertJsonPath('checked', 1)
        ->assertJsonCount(0, 'broken');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://shop.example/sale?utm_source=maildun');
    Http::assertSentCount(1);
});

test('links can only be checked on a draft by someone who can edit it', function () {
    Http::preventStrayRequests();

    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = $owner->currentTeam;
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $draft = Email::factory()->for($team)->create(['html' => '<a href="https://example.com">Go</a>']);
    $sent = Email::factory()->for($team)->create(['status' => EmailStatus::Sent]);

    $this->actingAs($member)
        ->getJson(route('emails.check-links', [$team, $draft]))
        ->assertForbidden();

    $this->actingAs($owner)
        ->getJson(route('emails.check-links', [$team, $sent]))
        ->assertConflict();

    Http::assertNothingSent();
});
