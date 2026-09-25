<?php

use App\Enums\AudienceAttributeType;
use App\Enums\AutomationTrigger;
use App\Enums\SubscribeFormArtworkPreset;
use App\Enums\SubscribeFormArtworkType;
use App\Enums\SubscribeFormCardPadding;
use App\Enums\SubscribeFormHeaderSpacing;
use App\Enums\SubscribeFormLogoPosition;
use App\Enums\SubscribeFormLogoShape;
use App\Enums\SubscribeFormLogoSize;
use App\Enums\SubscribeFormTextAlignment;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Enums\TeamBrandColor;
use App\Enums\TeamBrandFont;
use App\Enums\TeamBrandInputStyle;
use App\Enums\TransactionalEmailStatus;
use App\Events\SubscriberLifecycleOccurred;
use App\Jobs\SendTransactionalEmailDelivery;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Contact;
use App\Models\SubscribeForm;
use App\Models\Subscriber;
use App\Models\TeamEmailIntegration;
use App\Models\TransactionalEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

test('only published subscribe forms are public', function () {
    $draft = SubscribeForm::factory()->create();
    $published = SubscribeForm::factory()->published()->create([
        'brand_color' => TeamBrandColor::Fuchsia,
        'brand_font' => TeamBrandFont::InstrumentSans,
        'brand_input_style' => TeamBrandInputStyle::Soft,
    ]);
    $published->audience->team->update([
        'brand_color' => TeamBrandColor::Blue,
        'brand_font' => TeamBrandFont::Inter,
        'brand_input_style' => TeamBrandInputStyle::Default,
    ]);

    $this->get(route('public.subscribe_forms.show', $draft))->assertNotFound();

    $this->get(route('public.subscribe_forms.show', [$published, 'embed' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('subscribe-forms/public')
            ->where('embed', true)
            ->where('subscribeForm.uuid', $published->uuid)
            ->where('subscribeForm.theme.color', TeamBrandColor::Fuchsia->value)
            ->where('subscribeForm.theme.font', TeamBrandFont::InstrumentSans->value)
            ->where('subscribeForm.theme.inputStyle', TeamBrandInputStyle::Soft->value)
            ->where('subscribeForm.style', 'card')
            ->where('subscribeForm.image_side', 'right')
            ->where('subscribeForm.artwork_type', 'upload')
            ->where('subscribeForm.artwork_preset', null)
            ->where('subscribeForm.text_alignment', SubscribeFormTextAlignment::Center->value)
            ->where('subscribeForm.image_url', null)
            ->where('subscribeForm.logo', null)
            ->where('subscribeForm.logo_shape', 'default')
            ->where('subscribeForm.logo_size', 'medium')
            ->where('subscribeForm.logo_position', 'center')
            ->where('subscribeForm.header_spacing', 'default')
            ->where('subscribeForm.card_padding', 'default')
            ->where('subscribeForm.success_heading', 'You’re subscribed!')
            ->where('subscribeForm.redirect_enabled', false)
            ->where('subscribeForm.redirect_url', null)
            ->where('subscribeForm.powered_by_enabled', true)
            ->where('subscribeForm.powered_by_form_position', 'bottom-center'));
});

test('published forms expose their content controls on the hosted page', function () {
    $published = SubscribeForm::factory()->published()->create([
        'text_alignment' => SubscribeFormTextAlignment::Right,
        'success_heading' => 'You are on the list!',
        'redirect_enabled' => true,
        'redirect_url' => 'https://example.com/welcome',
        'powered_by_form_position' => 'top-right',
    ]);

    $this->get(route('public.subscribe_forms.show', $published))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscribeForm.text_alignment', 'right')
            ->where('subscribeForm.success_heading', 'You are on the list!')
            ->where('subscribeForm.redirect_enabled', true)
            ->where('subscribeForm.redirect_url', 'https://example.com/welcome')
            ->where('subscribeForm.powered_by_enabled', true)
            ->where('subscribeForm.powered_by_form_position', 'top-right'));
});

test('disabled redirects do not expose their saved destination publicly', function () {
    $published = SubscribeForm::factory()->published()->create([
        'redirect_enabled' => false,
        'redirect_url' => 'https://example.com/private-destination',
    ]);

    $this->get(route('public.subscribe_forms.show', $published))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscribeForm.redirect_enabled', false)
            ->where('subscribeForm.redirect_url', null));
});

test('published forms expose their logo on the hosted page', function () {
    $published = SubscribeForm::factory()->published()->create([
        'logo_path' => 'subscribe-form-logos/brand.png',
        'logo_shape' => SubscribeFormLogoShape::Square,
        'logo_size' => SubscribeFormLogoSize::Large,
        'logo_position' => SubscribeFormLogoPosition::Right,
        'header_spacing' => SubscribeFormHeaderSpacing::Relaxed,
        'card_padding' => SubscribeFormCardPadding::Compact,
    ]);

    $this->get(route('public.subscribe_forms.show', $published))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscribeForm.logo', '/storage/subscribe-form-logos/brand.png')
            ->where('subscribeForm.logo_shape', 'square')
            ->where('subscribeForm.logo_size', 'large')
            ->where('subscribeForm.logo_position', 'right')
            ->where('subscribeForm.header_spacing', 'relaxed')
            ->where('subscribeForm.card_padding', 'compact'));
});

test('published forms expose rounded logo shapes on the hosted page', function () {
    $published = SubscribeForm::factory()->published()->create([
        'logo_shape' => SubscribeFormLogoShape::RoundedXl,
        'logo_position' => SubscribeFormLogoPosition::Left,
        'header_spacing' => SubscribeFormHeaderSpacing::Spacious,
    ]);

    $this->get(route('public.subscribe_forms.show', $published))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscribeForm.logo_shape', 'rounded-xl')
            ->where('subscribeForm.logo_position', 'left')
            ->where('subscribeForm.header_spacing', 'spacious'));
});

test('published forms expose optimized artwork on the hosted page', function () {
    $published = SubscribeForm::factory()->published()->create([
        'image_path' => 'subscribe-form-images/hero.webp',
    ]);

    $this->get(route('public.subscribe_forms.show', $published))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscribeForm.image_url', '/storage/subscribe-form-images/hero.webp'));
});

test('published forms expose brand-aware artwork presets on the hosted page', function () {
    $published = SubscribeForm::factory()->published()->create([
        'artwork_type' => SubscribeFormArtworkType::BackgroundPreset,
        'artwork_preset' => SubscribeFormArtworkPreset::BackgroundGlow,
        'brand_color' => TeamBrandColor::Emerald,
    ]);

    $this->get(route('public.subscribe_forms.show', $published))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscribeForm.artwork_type', 'background-preset')
            ->where('subscribeForm.artwork_preset', 'background-glow')
            ->where('subscribeForm.theme.color', 'emerald'));
});

test('public forms validate configured fields consent and honeypot', function () {
    $audience = Audience::factory()->create([
        'first_name_mode' => 'required',
        'last_name_mode' => 'hidden',
    ]);
    $subscribeForm = SubscribeForm::factory()->for($audience)->published()->create();

    $this->postJson(route('public.subscribe_forms.store', $subscribeForm), [
        'email' => 'person@example.com',
        'last_name' => 'Hidden',
        'consent' => false,
        'website' => 'spam.example',
    ])->assertInvalid(['first_name', 'last_name', 'consent', 'website']);
});

test('published forms create normalized consented subscribers', function () {
    $audience = Audience::factory()->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->published()->create([
        'consent_text' => 'I agree to receive updates.',
    ]);

    $response = $this->postJson(route('public.subscribe_forms.store', $subscribeForm), [
        'email' => '  Person@Example.COM ',
        'first_name' => 'Taylor',
        'last_name' => 'Otwell',
        'consent' => true,
        'website' => '',
    ]);

    $response->assertOk()->assertExactJson(['message' => $subscribeForm->success_message]);

    $subscriber = $audience->subscribers()->firstOrFail();

    expect($subscriber->email)->toBe('person@example.com')
        ->and($subscriber->status)->toBe(SubscriberStatus::Subscribed)
        ->and($subscriber->source)->toBe(SubscriberSource::Form)
        ->and($subscriber->subscribe_form_id)->toBe($subscribeForm->id)
        ->and($subscriber->consent_text)->toBe('I agree to receive updates.')
        ->and($subscriber->consented_at)->not->toBeNull();
});

test('double opt-in forms queue the selected transactional confirmation email', function () {
    $audience = Audience::factory()->create();
    TeamEmailIntegration::factory()->for($audience->team)->ses()->create();
    $transactionalEmail = TransactionalEmail::factory()
        ->for($audience->team)
        ->published()
        ->create([
            'subject' => 'Confirm {{ email }}',
            'html' => '<a href="{{ confirmation_url }}">Confirm subscription</a>',
        ]);
    $audience->update([
        'double_opt_in' => true,
        'double_opt_in_email_id' => $transactionalEmail->id,
    ]);
    $subscribeForm = SubscribeForm::factory()->for($audience)->published()->create();
    Event::fake([SubscriberLifecycleOccurred::class]);
    Queue::fake();

    $this->postJson(route('public.subscribe_forms.store', $subscribeForm), [
        'email' => 'person@example.com',
        'consent' => true,
        'website' => '',
    ])->assertOk();

    $subscriber = $audience->subscribers()->sole();
    $delivery = $transactionalEmail->deliveries()->sole();

    expect($subscriber->status)->toBe(SubscriberStatus::Subscribed)
        ->and($subscriber->subscribed_at)->toBeNull()
        ->and($delivery->to_address)->toBe('person@example.com')
        ->and($delivery->subject)->toBe('Confirm person@example.com')
        ->and($delivery->html)->toContain('/confirm/'.$subscriber->uuid)
        ->and($delivery->html)->toContain('signature=');

    Queue::assertPushed(
        SendTransactionalEmailDelivery::class,
        fn (SendTransactionalEmailDelivery $job): bool => $job->deliveryId === $delivery->id,
    );
    Event::assertNotDispatched(SubscriberLifecycleOccurred::class);
});

test('a double opt-in signup is kept when the confirmation email cannot be queued', function (
    bool $providerConnected,
    TransactionalEmailStatus $confirmationEmailStatus,
) {
    $audience = Audience::factory()->create();

    if ($providerConnected) {
        TeamEmailIntegration::factory()->for($audience->team)->ses()->create();
    }

    $transactionalEmail = TransactionalEmail::factory()
        ->for($audience->team)
        ->create(['status' => $confirmationEmailStatus]);
    $audience->update([
        'double_opt_in' => true,
        'double_opt_in_email_id' => $transactionalEmail->id,
    ]);
    $subscribeForm = SubscribeForm::factory()->for($audience)->published()->create();
    Queue::fake();
    Log::spy();

    $this->postJson(route('public.subscribe_forms.store', $subscribeForm), [
        'email' => 'person@example.com',
        'consent' => true,
        'website' => '',
    ])->assertOk()->assertExactJson(['message' => $subscribeForm->success_message]);

    $subscriber = $audience->subscribers()->sole();

    expect($subscriber->email)->toBe('person@example.com')
        ->and($subscriber->consented_at)->not->toBeNull()
        ->and($subscriber->subscribed_at)->toBeNull();

    Queue::assertNothingPushed();
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $context['audience_id'] === $audience->id)
        ->once();
})->with([
    'no email provider is connected' => [false, TransactionalEmailStatus::Published],
    'the confirmation email was unpublished' => [true, TransactionalEmailStatus::Draft],
]);

test('a signed double opt-in link confirms a pending subscriber', function () {
    $audience = Audience::factory()->create(['double_opt_in' => true]);
    $subscriber = Subscriber::factory()->for($audience)->create([
        'subscribed_at' => null,
        'status' => SubscriberStatus::Subscribed,
    ]);
    Event::fake([SubscriberLifecycleOccurred::class]);

    $response = $this->get(URL::signedRoute('public.subscribe.confirm', $subscriber));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('subscribe-forms/confirmed')
            ->where('audienceName', $audience->name)
            ->where('confirmed', true));

    expect($subscriber->fresh()->subscribed_at)->not->toBeNull();
    Event::assertDispatched(
        SubscriberLifecycleOccurred::class,
        fn (SubscriberLifecycleOccurred $event): bool => $event->trigger === AutomationTrigger::Subscribed,
    );
});

test('published forms display and store their audience attributes', function () {
    $audience = Audience::factory()->create();
    AudienceAttribute::factory()->for($audience)->create([
        'name' => 'Company',
        'key' => 'company',
        'type' => AudienceAttributeType::Text,
        'required' => true,
    ]);
    AudienceAttribute::factory()->for($audience)->create([
        'name' => 'Team size',
        'key' => 'team_size',
        'type' => AudienceAttributeType::Number,
    ]);
    $subscribeForm = SubscribeForm::factory()->for($audience)->published()->create();

    $this->get(route('public.subscribe_forms.show', $subscribeForm))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscribeForm.attributes.0.key', 'company')
            ->where('subscribeForm.attributes.0.required', true)
            ->where('subscribeForm.attributes.1.key', 'team_size')
            ->where('subscribeForm.attributes.1.required', false));

    $this->postJson(route('public.subscribe_forms.store', $subscribeForm), [
        'email' => 'person@example.com',
        'consent' => true,
        'website' => '',
        'attributes' => [
            'company' => 'Acme Inc.',
            'team_size' => 12,
        ],
    ])->assertOk();

    expect($audience->subscribers()->firstOrFail()->attribute_values)->toBe([
        'company' => 'Acme Inc.',
        'team_size' => 12,
    ]);
});

test('published forms require configured required audience attributes', function () {
    $audience = Audience::factory()->create();
    AudienceAttribute::factory()->for($audience)->create([
        'name' => 'Company',
        'key' => 'company',
        'required' => true,
    ]);
    $subscribeForm = SubscribeForm::factory()->for($audience)->published()->create();

    $this->postJson(route('public.subscribe_forms.store', $subscribeForm), [
        'email' => 'person@example.com',
        'consent' => true,
        'website' => '',
    ])->assertInvalid('attributes.company');
});

test('published forms reject unknown audience attributes', function () {
    $audience = Audience::factory()->create();
    AudienceAttribute::factory()->for($audience)->create([
        'name' => 'Company',
        'key' => 'company',
    ]);
    $subscribeForm = SubscribeForm::factory()->for($audience)->published()->create();

    $this->postJson(route('public.subscribe_forms.store', $subscribeForm), [
        'email' => 'person@example.com',
        'consent' => true,
        'website' => '',
        'attributes' => ['unexpected' => 'value'],
    ])->assertInvalid('attributes');
});

test('duplicate public subscriptions are idempotent and do not disclose existence', function () {
    $audience = Audience::factory()->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->published()->create();
    Subscriber::factory()->for($audience)->create([
        'email' => 'person@example.com',
        'first_name' => 'Original',
    ]);

    $response = $this->postJson(route('public.subscribe_forms.store', $subscribeForm), [
        'email' => 'person@example.com',
        'first_name' => 'Changed',
        'consent' => true,
        'website' => '',
    ]);

    $response->assertOk()->assertExactJson(['message' => $subscribeForm->success_message]);
    expect($audience->subscribers()->count())->toBe(1)
        ->and($audience->subscribers()->first()->first_name)->toBe('Original');
});

test('a public form reuses an existing team contact without replacing its profile', function () {
    $audience = Audience::factory()->create();
    $contact = Contact::factory()->for($audience->team)->create([
        'email' => 'person@example.com',
        'first_name' => 'Original',
        'last_name' => 'Person',
    ]);
    $subscribeForm = SubscribeForm::factory()->for($audience)->published()->create();

    $this->postJson(route('public.subscribe_forms.store', $subscribeForm), [
        'email' => 'person@example.com',
        'first_name' => 'Replacement',
        'last_name' => 'Name',
        'consent' => true,
        'website' => '',
    ])->assertOk();

    $subscriber = $audience->subscribers()->sole();

    expect($contact->fresh()->first_name)->toBe('Original')
        ->and($contact->fresh()->last_name)->toBe('Person')
        ->and($subscriber->contact_id)->toBe($contact->id)
        ->and($subscriber->first_name)->toBe('Original')
        ->and($subscriber->last_name)->toBe('Person');
});

test('an unsubscribed address can subscribe again with fresh consent', function () {
    $audience = Audience::factory()->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->published()->create();
    $subscriber = Subscriber::factory()->for($audience)->unsubscribed()->create([
        'email' => 'person@example.com',
        'consented_at' => now()->subYear(),
    ]);
    $previousConsentAt = $subscriber->consented_at;

    $this->travel(1)->day();

    $this->postJson(route('public.subscribe_forms.store', $subscribeForm), [
        'email' => 'person@example.com',
        'consent' => true,
        'website' => '',
    ])->assertOk();

    $subscriber->refresh();

    expect($subscriber->status)->toBe(SubscriberStatus::Subscribed)
        ->and($subscriber->unsubscribed_at)->toBeNull()
        ->and($subscriber->consented_at->isAfter($previousConsentAt))->toBeTrue();
});

test('public subscribe endpoints are unavailable after a form is deleted', function () {
    $subscribeForm = SubscribeForm::factory()->published()->create();
    $uuid = $subscribeForm->uuid;
    $subscribeForm->delete();

    $this->get(route('public.subscribe_forms.show', $uuid))->assertNotFound();
    $this->postJson(route('public.subscribe_forms.store', $uuid), [
        'email' => 'person@example.com',
        'consent' => true,
    ])->assertNotFound();
});

test('public subscribe submissions are throttled per form and email', function () {
    $subscribeForm = SubscribeForm::factory()->published()->create();
    $payload = [
        'email' => 'limited@example.com',
        'consent' => true,
        'website' => '',
    ];

    $this->postJson(route('public.subscribe_forms.store', $subscribeForm), $payload)->assertOk();
    $this->postJson(route('public.subscribe_forms.store', $subscribeForm), $payload)->assertOk();
    $this->postJson(route('public.subscribe_forms.store', $subscribeForm), $payload)->assertOk();
    $this->postJson(route('public.subscribe_forms.store', $subscribeForm), $payload)->assertTooManyRequests();
});
