<?php

use App\Actions\Emails\BuildTrackedEmailHtml;
use App\Actions\Emails\RebuildEmailTrackingAggregates;
use App\Models\Email;
use App\Models\EmailDelivery;
use Illuminate\Support\Facades\URL;

test('tracked html rewrites links and appends a signed open pixel', function () {
    $email = Email::factory()->create(['html' => '<a href="https://example.com/story">Story</a>']);
    $delivery = EmailDelivery::factory()->for($email)->create();
    $email->links()->create([
        'url' => 'https://example.com/story',
        'url_hash' => hash('sha256', 'https://example.com/story'),
        'position' => 0,
    ]);

    $html = app(BuildTrackedEmailHtml::class)->build($delivery);

    expect($html)->toContain('/track/emails/'.$delivery->uuid.'/links/')
        ->and($html)->toContain('/track/emails/'.$delivery->uuid.'/open.gif')
        ->and($html)->toContain('signature=');
});

test('the open pixel is inserted before the closing body tag', function () {
    $email = Email::factory()->create(['html' => '<html><body><p>Hello</p></body></html>']);
    $delivery = EmailDelivery::factory()->for($email)->create();

    $html = app(BuildTrackedEmailHtml::class)->build($delivery);

    expect(stripos($html, 'open.gif'))->toBeLessThan(stripos($html, '</body>'));
});

test('signed tracking endpoints count opens and clicks', function () {
    $email = Email::factory()->create();
    $delivery = EmailDelivery::factory()->for($email)->create();
    $link = $email->links()->create([
        'url' => 'https://example.com/story',
        'url_hash' => hash('sha256', 'https://example.com/story'),
        'position' => 0,
    ]);

    $openUrl = URL::signedRoute('emails.track.open', ['delivery' => $delivery]);
    $clickUrl = URL::signedRoute('emails.track.click', ['delivery' => $delivery, 'link' => $link]);

    $this->get($openUrl)->assertOk()->assertHeader('Content-Type', 'image/gif');
    $this->get($openUrl)->assertOk();
    $this->get($clickUrl)->assertRedirect('https://example.com/story');
    $this->get($clickUrl)->assertRedirect('https://example.com/story');

    expect($delivery->fresh()->opens_count)->toBe(2)
        ->and($delivery->fresh()->clicks_count)->toBe(2)
        ->and($delivery->linkClicks()->firstOrFail()->clicks_count)->toBe(2)
        ->and($email->trackingAggregate()->firstOrFail()->only([
            'total_opens_count',
            'unique_opens_count',
            'total_clicks_count',
            'unique_clicks_count',
        ]))->toBe([
            'total_opens_count' => 2,
            'unique_opens_count' => 1,
            'total_clicks_count' => 2,
            'unique_clicks_count' => 1,
        ])->and($link->trackingAggregate()->firstOrFail()->only([
            'total_clicks_count',
            'unique_clicks_count',
        ]))->toBe([
            'total_clicks_count' => 2,
            'unique_clicks_count' => 1,
        ]);
});

test('a click counts as an open when the pixel never loaded', function () {
    $email = Email::factory()->create();
    $delivery = EmailDelivery::factory()->for($email)->create();
    $link = $email->links()->create([
        'url' => 'https://example.com/story',
        'url_hash' => hash('sha256', 'https://example.com/story'),
        'position' => 0,
    ]);

    $this->get(URL::signedRoute('emails.track.click', [
        'delivery' => $delivery,
        'link' => $link,
    ]))->assertRedirect('https://example.com/story');

    $fresh = $delivery->fresh();

    expect($fresh->clicks_count)->toBe(1)
        ->and($fresh->opens_count)->toBe(1)
        ->and($fresh->first_opened_at)->not->toBeNull();

    $this->get(URL::signedRoute('emails.track.click', [
        'delivery' => $delivery,
        'link' => $link,
    ]))->assertRedirect('https://example.com/story');

    expect($delivery->fresh()->opens_count)->toBe(1)
        ->and($delivery->fresh()->clicks_count)->toBe(2)
        ->and($email->trackingAggregate()->firstOrFail()->only([
            'total_opens_count',
            'unique_opens_count',
            'total_clicks_count',
            'unique_clicks_count',
        ]))->toBe([
            'total_opens_count' => 1,
            'unique_opens_count' => 1,
            'total_clicks_count' => 2,
            'unique_clicks_count' => 1,
        ]);
});

test('unsigned tracking requests are rejected', function () {
    $delivery = EmailDelivery::factory()->create();

    $this->get(route('emails.track.open', $delivery))->assertForbidden();
});

test('a click only redirects to an http address', function () {
    $email = Email::factory()->create();
    $delivery = EmailDelivery::factory()->for($email)->create();
    $link = $email->links()->create([
        'url' => 'javascript:alert(1)',
        'url_hash' => hash('sha256', 'javascript:alert(1)'),
        'position' => 0,
    ]);

    $this->get(URL::signedRoute('emails.track.click', ['delivery' => $delivery, 'link' => $link]))
        ->assertNotFound();

    expect($delivery->fresh()->clicks_count)->toBe(0);
});

test('tracking aggregates can be rebuilt from authoritative delivery records', function () {
    $email = Email::factory()->create();
    $firstDelivery = EmailDelivery::factory()->for($email)->create([
        'opens_count' => 3,
        'clicks_count' => 2,
        'first_opened_at' => now()->subMinutes(10),
        'last_opened_at' => now()->subMinutes(5),
        'first_clicked_at' => now()->subMinutes(8),
        'last_clicked_at' => now()->subMinutes(4),
    ]);
    EmailDelivery::factory()->for($email)->create([
        'opens_count' => 1,
        'first_opened_at' => now()->subMinutes(3),
        'last_opened_at' => now()->subMinutes(3),
    ]);
    $link = $email->links()->create([
        'url' => 'https://example.com/story',
        'url_hash' => hash('sha256', 'https://example.com/story'),
        'position' => 0,
    ]);
    $link->clicks()->create([
        'email_delivery_id' => $firstDelivery->id,
        'clicks_count' => 2,
        'first_clicked_at' => now()->subMinutes(8),
        'last_clicked_at' => now()->subMinutes(4),
    ]);

    app(RebuildEmailTrackingAggregates::class)->handle($email);

    expect($email->trackingAggregate()->firstOrFail()->only([
        'total_opens_count',
        'unique_opens_count',
        'total_clicks_count',
        'unique_clicks_count',
    ]))->toBe([
        'total_opens_count' => 4,
        'unique_opens_count' => 2,
        'total_clicks_count' => 2,
        'unique_clicks_count' => 1,
    ])->and($link->trackingAggregate()->firstOrFail()->only([
        'total_clicks_count',
        'unique_clicks_count',
    ]))->toBe([
        'total_clicks_count' => 2,
        'unique_clicks_count' => 1,
    ]);

    $email->trackingAggregate()->update(['total_opens_count' => 0]);

    $this->artisan('emails:rebuild-tracking', ['--email' => $email->uuid])
        ->assertSuccessful();

    expect($email->trackingAggregate()->value('total_opens_count'))->toBe(4);
});
