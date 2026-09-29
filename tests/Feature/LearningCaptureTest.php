<?php

use App\Models\LearningCapture;
use App\Models\User;

function captureToken(User $user): string
{
    $token = 'wlcap_test_token';
    $user->extensionAccessTokens()->create(['token_hash' => hash('sha256', $token)]);

    return $token;
}

function capturePayload(array $overrides = []): array
{
    return array_replace_recursive(['page_title' => 'DNSの記事', 'source_url' => 'https://example.com/dns', 'terms' => [['term' => 'DNS', 'explanation' => '名前をIPアドレスへ対応させる仕組みです。']]], $overrides);
}

test('a user sees only their captures and pagination', function () {
    $user = User::factory()->create();
    LearningCapture::factory()->count(13)->for($user)->create();
    $otherCapture = LearningCapture::factory()->create();

    $response = $this->actingAs($user)->get(route('captures.index'));

    $response->assertSee('Web学習リスト')
        ->assertDontSee('見つけた学び')
        ->assertDontSee($otherCapture->page_title);
    expect(route('captures.index'))->toEndWith('/captures');
    expect($response->viewData('captures')->count())->toBe(12);
});

test('another user cannot view or delete a capture', function () {
    $capture = LearningCapture::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('captures.show', $capture))->assertNotFound();
    $this->actingAs($user)->delete(route('captures.destroy', $capture))->assertNotFound();
    $this->assertModelExists($capture);
});

test('extension token stores a capture for its owner and merges duplicate terms', function () {
    $user = User::factory()->create();
    $token = captureToken($user);

    $this->withToken($token)->postJson(route('extension.captures.store'), capturePayload())->assertCreated();
    $this->withToken($token)->postJson(route('extension.captures.store'), capturePayload(['page_title' => '新しいDNSの記事', 'terms' => [['term' => 'ＤＮＳ', 'explanation' => ''], ['term' => 'TTL', 'explanation' => '保持時間です。']]]))->assertCreated();

    $capture = $user->learningCaptures()->firstOrFail();
    expect($user->learningCaptures()->count())->toBe(1)->and($capture->terms()->count())->toBe(2)->and($capture->page_title)->toBe('新しいDNSの記事');
});

test('capture API rejects invalid tokens, user impersonation, oversized terms, and invalid URLs', function () {
    $user = User::factory()->create();
    $token = captureToken($user);
    $otherUser = User::factory()->create();

    $this->withToken('invalid')->postJson(route('extension.captures.store'), capturePayload())->assertUnauthorized();
    $this->withToken($token)->postJson(route('extension.captures.store'), capturePayload(['user_id' => $otherUser->id]))->assertCreated();
    expect($otherUser->learningCaptures()->exists())->toBeFalse();
    $this->withToken($token)->postJson(route('extension.captures.store'), capturePayload(['source_url' => 'javascript:alert(1)']))->assertUnprocessable()->assertJsonValidationErrors('source_url');
    $this->withToken($token)->postJson(route('extension.captures.store'), capturePayload(['terms' => array_fill(0, 51, ['term' => '語', 'explanation' => null])]))->assertUnprocessable()->assertJsonValidationErrors('terms');
});

test('capture URL normalization merges fragments, trailing slashes, and tracking parameters while preserving meaningful queries', function () {
    $user = User::factory()->create();
    $token = captureToken($user);

    $this->withToken($token)->postJson(route('extension.captures.store'), capturePayload(['source_url' => 'https://example.com/article/?utm_source=google&id=123#intro']))->assertCreated();
    $this->withToken($token)->postJson(route('extension.captures.store'), capturePayload(['source_url' => 'https://example.com/article?id=123&utm_medium=cpc#details']))->assertCreated();

    expect($user->learningCaptures()->count())->toBe(1);
    expect($user->learningCaptures()->firstOrFail()->normalized_source_url)->toBe('https://example.com/article?id=123');
});
