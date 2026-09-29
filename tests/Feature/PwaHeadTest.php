<?php

use App\Models\User;

test('public and guest pages render PWA metadata', function (string $path) {
    $response = $this->get($path);

    $response
        ->assertOk()
        ->assertSee('rel="manifest" href="'.asset('manifest.webmanifest').'"', false)
        ->assertSee('<meta name="theme-color" content="#F7F5F0">', false)
        ->assertSee('rel="apple-touch-icon" href="'.asset('images/pwa/apple-touch-icon.png').'"', false)
        ->assertSee('<meta name="mobile-web-app-capable" content="yes">', false)
        ->assertSee('<meta name="apple-mobile-web-app-capable" content="yes">', false)
        ->assertSee('<meta name="apple-mobile-web-app-status-bar-style" content="default">', false);
})->with([
    'login' => '/login',
    'registration' => '/register',
    'welcome' => '/',
    'onboarding' => '/onboarding',
    'privacy policy' => '/privacy',
    'terms of service' => '/terms',
    'tokushoho' => '/tokushoho',
]);

test('application layouts use the official Web Learning document title', function () {
    $guestResponse = $this->get('/login');

    $guestResponse
        ->assertOk()
        ->assertSee('<title>Web Learning</title>', false)
        ->assertDontSee('<title>Web_Learning</title>', false);

    $user = User::factory()->create();

    $appResponse = $this->actingAs($user)->get('/dashboard');

    $appResponse
        ->assertOk()
        ->assertSee('<title>Web Learning</title>', false)
        ->assertDontSee('<title>Web_Learning</title>', false);
});
