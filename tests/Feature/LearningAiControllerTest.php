<?php

use App\Models\AiUsageLog;
use App\Models\Question;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

function learningAiQuestion(User $user, array $attributes = []): Question
{
    $learningSet = $user->learningSets()->create([
        'title' => 'Linuxサービス管理',
        'source_type' => 'web',
        'source_url' => 'https://example.com/linux',
    ]);
    $learningSet->learningTerms()->create(['term' => 'systemd', 'description' => 'サービス管理の仕組み', 'source_type' => 'web', 'source_url' => 'https://example.com/linux']);

    return $learningSet->questions()->create([
        'question' => 'systemdとは何ですか？',
        'input_question' => 'systemdを説明してください。',
        'option_a' => 'サービス管理の仕組み', 'option_b' => 'テキストエディタ', 'option_c' => 'ブラウザ', 'option_d' => 'データベース',
        'correct_option' => 'A', 'explanation' => 'systemdはLinuxのシステムやサービスを管理する仕組みです。',
        ...$attributes,
    ]);
}

function learningAiResponse(string $answer = 'systemdはサービス管理の仕組みで、systemctlは操作するコマンドです。'): array
{
    return ['candidates' => [['content' => ['parts' => [['text' => $answer]]]]], 'usageMetadata' => ['promptTokenCount' => 12, 'candidatesTokenCount' => 18, 'totalTokenCount' => 30]];
}

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 10:00:00', 'Asia/Tokyo'));
    config(['services.gemini.key' => 'test-key', 'services.gemini.model' => 'gemini-test']);
});

test('guests cannot post learning AI questions', function (): void {
    $this->postJson(route('learning-ai.ask'), ['question_id' => 1, 'question' => '質問です'])
        ->assertUnauthorized();
});

test('a learning screen keeps AI help inside the hidden solution without fixed term actions', function (): void {
    $user = User::factory()->create();
    $question = learningAiQuestion($user);

    $this->actingAs($user)->get(route('learning-sets.quiz', $question->learningSet))
        ->assertSee('systemd')
        ->assertDontSee('この学習について')
        ->assertDontSee('この学習の用語')
        ->assertSee('AIに質問する')
        ->assertSee('data-selection-toolbar', false)
        ->assertSee('ChatGPTに聞く ↗')
        ->assertSee('本日の残り 3 / 3回')
        ->assertSee('AIによる回答です。内容に誤りが含まれる場合があります。')
        ->assertDontSee('test-key');
});

test('an owned question receives a Gemini answer and records one successful learning chat', function (): void {
    Http::preventStrayRequests();
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(learningAiResponse())]);
    $user = User::factory()->create();
    $question = learningAiQuestion($user);

    $this->actingAs($user)->postJson(route('learning-ai.ask'), ['question_id' => $question->id, 'question' => 'systemctlとの違いは？'])
        ->assertOk()
        ->assertJsonPath('answer', 'systemdはサービス管理の仕組みで、systemctlは操作するコマンドです。')
        ->assertJsonPath('remaining', 2);

    $this->assertDatabaseHas('ai_usage_logs', ['user_id' => $user->id, 'feature' => 'learning_chat', 'success' => true]);
    Http::assertSent(fn ($request): bool => str_contains($request['contents'][0]['parts'][0]['text'], $question->question)
        && str_contains($request['contents'][0]['parts'][0]['text'], $question->explanation)
        && str_contains($request['contents'][0]['parts'][0]['text'], 'Linuxサービス管理'));
});

test('a user cannot request another users question context', function (): void {
    $user = User::factory()->create();
    $otherQuestion = learningAiQuestion(User::factory()->create());

    $this->actingAs($user)->postJson(route('learning-ai.ask'), ['question_id' => $otherQuestion->id, 'question' => '質問です'])
        ->assertNotFound();
});

test('learning AI validates empty and overlength questions before calling Gemini', function (string $questionText, string $errorKey): void {
    Http::preventStrayRequests();
    $user = User::factory()->create();
    $question = learningAiQuestion($user);

    $this->actingAs($user)->postJson(route('learning-ai.ask'), ['question_id' => $question->id, 'question' => $questionText])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$errorKey]);

    Http::assertNothingSent();
})->with([
    'empty' => ['　　', 'question'],
    'overlength' => [str_repeat('あ', 501), 'question'],
]);

test('the fourth successful learning AI question is rejected without calling Gemini', function (): void {
    Http::preventStrayRequests();
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(learningAiResponse())]);
    $user = User::factory()->create();
    $question = learningAiQuestion($user);

    foreach (range(1, 3) as $number) {
        RateLimiter::clear('learning-chat:'.$user->id);
        $this->actingAs($user)->postJson(route('learning-ai.ask'), ['question_id' => $question->id, 'question' => "質問{$number}"])->assertOk();
    }
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(learningAiResponse())]);

    $this->actingAs($user)->postJson(route('learning-ai.ask'), ['question_id' => $question->id, 'question' => '4回目です'])
        ->assertTooManyRequests()
        ->assertJsonPath('message', '本日のAI質問を使い切りました。')
        ->assertJsonPath('remaining', 0);

    expect(AiUsageLog::query()->where('feature', 'learning_chat')->where('success', true)->count())->toBe(3);
    Http::assertNothingSent();
});

test('a failed Gemini request does not consume the daily learning AI limit', function (): void {
    Http::preventStrayRequests();
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'failed']], 500)]);
    $user = User::factory()->create();
    $question = learningAiQuestion($user);

    $this->actingAs($user)->postJson(route('learning-ai.ask'), ['question_id' => $question->id, 'question' => '質問です'])
        ->assertStatus(502);

    expect(AiUsageLog::query()->where('feature', 'learning_chat')->where('success', false)->count())->toBe(1);
    $this->actingAs($user)->get(route('learning-sets.quiz', $question->learningSet))->assertSee('本日の残り 3 / 3回');
});

test('learning AI usage is independent per user and resets on the next application day', function (): void {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $question = learningAiQuestion($firstUser);
    AiUsageLog::query()->create(['user_id' => $firstUser->id, 'provider' => 'gemini', 'model' => 'gemini-test', 'feature' => 'learning_chat', 'input_characters' => 1, 'success' => true, 'http_status' => 200]);

    $this->actingAs($firstUser)->get(route('learning-sets.quiz', $question->learningSet))->assertSee('本日の残り 2 / 3回');
    $secondQuestion = learningAiQuestion($secondUser);
    $this->actingAs($secondUser)->get(route('learning-sets.quiz', $secondQuestion->learningSet))->assertSee('本日の残り 3 / 3回');

    $this->travelTo(CarbonImmutable::parse('2026-09-11 10:00:00', 'Asia/Tokyo'));
    $this->actingAs($firstUser)->get(route('learning-sets.quiz', $question->learningSet))->assertSee('本日の残り 3 / 3回');
});
