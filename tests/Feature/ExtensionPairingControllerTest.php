<?php

use App\Models\ExtensionAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

test('an authenticated user can approve and claim a one-time extension pairing', function () {
    $pairing = startPairing($this);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('extension.connect.approve', $pairing['pairing_id']), ['pairing_secret' => $pairing['pairing_secret']])
        ->assertOk()
        ->assertSee('接続しました。');

    $claim = $this->postJson(route('extension.pairings.claim', $pairing['pairing_id']), ['pairing_secret' => $pairing['pairing_secret']]);

    $claim->assertOk();
    $plainTextToken = $claim->json('access_token');

    expect($plainTextToken)->toStartWith('wlcap_');
    $this->assertDatabaseHas('extension_access_tokens', [
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $plainTextToken),
    ]);
    expect(ExtensionAccessToken::query()->firstOrFail()->token_hash)->not->toBe($plainTextToken);

    $this->postJson(route('extension.pairings.claim', $pairing['pairing_id']), ['pairing_secret' => $pairing['pairing_secret']])
        ->assertNotFound();
});

test('pairing approval requires the secret that is only delivered in the URL fragment', function () {
    $pairing = startPairing($this);
    $anotherUser = User::factory()->create();

    $this->actingAs($anotherUser)
        ->post(route('extension.connect.approve', $pairing['pairing_id']), ['pairing_secret' => str_repeat('a', 64)])
        ->assertNotFound();

    expect(ExtensionAccessToken::query()->count())->toBe(0);
});

test('an authenticated pairing page submits approval automatically without rendering a connect button', function () {
    $pairing = startPairing($this);

    $this->actingAs(User::factory()->create())
        ->get(route('extension.connect.show', $pairing['pairing_id']))
        ->assertOk()
        ->assertSee('pairingApprovalForm')
        ->assertSee('pairingApprovalForm.requestSubmit()')
        ->assertDontSee('Chrome拡張機能を接続する');
});

test('a guest is redirected to login and pairing approval is submitted automatically after login', function () {
    $pairing = startPairing($this);

    $this->get(route('extension.connect.show', $pairing['pairing_id']))
        ->assertOk()
        ->assertSee("window.location.replace('".route('extension.connect.authorize', $pairing['pairing_id'])."')", false)
        ->assertDontSee($pairing['pairing_secret']);

    $this->get(route('extension.connect.authorize', $pairing['pairing_id']))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('extension.connect.authorize', $pairing['pairing_id']))
        ->assertOk()
        ->assertSee('pairingApprovalForm')
        ->assertDontSee('Chrome拡張機能を接続する');
});

test('expired pairings cannot be approved or claimed', function () {
    $pairing = startPairing($this);
    Cache::forget('extension-pairing:'.$pairing['pairing_id']);

    $this->actingAs(User::factory()->create())
        ->get(route('extension.connect.show', $pairing['pairing_id']))
        ->assertNotFound();

    $this->postJson(route('extension.pairings.claim', $pairing['pairing_id']), ['pairing_secret' => $pairing['pairing_secret']])
        ->assertNotFound();
});

test('revoking an extension connection invalidates its token', function () {
    $user = User::factory()->create();
    $plainTextToken = 'wlcap_'.str_repeat('a', 48);
    $user->extensionAccessTokens()->create(['token_hash' => hash('sha256', $plainTextToken)]);

    $this->withToken($plainTextToken)->deleteJson(route('extension.connection.destroy'))->assertNoContent();
    $this->withToken($plainTextToken)->getJson(route('extension.connection.show'))->assertUnauthorized();
});

/** @return array{pairing_id: string, pairing_secret: string} */
function startPairing(object $test): array
{
    $pairingId = str_repeat('a', 64);
    $pairingSecret = str_repeat('b', 64);
    Cache::forget('extension-pairing:'.$pairingId);

    $response = $test->postJson(route('extension.pairings.start'), [
        'pairing_id' => $pairingId,
        'pairing_secret' => $pairingSecret,
    ]);

    $response->assertCreated()
        ->assertJsonPath('connect_url', route('extension.connect.show', $pairingId).'#pairing_secret='.$pairingSecret);

    return ['pairing_id' => $pairingId, 'pairing_secret' => $pairingSecret];
}
