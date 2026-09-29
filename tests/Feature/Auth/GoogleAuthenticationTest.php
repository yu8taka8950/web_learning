<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

test('the Google redirect route sends guests to the provider', function () {
    Socialite::fake('google');

    $this->get(route('auth.google.redirect'))
        ->assertRedirect('https://socialite.fake/google/authorize');
});

test('a new verified Google user is created without a password and starts onboarding', function () {
    Socialite::fake('google', googleUser());

    $response = $this->get(route('auth.google.callback'));
    $user = User::query()->sole();

    $response->assertRedirect(route('onboarding.show'));
    $this->assertAuthenticatedAs($user);
    expect($user->google_id)->toBe('google-user-id')
        ->and($user->email)->toBe('google-user@example.com')
        ->and($user->password)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->onboarding_completed_at)->toBeNull();
});

test('an existing Google user logs in without creating another user', function () {
    $user = User::factory()->create([
        'google_id' => 'google-user-id',
        'email' => 'google-user@example.com',
        'password' => null,
    ]);
    Socialite::fake('google', googleUser());

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('users', 1);
});

test('a successful Google login regenerates the session', function () {
    Socialite::fake('google', googleUser());
    $this->withSession(['oauth-login' => true]);
    $previousSessionId = session()->getId();

    $this->get(route('auth.google.callback'));

    expect(session()->getId())->not->toBe($previousSessionId);
});

test('a password user with the same email is not automatically linked to Google', function () {
    $user = User::factory()->create([
        'email' => 'google-user@example.com',
        'google_id' => null,
        'password' => Hash::make('password'),
    ]);
    Socialite::fake('google', googleUser());

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    $this->assertDatabaseCount('users', 1);
    expect($user->fresh()->google_id)->toBeNull();
});

test('an unverified Google email is not marked as verified', function () {
    Socialite::fake('google', googleUser(['email_verified' => false]));

    $this->get(route('auth.google.callback'));

    expect(User::query()->sole()->email_verified_at)->toBeNull();
});

test('a Google callback without an email returns a safe login error', function () {
    Socialite::fake('google', googleUser(['email' => null]));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
});

test('a failed Google callback returns a safe login error', function () {
    Socialite::fake('google', function (): void {
        throw new RuntimeException('Google callback failed');
    });

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('the login and registration screens offer Google authentication', function () {
    config(['services.google.login_enabled' => true]);

    $this->get(route('login'))
        ->assertSee('Googleで続ける')
        ->assertSee('href="'.route('auth.google.redirect').'"', false);

    $this->get(route('register'))
        ->assertSee('Googleで続ける')
        ->assertSee('href="'.route('auth.google.redirect').'"', false);
});

test('the login and registration screens hide Google authentication when disabled', function () {
    config(['services.google.login_enabled' => false]);

    $this->get(route('login'))
        ->assertDontSee('Googleで続ける')
        ->assertDontSee('href="'.route('auth.google.redirect').'"', false)
        ->assertSee('メールアドレス')
        ->assertSee('ログイン');

    $this->get(route('register'))
        ->assertDontSee('Googleで続ける')
        ->assertDontSee('href="'.route('auth.google.redirect').'"', false)
        ->assertSee('メールアドレス')
        ->assertSee('アカウントを作成');
});

test('a Google-only user does not see password-based profile actions', function () {
    $user = User::factory()->create(['password' => null]);

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertDontSee('Update Password')
        ->assertDontSee('Delete Account');
});

function googleUser(array $attributes = []): GoogleUser
{
    return GoogleUser::fake([
        'id' => 'google-user-id',
        'name' => 'Google User',
        'email' => 'google-user@example.com',
        'email_verified' => true,
        ...$attributes,
    ]);
}
