<?php

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('guests can read the appropriate legal notices on the about page', function () {
    config()->set('version.current', '1.2.3');
    config()->set('attribution.source_url', 'https://git.example.com/fork/maildun');

    $response = $this->get(route('about'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('about')
        ->where('copyright', 'Maildun, Copyright (C) 2026 Dunn')
        ->where('version', '1.2.3')
        ->where('sourceUrl', 'https://git.example.com/fork/maildun')
    );
});

test('signed-in users can read the about page', function () {
    $response = $this
        ->actingAs(User::factory()->create())
        ->get(route('about'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('about')
        ->where('copyright', 'Maildun, Copyright (C) 2026 Dunn')
    );
});

test('the about page links the bundled frontend licenses the build wrote', function () {
    $publicPath = storage_path('framework/testing/about-'.Str::random(8));
    File::ensureDirectoryExists($publicPath.'/build');
    File::put($publicPath.'/build/third-party-licenses.txt', '# Licenses');
    $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($publicPath));
    $this->app->usePublicPath($publicPath);
    $this->withoutVite();

    $response = $this->get(route('about'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('bundledLicensesUrl', url('/build/third-party-licenses.txt'))
    );
});

test('the about page leaves out the bundled frontend licenses when the build wrote none', function () {
    $publicPath = storage_path('framework/testing/about-'.Str::random(8));
    File::ensureDirectoryExists($publicPath);
    $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($publicPath));
    $this->app->usePublicPath($publicPath);
    $this->withoutVite();

    $response = $this->get(route('about'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('bundledLicensesUrl', null)
    );
});

test('the license and third-party notices are served as plain text', function (string $routeName, string $file) {
    $response = $this->get(route($routeName));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    expect($response->getContent())->toBe(file_get_contents(base_path($file)));
})->with([
    'license' => ['about.license', 'LICENSE'],
    'third-party notices' => ['about.notices', 'THIRD_PARTY_NOTICES.md'],
]);

test('the license and third-party notices return 404 when the file is missing', function (string $routeName, string $file) {
    File::partialMock()
        ->shouldReceive('isFile')
        ->with(base_path($file))
        ->andReturnFalse();

    $response = $this->get(route($routeName));

    $response->assertNotFound();
})->with([
    'license' => ['about.license', 'LICENSE'],
    'third-party notices' => ['about.notices', 'THIRD_PARTY_NOTICES.md'],
]);
