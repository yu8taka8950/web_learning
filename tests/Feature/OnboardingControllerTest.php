<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;

test('guest can view onboarding but cannot complete it', function () {
    $user = User::factory()->create();
    $completedAt = $user->onboarding_completed_at;

    $this->get(route('onboarding.show'))
        ->assertOk()
        ->assertSee('Web Learning Guide')
        ->assertSee('href="'.route('register').'"', false)
        ->assertSee('無料ではじめる →')
        ->assertSee('href="'.url('/').'"', false)
        ->assertDontSee('href="'.route('dashboard').'"', false)
        ->assertDontSee('スキップ →');

    $this->post(route('onboarding.complete'))->assertRedirect(route('login'));

    expect($user->fresh()->onboarding_completed_at->equalTo($completedAt))->toBeTrue();
});

test('an incomplete user is redirected from dashboard to onboarding', function () {
    $user = User::factory()->onboardingIncomplete()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.show'));
});

test('a completed user can access dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('クイック学習')
        ->assertDontSee('読むだけで、');
});

test('onboarding is a standalone guide with all five steps and images', function () {
    $user = User::factory()->onboardingIncomplete()->create();

    $this->actingAs($user)
        ->get(route('onboarding.show'))
        ->assertOk()
        ->assertDontSee('ホーム')
        ->assertDontSee('学習セット')
        ->assertSee('Web Learning')
        ->assertSee('1</span> / 5', false)
        ->assertSee('role="progressbar"', false)
        ->assertSee('読むだけで、')
        ->assertSee('学びがたまる。')
        ->assertSee('Chrome拡張')
        ->assertSee('いつも通りWebを見るだけ')
        ->assertSee('覚えたいものだけ選ぶ')
        ->assertSee('AIが問題に変える')
        ->assertSee('AI生成内容には誤りが含まれる場合があります。')
        ->assertSee('忘れる前に、もう一度')
        ->assertSeeInOrder(['3日後', '7日後', '14日後'])
        ->assertSee('翌日')
        ->assertSee('← 戻る')
        ->assertSee('次へ →')
        ->assertSee('スキップ')
        ->assertSee('Web Learningを始める →')
        ->assertSee(Vite::asset('resources/images/onboarding/sample1.png'), false)
        ->assertSee(Vite::asset('resources/images/onboarding/sample2.png'), false)
        ->assertSee(Vite::asset('resources/images/onboarding/sample3.png'), false)
        ->assertSee(Vite::asset('resources/images/onboarding/sample4.png'), false)
        ->assertSee(Vite::asset('resources/images/onboarding/sample5.png'), false)
        ->assertSee('action="'.route('onboarding.complete').'"', false)
        ->assertSee('name="_token"', false);
});

test('completing onboarding updates only the authenticated user and redirects to dashboard', function () {
    $user = User::factory()->onboardingIncomplete()->create();
    $otherUser = User::factory()->onboardingIncomplete()->create();

    $response = $this->actingAs($user)->post(route('onboarding.complete'));

    $response->assertRedirect(route('dashboard'));
    expect($user->fresh()->onboarding_completed_at)->not->toBeNull()
        ->and($otherUser->fresh()->onboarding_completed_at)->toBeNull();
});

test('skipping onboarding marks it complete', function () {
    $user = User::factory()->onboardingIncomplete()->create();

    $this->actingAs($user)
        ->post(route('onboarding.complete'), ['action' => 'skip'])
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->onboarding_completed_at)->not->toBeNull();
});

test('a completed user can revisit onboarding from the navigation', function () {
    $user = User::factory()->create();
    $completedAt = $user->onboarding_completed_at;

    $this->actingAs($user)
        ->get(route('onboarding.show'))
        ->assertOk()
        ->assertSee('Web Learning Guide')
        ->assertSee('href="'.route('dashboard').'"', false)
        ->assertSee('Dashboardへ戻る →')
        ->assertDontSee('action="'.route('onboarding.complete').'"', false)
        ->assertDontSee('ホーム')
        ->assertDontSee('使い方');

    expect($user->fresh()->onboarding_completed_at->equalTo($completedAt))->toBeTrue();
});

test('dashboard navigation keeps the guide link without restoring the onboarding hero', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('クイック学習')
        ->assertSee('使い方')
        ->assertSee('href="'.route('onboarding.show').'"', false)
        ->assertDontSee('読むだけで、');
});

test('viewing onboarding does not update completion or call external APIs', function () {
    Http::preventStrayRequests();
    $user = User::factory()->onboardingIncomplete()->create();

    $this->actingAs($user)->get(route('onboarding.show'))->assertOk();

    expect($user->fresh()->onboarding_completed_at)->toBeNull();
});
