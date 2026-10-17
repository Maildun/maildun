<?php

use App\Enums\SubscriberStatus;
use App\Models\Audience;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\SubscribeForm;
use App\Models\Subscriber;
use App\Models\TeamApiKey;
use App\Models\User;
use App\Notifications\AudienceSubscriptionChanged;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

beforeEach(fn () => Notification::fake());

/**
 * @return Closure(AudienceSubscriptionChanged, array<int, string>, AnonymousNotifiable): bool
 */
function sentToAlerts(string $email, bool $subscribed): Closure
{
    return fn (AudienceSubscriptionChanged $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'alerts@example.com'
        && $notification->email === $email
        && $notification->subscribed === $subscribed;
}

test('a form signup emails the audience notification address', function () {
    $audience = Audience::factory()->create(['notification_email' => 'alerts@example.com']);
    $subscribeForm = SubscribeForm::factory()->for($audience)->published()->create();

    $this->postJson(route('public.subscribe_forms.store', $subscribeForm), [
        'email' => 'reader@example.com',
        'consent' => true,
        'website' => '',
    ])->assertOk();

    Notification::assertSentOnDemand(AudienceSubscriptionChanged::class, sentToAlerts('reader@example.com', subscribed: true));
});

test('a double opt-in signup alerts only once it is confirmed', function () {
    $audience = Audience::factory()->create(['double_opt_in' => true, 'notification_email' => 'alerts@example.com']);
    $subscriber = Subscriber::factory()->for($audience)->create([
        'email' => 'reader@example.com',
        'status' => SubscriberStatus::Subscribed,
        'subscribed_at' => null,
    ]);

    $this->get(URL::signedRoute('public.subscribe.confirm', $subscriber))->assertOk();

    Notification::assertSentOnDemand(AudienceSubscriptionChanged::class, sentToAlerts('reader@example.com', subscribed: true));
});

test('an unsubscribe link emails the audience notification address', function () {
    $audience = Audience::factory()->create(['notification_email' => 'alerts@example.com']);
    $subscriber = Subscriber::factory()->for($audience)->create(['email' => 'reader@example.com']);
    $delivery = EmailDelivery::factory()
        ->for(Email::factory()->for($audience->team)->create(['audience_id' => $audience->id]))
        ->create(['subscriber_id' => $subscriber->id, 'email_address' => $subscriber->email]);

    $this->post(URL::signedRoute('public.unsubscribe.store', ['delivery' => $delivery]))->assertNoContent();

    Notification::assertSentOnDemand(AudienceSubscriptionChanged::class, sentToAlerts('reader@example.com', subscribed: false));
});

test('api subscribe and unsubscribe calls email the audience notification address', function () {
    $audience = Audience::factory()->create(['notification_email' => 'alerts@example.com']);
    $issued = TeamApiKey::issue($audience->team, 'Production');

    $this->withToken($issued['token'])
        ->postJson(route('api.v1.audiences.subscribers.store', $audience->uuid), ['email' => 'reader@example.com'])
        ->assertOk();
    $this->withToken($issued['token'])
        ->deleteJson(route('api.v1.audiences.subscribers.destroy', $audience->uuid), ['email' => 'reader@example.com'])
        ->assertSuccessful();

    Notification::assertSentOnDemand(AudienceSubscriptionChanged::class, sentToAlerts('reader@example.com', subscribed: true));
    Notification::assertSentOnDemand(AudienceSubscriptionChanged::class, sentToAlerts('reader@example.com', subscribed: false));
});

test('an audience without a notification address sends no alert', function () {
    $audience = Audience::factory()->create(['notification_email' => null]);
    $subscribeForm = SubscribeForm::factory()->for($audience)->published()->create();

    $this->postJson(route('public.subscribe_forms.store', $subscribeForm), [
        'email' => 'reader@example.com',
        'consent' => true,
        'website' => '',
    ])->assertOk();

    Notification::assertNothingSent();
});

test('a subscriber the team adds in the app sends no alert', function () {
    $user = User::factory()->create();
    $audience = Audience::factory()->for($user->currentTeam)->create(['notification_email' => 'alerts@example.com']);

    $this->actingAs($user)
        ->post(route('audiences.subscribers.store', [$user->currentTeam, $audience]), [
            'email' => 'reader@example.com',
            'consent_confirmed' => true,
        ])
        ->assertRedirect();

    Notification::assertNothingSent();
});

test('the alert escapes the subscriber details it shows', function () {
    $notification = new AudienceSubscriptionChanged(
        audienceName: 'News <b>letter</b>',
        email: 'reader@example.com',
        name: "<script>alert('x')</script>",
        subscribed: true,
        profileUrl: 'https://maildun.test/subscribers/1',
    );

    $html = (string) $notification->toMail(new AnonymousNotifiable)->render();

    expect($html)
        ->toContain('&lt;script&gt;')
        ->not->toContain("<script>alert('x')</script>")
        ->not->toContain('<b>letter</b>');
});
