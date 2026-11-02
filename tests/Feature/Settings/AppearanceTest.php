<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('appearance page is displayed', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/appearance'));
});

test('the saved color mode is rendered on the page before scripts run', function (?string $cookie, bool $rendersDark, string $sharedMode) {
    $request = $this->actingAs(User::factory()->create());

    if ($cookie !== null) {
        $request = $request->withUnencryptedCookie('appearance', $cookie);
    }

    $response = $request->get(route('appearance.edit'))->assertOk();

    expect(str_contains($response->getContent(), '<html lang="en" class="dark">'))->toBe($rendersDark);
    $response->assertSee("const appearance = '{$sharedMode}';", false);
})->with([
    'dark' => ['dark', true, 'dark'],
    'light' => ['light', false, 'light'],
    'system' => ['system', false, 'system'],
    'no cookie yet' => [null, false, 'system'],
]);
