<?php

use App\Models\AiUsageLog;
use App\Models\User;
use App\Services\UsageLimitReachedException;
use App\Services\UsageLimitService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

afterEach(function (): void {
    Carbon::setTestNow();
});

test('free and plus plans have independent configured monthly limits', function (): void {
    $service = app(UsageLimitService::class);
    $freeUser = User::factory()->create();
    $plusUser = User::factory()->create();
    $plusUser->subscriptions()->create(['type' => 'plus', 'stripe_id' => 'sub_plus_test', 'stripe_status' => 'active']);

    expect($service->usage($freeUser, UsageLimitService::Web)['limit'])->toBe(100)
        ->and($service->usage($freeUser, UsageLimitService::Screenshot)['limit'])->toBe(100)
        ->and($service->usage($plusUser, UsageLimitService::Web)['limit'])->toBe(1000)
        ->and($service->usage($plusUser, UsageLimitService::Screenshot)['limit'])->toBe(1000);
});

test('a canceled subscription remains Plus during its grace period and becomes Free afterwards', function (): void {
    $user = User::factory()->create();
    $subscription = $user->subscriptions()->create(['type' => 'plus', 'stripe_id' => 'sub_grace_test', 'stripe_status' => 'canceled', 'ends_at' => now()->addDay()]);
    $service = app(UsageLimitService::class);

    expect($service->currentPlan($user))->toBe('plus');

    $subscription->update(['ends_at' => now()->subSecond()]);
    $user->unsetRelation('subscriptions');
    expect($service->currentPlan($user))->toBe('free');
});

test('usage counts only successful matching feature logs in the current calendar month', function (): void {
    Carbon::setTestNow('2026-09-16 12:00:00');
    $user = User::factory()->create();
    usageLog($user, 'web_term_detection', true);
    usageLog($user, 'web_term_detection', false);
    usageLog($user, 'screenshot_analysis', true);
    $service = app(UsageLimitService::class);

    expect($service->usage($user, UsageLimitService::Web)['used'])->toBe(1)
        ->and($service->usage($user, UsageLimitService::Screenshot)['used'])->toBe(1);

    Carbon::setTestNow('2026-10-01 00:00:00');
    expect($service->usage($user, UsageLimitService::Web)['used'])->toBe(0);
});

test('the one hundred and first web reservation is rejected without Gemini', function (): void {
    $user = User::factory()->create();
    foreach (range(1, 100) as $number) {
        usageLog($user, 'web_term_detection', true);
    }
    Http::preventStrayRequests();
    $token = 'quota-extension-token';
    $user->extensionAccessTokens()->create(['token_hash' => hash('sha256', $token)]);

    $this->withToken($token)->postJson(route('extension.analyze-page'), pagePayload())
        ->assertStatus(429)->assertJsonPath('code', 'usage_limit_reached')->assertJsonPath('feature', 'web_analysis')->assertJsonPath('limit', 100);
});

test('web and screenshot reservations are separate', function (): void {
    $user = User::factory()->create();
    foreach (range(1, 100) as $number) {
        usageLog($user, 'web_term_detection', true);
    }
    $service = app(UsageLimitService::class);

    expect(fn () => $service->reserve($user, UsageLimitService::Web))->toThrow(UsageLimitReachedException::class);
    $reservation = $service->reserve($user, UsageLimitService::Screenshot);
    expect($reservation['usage']['remaining'])->toBe(100);
});

test('pricing requires authentication and checkout rejects injected Stripe prices', function (): void {
    $this->get(route('pricing'))->assertRedirect(route('login'));
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('billing.checkout'), ['currency' => 'price_attacker'])->assertSessionHasErrors('currency');
});

test('pricing displays the free comparison and monthly usage details', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('pricing'));

    $response->assertSee('料金プラン')
        ->assertSee('bg-gradient-to-b from-white via-stone-50/80 to-transparent', false)
        ->assertSee('Web解析')
        ->assertSee('スクリーンショット解析')
        ->assertSee('月100回')
        ->assertSee('月1,000回')
        ->assertSee('今月の利用状況')
        ->assertSee('0 / 100')
        ->assertSee('申込み内容を確認する')
        ->assertSee('name="currency"', false)
        ->assertSee('billing/confirm');
});

test('pricing displays the plus management action for a plus subscriber', function (): void {
    $user = User::factory()->create();
    $user->subscriptions()->create(['type' => 'plus', 'stripe_id' => 'sub_pricing_plus_test', 'stripe_status' => 'active']);

    $response = $this->actingAs($user)->get(route('pricing'));

    $response->assertSee('現在のプラン:')
        ->assertSee('Plus')
        ->assertSee('プランを管理')
        ->assertSee('billing/portal')
        ->assertDontSee('billing/confirm');
});

test('billing success welcomes the user and provides Plus learning paths', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('billing.success'));

    $response->assertSee('Plusへのご加入、')
        ->assertSee('ありがとうございます')
        ->assertSee('Web解析')
        ->assertSee('スクリーンショット解析')
        ->assertSee('月1,000回')
        ->assertSee('学習量が増えても安心')
        ->assertSee('ダッシュボードへ戻る')
        ->assertSee('Web学習を始める')
        ->assertSee('Chrome拡張機能を使って学習する')
        ->assertSee(route('dashboard'), false)
        ->assertSee(route('onboarding.show'), false)
        ->assertSee(route('extension.connection.show'), false);
});

/** @return array{title: string, url: string, content: string} */
function pagePayload(): array
{
    return ['title' => 'Quota', 'url' => 'https://example.com/quota', 'content' => str_repeat('quota ', 10)];
}

function usageLog(User $user, string $feature, bool $success): void
{
    AiUsageLog::query()->create(['user_id' => $user->id, 'provider' => 'gemini', 'model' => 'gemini-test', 'feature' => $feature, 'input_characters' => 1, 'success' => $success, 'http_status' => $success ? 200 : 500]);
}
