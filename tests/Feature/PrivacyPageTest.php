<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('guests can view the privacy policy', function (): void {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('プライバシーポリシー')
        ->assertSee('第1条　取得する情報')
        ->assertSee('第11条　お問い合わせ');
});

test('authenticated users can view the privacy policy', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('privacy'))
        ->assertOk()
        ->assertSee('PRIVACY POLICY');
});

test('the privacy route does not require authentication', function (): void {
    $middleware = Route::getRoutes()->getByName('privacy')->gatherMiddleware();

    expect($middleware)->not->toContain('auth');
});

test('the registration screen links to the privacy policy', function (): void {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('href="'.route('privacy').'"', false)
        ->assertSee('プライバシーポリシー');
});
