<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertOk()
        ->assertSee('<div class="w-full max-w-[420px] mx-auto">', false);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
