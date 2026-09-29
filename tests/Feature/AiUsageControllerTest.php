<?php

use App\Models\AiUsageLog;
use App\Models\User;

test('guests are redirected to the login screen for AI usage', function () {
    $response = $this->get('/ai-usage');

    $response->assertRedirect(route('login'));
});

test('authenticated users can view AI usage', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/ai-usage');

    $response->assertOk();
});

test('shared dictionary generation is labeled as term explanation usage', function (): void {
    $user = User::factory()->create();
    AiUsageLog::query()->create([
        'user_id' => $user->id,
        'provider' => 'gemini',
        'model' => 'gemini-test',
        'feature' => 'term_explanation',
        'input_characters' => 100,
        'success' => true,
        'http_status' => 200,
    ]);

    $this->actingAs($user)->get('/ai-usage')
        ->assertOk()
        ->assertSee('用語解説');
});

test('AI usage displays today and all-time totals for all logs', function () {
    AiUsageLog::query()->create(['provider' => 'gemini', 'model' => 'gemini-3.5-flash-lite', 'feature' => 'web_term_detection', 'input_characters' => 2138, 'input_tokens' => 1208, 'output_tokens' => 223, 'total_tokens' => 1431, 'success' => true, 'http_status' => 200]);
    AiUsageLog::query()->create(['provider' => 'gemini', 'model' => 'gemini-3.5-flash-lite', 'feature' => 'quiz_generation', 'input_characters' => 2100, 'input_tokens' => 1202, 'output_tokens' => 223, 'total_tokens' => 1425, 'success' => false, 'http_status' => 429, 'error_message' => 'Rate limit exceeded.']);
    AiUsageLog::query()->create(['provider' => 'gemini', 'model' => 'gemini-3.5-flash-lite', 'feature' => 'learning_chat', 'input_characters' => 300, 'input_tokens' => 100, 'output_tokens' => 200, 'total_tokens' => 300, 'success' => true, 'http_status' => 200]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/ai-usage');

    $response
        ->assertOk()
        ->assertViewHas('todayUsage', [
            'apiCalls' => 3,
            'successfulCalls' => 2,
            'failedCalls' => 1,
            'inputCharacters' => 4538,
            'inputTokens' => 2510,
            'outputTokens' => 646,
            'totalTokens' => 3156,
        ])
        ->assertViewHas('allTimeUsage', [
            'apiCalls' => 3,
            'totalTokens' => 3156,
        ])
        ->assertSee('4,538文字')
        ->assertSee('2,510')
        ->assertSee('646')
        ->assertSee('3,156')
        ->assertSee('Web用語検出')
        ->assertSee('AI問題生成')
        ->assertSee('AI質問')
        ->assertSee('成功')
        ->assertSee('失敗')
        ->assertSee('429');
});
