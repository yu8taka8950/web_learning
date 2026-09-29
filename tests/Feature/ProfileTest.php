<?php

use App\Models\ExtensionAccessToken;
use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk()
        ->assertSee('Chrome拡張機能の接続')
        ->assertSee('PopupでLearning ModeをONにすると')
        ->assertSee('Web Learningをアプリとして使う')
        ->assertSee('x-data="pwaInstall"', false)
        ->assertSee('アプリをインストール')
        ->assertSee('ホーム画面に追加')
        ->assertDontSee('接続トークン')
        ->assertDontSee('接続状態：');
});

test('user can issue a one-time extension token without storing the raw token', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('profile.extension-token.create'));
    $rawToken = $response->getSession()->get('extension-token');

    $response->assertRedirect(route('profile.edit'));
    expect($rawToken)->toStartWith('wlcap_');
    $stored = ExtensionAccessToken::query()->where('user_id', $user->id)->firstOrFail();
    expect($stored->token_hash)->not->toBe($rawToken)->and($stored->token_hash)->toBe(hash('sha256', $rawToken));
    $this->actingAs($user)->get(route('profile.edit'))->assertDontSee($rawToken);
});

test('user can revoke only their own extension token', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $user->extensionAccessTokens()->create(['token_hash' => hash('sha256', 'user-token')]);
    $otherUser->extensionAccessTokens()->create(['token_hash' => hash('sha256', 'other-token')]);

    $this->actingAs($user)->delete(route('profile.extension-token.destroy'))->assertRedirect(route('profile.edit'));
    expect($user->extensionAccessTokens()->exists())->toBeFalse()->and($otherUser->extensionAccessTokens()->exists())->toBeTrue();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('userDeletion', 'password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});
