<?php

use App\Enums\TeamRole;
use App\Jobs\SendTransactionalEmailDelivery;
use App\Models\Audience;
use App\Models\SubscribeForm;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Models\TransactionalEmail;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('unpublishing a published form takes its hosted page and signups offline', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->create();
    $signup = [
        'email' => 'person@example.com',
        'consent' => true,
        'website' => '',
    ];

    $this->get(route('public.subscribe_forms.show', $subscribeForm))->assertNotFound();

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.publish', [$team, $audience, $subscribeForm]))
        ->assertRedirect();

    $this->get(route('public.subscribe_forms.show', $subscribeForm))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('subscribe-forms/public'));

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.unpublish', [$team, $audience, $subscribeForm]))
        ->assertRedirect();

    $this->get(route('public.subscribe_forms.show', $subscribeForm))->assertNotFound();
    $this->postJson(route('public.subscribe_forms.store', $subscribeForm), $signup)->assertNotFound();

    expect($audience->subscribers()->exists())->toBeFalse();
});

test('viewers cannot create change or delete subscribe forms', function (string $method, string $routeName, array $payload) {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($viewer, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $viewer);
    $audience = Audience::factory()->for($team)->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->published()->create([
        'headline' => 'Original headline',
    ]);

    $parameters = $routeName === 'audiences.subscribe_forms.store'
        ? [$team, $audience]
        : [$team, $audience, $subscribeForm];

    $this->actingAs($viewer)
        ->{$method}(route($routeName, $parameters), $payload)
        ->assertForbidden();

    expect($audience->subscribeForms()->count())->toBe(1)
        ->and($subscribeForm->fresh())
        ->headline->toBe('Original headline')
        ->published_at->not->toBeNull();
})->with([
    'create' => ['post', 'audiences.subscribe_forms.store', [
        'name' => 'Footer signup',
        'headline' => 'Get product news',
        'button_label' => 'Join now',
        'success_message' => 'You are subscribed.',
        'consent_text' => 'I agree to receive marketing emails.',
    ]],
    'update' => ['patch', 'audiences.subscribe_forms.update', [
        'name' => 'Footer signup',
        'headline' => 'Changed headline',
        'button_label' => 'Join now',
        'success_message' => 'You are subscribed.',
        'consent_text' => 'I agree to receive marketing emails.',
    ]],
    'unpublish' => ['patch', 'audiences.subscribe_forms.unpublish', []],
    'delete' => ['delete', 'audiences.subscribe_forms.destroy', []],
]);

test('a double opt-in signup is confirmed through the link in its confirmation email', function () {
    Queue::fake([SendTransactionalEmailDelivery::class]);
    $audience = Audience::factory()->create(['double_opt_in' => true]);
    TeamEmailIntegration::factory()->for($audience->team)->create();
    $transactionalEmail = TransactionalEmail::factory()
        ->for($audience->team)
        ->published()
        ->create([
            'subject' => 'Confirm your subscription',
            'html' => '<p><a href="{{ confirmation_url }}">Confirm subscription</a></p>',
        ]);
    $audience->update(['double_opt_in_email_id' => $transactionalEmail->id]);
    $subscribeForm = SubscribeForm::factory()->for($audience)->published()->create();

    $this->postJson(route('public.subscribe_forms.store', $subscribeForm), [
        'email' => 'person@example.com',
        'consent' => true,
        'website' => '',
    ])->assertOk();

    $subscriber = $audience->subscribers()->sole();
    $delivery = $transactionalEmail->deliveries()->sole();

    expect($subscriber->subscribed_at)->toBeNull()
        ->and(preg_match('/href="([^"]+)"/', $delivery->html, $matches))->toBe(1);

    $confirmationUrl = Str::after(html_entity_decode($matches[1]), config('app.url'));

    $this->get(Str::before($confirmationUrl, '?'))->assertForbidden();

    $this->get($confirmationUrl)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('subscribe-forms/confirmed')
            ->where('audienceName', $audience->name)
            ->where('confirmed', false)
            ->where('canConfirm', true));

    expect($subscriber->fresh()->subscribed_at)->toBeNull();

    $this->post($confirmationUrl)->assertRedirect();

    expect($subscriber->fresh()->subscribed_at)->not->toBeNull();

    $this->get($confirmationUrl)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('confirmed', true)
            ->where('canConfirm', false));
});
