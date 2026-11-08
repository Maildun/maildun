<?php

use App\Models\SubscribeForm;

test('application pages may only be framed by the application itself', function () {
    $response = $this->get(route('login'))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');

    expect($response->headers->all('Content-Security-Policy'))->toBe(["frame-ancestors 'self'"]);
});

test('the hosted subscribe form can be embedded on other sites', function () {
    $subscribeForm = SubscribeForm::factory()->published()->create();

    $this->get(route('public.subscribe_forms.show', ['subscribeForm' => $subscribeForm, 'embed' => 1]))
        ->assertOk()
        ->assertHeaderMissing('X-Frame-Options')
        ->assertHeaderMissing('Content-Security-Policy');
});

test('a page that states its own framing policy keeps it', function () {
    $response = $this->get(route('public.web_view.test'))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY');

    expect($response->headers->all('Content-Security-Policy'))->toHaveCount(1)
        ->and($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'");
});
