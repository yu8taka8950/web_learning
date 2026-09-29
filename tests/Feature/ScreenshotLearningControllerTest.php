<?php

use App\Models\ExtensionQuizDraft;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('services.gemini.key', 'gemini-test-key');
    config()->set('services.gemini.model', 'gemini-test-model');
});

test('guests cannot use screenshot learning', function () {
    $this->get(route('screenshot-learning.create'))->assertRedirect(route('login'));
    $this->post(route('screenshot-learning.analyze'))->assertRedirect(route('login'));
});

test('the screenshot learning page presents the upload experience and tips', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('screenshot-learning.create'));

    $response
        ->assertOk()
        ->assertSee('IMAGE LEARNING')
        ->assertSee('スクリーンショットから学習')
        ->assertSee('アップロードのポイント')
        ->assertSee('ここに画像をドラッグ＆ドロップ')
        ->assertSee('ファイルを選択')
        ->assertSee('画像を解析する')
        ->assertSee('accept="image/jpeg,image/png,image/webp"', false)
        ->assertSee('enctype="multipart/form-data"', false)
        ->assertSee('JPG / PNG / WebP')
        ->assertSee('dragover.prevent', false)
        ->assertSee('sample10-', false)
        ->assertSee('alt="スクリーンショットから学習するイメージ"', false)
        ->assertSee('lg:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]', false)
        ->assertSee('lg:grid-cols-[minmax(0,1.65fr)_minmax(15rem,1fr)]', false)
        ->assertSee('lg:w-[145px]', false)
        ->assertSee('[text-wrap:pretty]', false)
        ->assertSee('min-h-[19rem]', false)
        ->assertSee('lg:py-8', false)
        ->assertDontSee('rotate-3', false)
        ->assertDontSee('sm:grid-cols-[minmax(10rem,0.8fr)_minmax(0,1.5fr)]', false)
        ->assertDontSee('overflow-hidden', false);

    $content = $response->getContent();
    expect(strpos($content, 'IMAGE LEARNING'))->toBeLessThan(strpos($content, 'sample10-'))
        ->and(strpos($content, 'sample10-'))->toBeLessThan(strpos($content, '画像をアップロード'))
        ->and(strpos($content, '画像をアップロード'))->toBeLessThan(strpos($content, 'アップロードのポイント'));
});

test('the upload must be a supported image no larger than ten megabytes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('screenshot-learning.analyze'), [
        'screenshot' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
    ])->assertSessionHasErrors(['screenshot']);

    $this->actingAs($user)->post(route('screenshot-learning.analyze'), [
        'screenshot' => UploadedFile::fake()->image('large.png')->size(10241),
    ])->assertSessionHasErrors(['screenshot']);
});

test('Gemini receives the image directly and analysis creates an owned screenshot draft', function () {
    Storage::fake('local');
    fakeScreenshotAnalysis();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('screenshot-learning.analyze'), [
        'screenshot' => UploadedFile::fake()->image('architecture.png'),
    ]);
    $draft = ExtensionQuizDraft::query()->firstOrFail();

    $response->assertSessionHasNoErrors()->assertRedirect(route('screenshot-learning.candidates', $draft->token));
    expect($draft->user_id)->toBe($user->id)
        ->and($draft->source_type)->toBe('screenshot')
        ->and($draft->source_url)->toBeNull()
        ->and($draft->selected_terms)->toHaveCount(2)
        ->and($draft->generated_questions)->toBe([]);
    Storage::disk('local')->assertExists($draft->source_image_path);
    $this->assertDatabaseHas('ai_usage_logs', ['user_id' => $user->id, 'feature' => 'screenshot_analysis', 'success' => true]);

    Http::assertSent(function (Request $request): bool {
        $parts = $request->data()['contents'][0]['parts'];

        return $request->hasHeader('x-goog-api-key', 'gemini-test-key')
            && isset($parts[1]['inlineData']['data'])
            && $parts[1]['inlineData']['mimeType'] === 'image/png';
    });
});

test('an analysis failure removes the privately stored image and creates no draft', function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'busy']], 503)]);
    $user = User::factory()->create();

    $this->actingAs($user)->from(route('screenshot-learning.create'))->post(route('screenshot-learning.analyze'), [
        'screenshot' => UploadedFile::fake()->image('notes.png'),
    ])->assertRedirect(route('screenshot-learning.create'))->assertSessionHasErrors(['screenshot']);

    expect(ExtensionQuizDraft::query()->exists())->toBeFalse();
    expect(Storage::disk('local')->allFiles('screenshots'))->toBe([]);
});

test('only the owner can view screenshot candidates', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $draft = screenshotDraft($owner);

    $this->actingAs($owner)->get(route('screenshot-learning.candidates', $draft->token))->assertOk()->assertSee('Amazon EC2');
    $this->actingAs($otherUser)->get(route('screenshot-learning.candidates', $draft->token))->assertNotFound();
});

test('selected candidates use the shared quiz generator and continue to the temporary quiz', function () {
    Http::preventStrayRequests();
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(screenshotGeminiResponse([
        'subject' => 'クラウド',
        'topic' => 'AWS・クラウド基礎',
        'questions' => [screenshotQuestion()],
    ]))]);
    $user = User::factory()->create();
    $draft = screenshotDraft($user);

    $this->actingAs($user)->post(route('screenshot-learning.generate', $draft->token), ['candidates' => [0]])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('extension-quiz-drafts.quiz', $draft->token));

    expect($draft->fresh()->selected_terms)->toHaveCount(1)
        ->and($draft->fresh()->subject)->toBe('クラウド')
        ->and($draft->fresh()->generated_questions)->toHaveCount(1)
        ->and($draft->fresh()->generated_questions[0]['input_question'])->toBe('Amazon EC2の役割を入力してください。');
    $this->assertDatabaseHas('ai_usage_logs', ['feature' => 'quiz_generation', 'success' => true]);
    Http::assertSentCount(1);
});

test('candidate selection rejects missing and manipulated indexes', function () {
    $user = User::factory()->create();
    $draft = screenshotDraft($user);

    $this->actingAs($user)->post(route('screenshot-learning.generate', $draft->token), ['candidates' => []])
        ->assertSessionHasErrors(['candidates']);
    $this->actingAs($user)->post(route('screenshot-learning.generate', $draft->token), ['candidates' => [99]])
        ->assertSessionHasErrors(['candidates']);
});

test('a completed screenshot quiz is saved with its screenshot provenance', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $draft = screenshotDraft($user, ['generated_questions' => [screenshotQuestion()]]);

    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token));
    $this->actingAs($user)->post(route('extension-quiz-drafts.grade', $draft->token), ['question_index' => 0, 'selected_option' => 'A'])->assertRedirect();
    $this->actingAs($user)->post(route('extension-quiz-drafts.save', $draft->token))->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('learning_sets', [
        'user_id' => $user->id,
        'source_type' => 'screenshot',
        'source_url' => null,
        'source_image_path' => 'screenshots/example.png',
        'title' => 'AWS構成図 - スクリーンショット学習',
        'subject' => 'クラウド',
        'topic' => 'AWS・クラウド基礎',
    ]);
    $question = $user->learningSets()->firstOrFail()->questions()->firstOrFail();
    $learningTerm = $user->learningSets()->firstOrFail()->learningTerms()->firstOrFail();
    expect($question->review_stage)->toBe(0)
        ->and($question->input_question)->toBe('Amazon EC2の役割を入力してください。')
        ->and($question->next_review_at->isSameSecond(now()->addDay()))->toBeTrue()
        ->and($question->review_count)->toBe(0)
        ->and($question->correct_review_count)->toBe(0)
        ->and($learningTerm->term)->toBe('Amazon EC2')
        ->and($learningTerm->description)->toBe('仮想サーバーを提供します。')
        ->and($learningTerm->source_type)->toBe('screenshot')
        ->and($learningTerm->source_url)->toBeNull();
});

function fakeScreenshotAnalysis(): void
{
    Http::preventStrayRequests();
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(screenshotGeminiResponse([
        'title' => 'AWS構成図',
        'candidates' => screenshotCandidates(),
    ]))]);
}

/** @param array<string, mixed> $data */
function screenshotGeminiResponse(array $data): array
{
    return [
        'candidates' => [['content' => ['parts' => [['text' => json_encode($data, JSON_UNESCAPED_UNICODE)]]]]],
        'usageMetadata' => ['promptTokenCount' => 100, 'candidatesTokenCount' => 50, 'totalTokenCount' => 150],
    ];
}

function screenshotCandidates(): array
{
    return [
        ['term' => 'Amazon EC2', 'description' => '仮想サーバーを提供します。'],
        ['term' => 'Amazon S3', 'description' => 'オブジェクトを保存します。'],
    ];
}

function screenshotQuestion(): array
{
    return [
        'term' => 'Amazon EC2', 'question' => 'EC2の役割は何ですか？', 'input_question' => 'Amazon EC2の役割を入力してください。',
        'option_a' => '仮想サーバー', 'option_b' => 'DNS', 'option_c' => 'メール', 'option_d' => '監視',
        'correct_option' => 'A', 'explanation' => 'EC2は仮想サーバーを提供します。',
    ];
}

/** @param array<string, mixed> $overrides */
function screenshotDraft(User $user, array $overrides = []): ExtensionQuizDraft
{
    return $user->extensionQuizDrafts()->create(array_replace([
        'token' => (string) Str::uuid(), 'source_type' => 'screenshot', 'source_title' => 'AWS構成図', 'subject' => 'クラウド', 'topic' => 'AWS・クラウド基礎',
        'source_url' => null, 'source_image_path' => 'screenshots/example.png',
        'selected_terms' => screenshotCandidates(), 'generated_questions' => [], 'expires_at' => now()->addDay(),
    ], $overrides));
}
