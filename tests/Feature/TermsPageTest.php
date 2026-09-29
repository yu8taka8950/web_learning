<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('guests can view the terms of service', function (): void {
    $this->get(route('terms'))
        ->assertOk()
        ->assertSee('利用規約')
        ->assertSee('第1条　適用')
        ->assertSee('第26条　お問い合わせ');
});

test('authenticated users can view the terms of service', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('terms'))
        ->assertOk()
        ->assertSee('TERMS OF SERVICE');
});

test('the terms route does not require authentication', function (): void {
    $middleware = Route::getRoutes()->getByName('terms')->gatherMiddleware();

    expect($middleware)->not->toContain('auth');
});

test('the registration screen links to the terms of service', function (): void {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('href="'.route('terms').'"', false)
        ->assertSee('href="'.route('privacy').'"', false);
});

test('the pricing screen retains subscription information and links to the terms', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('pricing'))
        ->assertOk()
        ->assertSee('¥300 / 月')
        ->assertSee('$1.99 / month')
        ->assertSee('月額サブスクリプション')
        ->assertSee('href="'.route('terms').'"', false);
});

test('the terms page links to the privacy policy', function (): void {
    $this->get(route('terms'))
        ->assertOk()
        ->assertSee('href="'.route('privacy').'"', false)
        ->assertSee('プライバシーポリシー');
});
