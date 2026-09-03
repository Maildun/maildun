<?php

use App\Actions\Automations\AdvanceAutomationRun;
use App\Actions\Emails\BuildTrackedEmailHtml;
use App\Enums\AutomationAction;
use App\Enums\AutomationTrigger;
use App\Enums\SubscriberStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Mail\AutomationEmail;
use App\Mail\CampaignEmail;
use App\Models\Audience;
use App\Models\Automation;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\Subscriber;
use App\Models\TeamEmailIntegration;
use App\Models\TransactionalEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

function unsubscribeDelivery(array $audienceAttributes = []): EmailDelivery
{
    $audience = Audience::factory()->create($audienceAttributes);
    $subscriber = Subscriber::factory()->for($audience)->create();
    $email = Email::factory()->for($audience->team)->create(['audience_id' => $audience->id]);

    return EmailDelivery::factory()->for($email)->create([
        'subscriber_id' => $subscriber->id,
        'email_address' => $subscriber->email,
    ]);
}

test('an unsigned unsubscribe link is rejected', function () {
    $delivery = unsubscribeDelivery();

    $this->get(route('public.unsubscribe.show', ['delivery' => $delivery]))->assertForbidden();
    $this->post(route('public.unsubscribe.store', ['delivery' => $delivery]))->assertForbidden();

    expect($delivery->subscriber->fresh()->status)->toBe(SubscriberStatus::Subscribed);
});

test('a signed unsubscribe link shows the confirmation page', function () {
    $delivery = unsubscribeDelivery(['name' => 'Weekly digest']);

    $this->get(URL::signedRoute('public.unsubscribe.show', ['delivery' => $delivery]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('unsubscribe/show')
            ->where('email', $delivery->email_address)
            ->where('audience', 'Weekly digest')
            ->where('unsubscribed', false)
            ->has('action'));
});

test('confirming unsubscribes the recipient and announces it', function () {
    Event::fake([SubscriberLifecycleOccurred::class]);

    $delivery = unsubscribeDelivery();

    $this->post(URL::signedRoute('public.unsubscribe.store', ['delivery' => $delivery]), [], ['X-Inertia' => 'true'])
        ->assertRedirect();

    $subscriber = $delivery->subscriber->fresh();

    expect($subscriber->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($subscriber->unsubscribed_at)->not->toBeNull();

    Event::assertDispatched(
        SubscriberLifecycleOccurred::class,
        fn (SubscriberLifecycleOccurred $event): bool => $event->trigger === AutomationTrigger::Unsubscribed
            && $event->subscriber->is($subscriber),
    );
});

test('a one click request from a mail provider succeeds without a session', function () {
    $delivery = unsubscribeDelivery();

    $this->post(
        URL::signedRoute('public.unsubscribe.store', ['delivery' => $delivery]),
        ['List-Unsubscribe' => 'One-Click'],
    )->assertNoContent();

    expect($delivery->subscriber->fresh()->status)->toBe(SubscriberStatus::Unsubscribed);
});

test('unsubscribing twice is not an error', function () {
    Event::fake([SubscriberLifecycleOccurred::class]);

    $delivery = unsubscribeDelivery();
    $url = URL::signedRoute('public.unsubscribe.store', ['delivery' => $delivery]);

    $this->post($url)->assertNoContent();
    $this->post($url)->assertNoContent();

    Event::assertDispatchedTimes(SubscriberLifecycleOccurred::class, 1);

    $this->get(URL::signedRoute('public.unsubscribe.show', ['delivery' => $delivery]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('unsubscribed', true));
});

test('a configured landing page takes over after unsubscribing', function () {
    $delivery = unsubscribeDelivery(['unsubscribed_url' => 'https://example.com/bye']);

    $this->post(
        URL::signedRoute('public.unsubscribe.store', ['delivery' => $delivery]),
        [],
        ['X-Inertia' => 'true'],
    )->assertRedirect('https://example.com/bye');
});

test('a campaign carries the one click unsubscribe headers', function () {
    $delivery = unsubscribeDelivery();

    $headers = (new CampaignEmail($delivery, '<p>Hello</p>'))->headers();

    expect($headers->text['List-Unsubscribe'])->toContain('/unsubscribe/'.$delivery->uuid)
        ->and($headers->text['List-Unsubscribe'])->toContain('signature=')
        ->and($headers->text['List-Unsubscribe'])->toStartWith('<')
        ->and($headers->text['List-Unsubscribe-Post'])->toBe('List-Unsubscribe=One-Click');
});

test('a campaign body gets a visible unsubscribe link', function () {
    $delivery = unsubscribeDelivery();
    $delivery->email->update(['html' => '<html><body><p>Hello</p></body></html>']);

    $html = app(BuildTrackedEmailHtml::class)->build($delivery);

    expect($html)->toContain('/unsubscribe/'.$delivery->uuid)
        ->and($html)->toContain('Unsubscribe</a>')
        ->and(stripos($html, '/unsubscribe/'))->toBeLessThan(stripos($html, '</body>'));
});

test('an author placed merge tag replaces the appended footer', function () {
    $delivery = unsubscribeDelivery();
    $delivery->email->update([
        'html' => '<p>Bye <a href="{{ unsubscribe_url }}">here</a></p>',
    ]);

    $html = app(BuildTrackedEmailHtml::class)->build($delivery);

    expect($html)->not->toContain('{{ unsubscribe_url }}')
        ->and($html)->not->toContain('Unsubscribe</a>')
        ->and(substr_count($html, '/unsubscribe/'.$delivery->uuid))->toBe(1);
});

test('automation mail carries the one click unsubscribe headers', function () {
    $subscriber = Subscriber::factory()->for(Audience::factory()->create())->create();
    $email = TransactionalEmail::factory()->for($subscriber->audience->team)->create();

    $headers = (new AutomationEmail($email, $subscriber, 'Hi', '<p>Hello</p>'))->headers();

    expect($headers->text['List-Unsubscribe'])->toContain('/unsubscribe/s/'.$subscriber->uuid)
        ->and($headers->text['List-Unsubscribe'])->toContain('signature=')
        ->and($headers->text['List-Unsubscribe'])->toStartWith('<')
        ->and($headers->text['List-Unsubscribe-Post'])->toBe('List-Unsubscribe=One-Click');
});

test('an unsigned subscriber unsubscribe link is rejected', function () {
    $subscriber = Subscriber::factory()->for(Audience::factory()->create())->create();

    $this->get(route('public.unsubscribe.subscriber.show', ['subscriber' => $subscriber]))
        ->assertForbidden();
    $this->post(route('public.unsubscribe.subscriber.store', ['subscriber' => $subscriber]))
        ->assertForbidden();

    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Subscribed);
});

test('a signed subscriber link shows the confirmation page', function () {
    $audience = Audience::factory()->create(['name' => 'Product updates']);
    $subscriber = Subscriber::factory()->for($audience)->create();

    $this->get(URL::signedRoute('public.unsubscribe.subscriber.show', ['subscriber' => $subscriber]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('unsubscribe/show')
            ->where('email', $subscriber->email)
            ->where('audience', 'Product updates')
            ->where('unsubscribed', false)
            ->has('action'));
});

test('a one click request on automation mail succeeds without a session', function () {
    Event::fake([SubscriberLifecycleOccurred::class]);

    $subscriber = Subscriber::factory()->for(Audience::factory()->create())->create();

    $this->post(URL::signedRoute('public.unsubscribe.subscriber.store', ['subscriber' => $subscriber]))
        ->assertNoContent();

    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($subscriber->fresh()->unsubscribed_at)->not->toBeNull();

    Event::assertDispatched(
        SubscriberLifecycleOccurred::class,
        fn (SubscriberLifecycleOccurred $event): bool => $event->trigger === AutomationTrigger::Unsubscribed,
    );
});

test('unsubscribing twice from automation mail is not an error', function () {
    Event::fake([SubscriberLifecycleOccurred::class]);

    $subscriber = Subscriber::factory()->for(Audience::factory()->create())->create();
    $url = URL::signedRoute('public.unsubscribe.subscriber.store', ['subscriber' => $subscriber]);

    $this->post($url)->assertNoContent();
    $stamped = $subscriber->fresh()->unsubscribed_at;

    $this->travelTo(now()->addHour());
    $this->post($url)->assertNoContent();

    expect($subscriber->fresh()->unsubscribed_at->toISOString())->toBe($stamped->toISOString());

    Event::assertDispatchedTimes(SubscriberLifecycleOccurred::class, 1);
});

test('a configured landing page takes over for automation mail', function () {
    $audience = Audience::factory()->create(['unsubscribed_url' => 'https://example.com/bye']);
    $subscriber = Subscriber::factory()->for($audience)->create();

    $this->post(
        URL::signedRoute('public.unsubscribe.subscriber.store', ['subscriber' => $subscriber]),
        [],
        ['X-Inertia' => 'true'],
    )->assertRedirect('https://example.com/bye');
});

test('automation mail offers a visible unsubscribe url as a merge tag', function () {
    Mail::fake();

    $automation = Automation::factory()->active()->create();
    TeamEmailIntegration::factory()->for($automation->team)->ses()->create();
    $email = TransactionalEmail::factory()->for($automation->team)->create([
        'html' => '<p>Hello</p><a href="{{ unsubscribe_url }}">Unsubscribe</a>',
    ]);
    $subscriber = Subscriber::factory()
        ->for(Audience::factory()->for($automation->team))
        ->create();

    $run = $automation->runs()->create([
        'subscriber_id' => $subscriber->id,
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

    app(AdvanceAutomationRun::class)->handle($run);

    Mail::assertSent(AutomationEmail::class, function (AutomationEmail $mail) use ($subscriber): bool {
        // The body link is the GET confirmation page, not the one-click POST.
        return str_contains($mail->htmlBody, '/unsubscribe/s/'.$subscriber->uuid)
            && ! str_contains($mail->htmlBody, '{{ unsubscribe_url }}');
    });
});

test('each recipient gets their own unsubscribe rate limit bucket', function () {
    // One provider's addresses carry many recipients' one-click requests, so a
    // shared bucket would throttle unrelated people out of opting out.
    $audience = Audience::factory()->create();
    $subscribers = Subscriber::factory()->count(3)->for($audience)->create();

    // 18 requests in total, but only 6 per recipient. A shared bucket would
    // start returning 429 at the eleventh; separate buckets never do.
    foreach ($subscribers as $subscriber) {
        foreach (range(1, 6) as $ignored) {
            $this->post(URL::signedRoute(
                'public.unsubscribe.subscriber.store',
                ['subscriber' => $subscriber],
            ))->assertNoContent();
        }
    }

    expect($subscribers->every(
        fn (Subscriber $subscriber): bool => $subscriber->fresh()->status === SubscriberStatus::Unsubscribed,
    ))->toBeTrue();
});
