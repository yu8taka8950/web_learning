<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->extensionToken = 'extension-page-token';
    $this->user->extensionAccessTokens()->create(['token_hash' => hash('sha256', $this->extensionToken)]);
    config()->set('services.gemini.key', 'gemini-test-key');
    config()->set('services.gemini.model', 'gemini-3.5-flash-lite');
});

test('an extension access token can analyze a page and records usage for its owner', function () {
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => json_encode(['terms' => [['term' => 'DNS', 'description' => '名前解決の仕組みです。']]], JSON_UNESCAPED_UNICODE)]]]]],
    ])]);

    $this->withToken($this->extensionToken)
        ->postJson(route('extension.analyze-page'), validPageAnalysisPayload())
        ->assertOk()
        ->assertJsonPath('terms.0.term', 'DNS');

    $this->assertDatabaseHas('ai_usage_logs', [
        'user_id' => $this->user->id,
        'feature' => 'web_term_detection',
        'success' => true,
    ]);
});

test('analyze rejects missing and retired fixed tokens', function () {
    $this->postJson(route('extension.analyze-page'), validPageAnalysisPayload())->assertUnauthorized();
    $this->withToken('web_learning_test_2026')->postJson(route('extension.analyze-page'), validPageAnalysisPayload())->assertUnauthorized();
});

/** @return array{title: string, url: string, content: string} */
function validPageAnalysisPayload(): array
{
    return [
        'title' => 'DNSの基本',
        'url' => 'https://example.com/dns',
        'content' => 'DNSはドメイン名をIPアドレスへ変換する名前解決の仕組みです。',
    ];
}
