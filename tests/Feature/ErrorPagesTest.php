<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('authenticated not found page provides branded metadata and a dashboard action', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/this-page-does-not-exist');

    $response->assertNotFound();
    $response->assertSee('Not Found');
    $response->assertSee('Back to dashboard');
    $response->assertSee('href="'.url('/').'"', false);
    $response->assertSee('<title>Not Found · Maildun</title>', false);
    $response->assertSee('href="/assets/img/logo.svg"', false);
    $response->assertSee('href="/assets/img/logo-white.svg"', false);
    $response->assertSee('href="/apple-touch-icon.png"', false);
    $response->assertSee('class="grainy"', false);
    $response->assertSee('feTurbulence', false);
});

test('not found page omits the dashboard action for guests', function () {
    $response = $this->get('/this-page-does-not-exist');

    $response->assertNotFound();
    $response->assertDontSee('Back to dashboard');
    $response->assertDontSee('href="'.url('/').'"', false);
});

test('server error page provides a dashboard action', function () {
    Route::get('/this-page-fails', fn () => abort(500));
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/this-page-fails');

    $response->assertServerError();
    $response->assertSee('Server Error');
    $response->assertSee('Back to dashboard');
    $response->assertSee('href="'.url('/').'"', false);
});
