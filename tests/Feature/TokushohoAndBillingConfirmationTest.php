<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('guests can view the specified commercial transactions act page', function (): void {
    $this->get(route('tokushoho'))
        ->assertOk()
        ->assertSee('特定商取引法に基づく表記')
        ->assertSee('月額300円')
        ->assertSee('月額1.99米ドル')
        ->assertSee('href="'.route('terms').'"', false)
        ->assertSee('href="'.route('privacy').'"', false);
});

test('authenticated users can view the specified commercial transactions act page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('tokushoho'))
        ->assertOk()
        ->assertSee('Web Learning');
});

test('the specified commercial transactions act route does not require authentication', function (): void {
    $middleware = Route::getRoutes()->getByName('tokushoho')->gatherMiddleware();

    expect($middleware)->not->toContain('auth');
});

test('the pricing page links to the specified commercial transactions act page and confirmation flow', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('pricing'))
        ->assertOk()
        ->assertSee('href="'.route('tokushoho').'"', false)
        ->assertSee('action="'.route('billing.confirm').'"', false)
        ->assertSee('月額サブスクリプション・自動更新です');
});

test('guests are redirected to login before viewing billing confirmation', function (): void {
    $this->get(route('billing.confirm', ['currency' => 'jpy']))
        ->assertRedirect(route('login'));
});

test('free users can view the Japanese yen confirmation without client supplied amounts or prices', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('billing.confirm', [
        'currency' => 'jpy',
        'amount' => '1',
        'price_id' => 'price_attacker',
    ]))
        ->assertOk()
        ->assertSee('月額300円')
        ->assertDontSee('月額1円')
        ->assertSee('name="currency" value="jpy"', false);
});

test('free users can view the United States dollar confirmation', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('billing.confirm', ['currency' => 'usd']))
        ->assertOk()
        ->assertSee('月額1.99米ドル')
        ->assertSee('name="currency" value="usd"', false);
});

test('billing confirmation rejects an unsupported currency', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->from(route('pricing'))->get(route('billing.confirm', ['currency' => 'eur']))
        ->assertRedirect(route('pricing'))
        ->assertSessionHasErrors('currency');
});

test('checkout requires both confirmations before it can start', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('billing.checkout'), ['currency' => 'jpy'])
        ->assertSessionHasErrors(['accepted_recurring', 'accepted_terms']);
});

test('checkout uses the configured Japanese yen price instead of client price fields', function (): void {
    $user = User::factory()->create();
    config(['plans.prices.jpy' => '']);

    $this->actingAs($user)->post(route('billing.checkout'), [
        'currency' => 'jpy',
        'accepted_recurring' => '1',
        'accepted_terms' => '1',
        'price_id' => 'price_attacker',
        'amount' => '1',
    ])->assertStatus(503);
});

test('checkout uses the configured United States dollar price instead of client price fields', function (): void {
    $user = User::factory()->create();
    config(['plans.prices.usd' => '']);

    $this->actingAs($user)->post(route('billing.checkout'), [
        'currency' => 'usd',
        'accepted_recurring' => '1',
        'accepted_terms' => '1',
        'price_id' => 'price_attacker',
        'amount' => '1',
    ])->assertStatus(503);
});

test('an existing Plus subscriber cannot start a duplicate checkout', function (): void {
    $user = User::factory()->create();
    $user->subscriptions()->create(['type' => 'plus', 'stripe_id' => 'sub_existing_plus', 'stripe_status' => 'active']);

    $this->actingAs($user)->post(route('billing.checkout'), [
        'currency' => 'jpy',
        'accepted_recurring' => '1',
        'accepted_terms' => '1',
    ])->assertRedirect(route('pricing'))
        ->assertSessionHas('status', '現在Plusをご利用中です。');
});

test('a subscriber in the grace period cannot start a duplicate checkout', function (): void {
    $user = User::factory()->create();
    $user->subscriptions()->create(['type' => 'plus', 'stripe_id' => 'sub_grace_period', 'stripe_status' => 'canceled', 'ends_at' => now()->addDay()]);

    $this->actingAs($user)->get(route('billing.confirm', ['currency' => 'jpy']))
        ->assertOk()
        ->assertSee('現在Plusをご利用中です')
        ->assertSee('Customer Portalを開く')
        ->assertDontSee('月額300円でPlusに申し込む');
});
