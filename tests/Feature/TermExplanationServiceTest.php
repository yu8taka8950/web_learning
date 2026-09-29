<?php

use App\Models\LearningSet;
use App\Models\TermExplanation;
use App\Models\User;
use App\Services\TermExplanationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('services.gemini.key', 'gemini-test-key');
    config()->set('services.gemini.model', 'gemini-test-model');
    config()->set('services.gemini.term_explanation_daily_limit', 20);
});

function sharedDictionaryLearningSet(User $user, string $subject = '宅建'): LearningSet
{
    return $user->learningSets()->create([
        'title' => '個人の教材タイトル',
        'subject' => $subject,
        'topic' => '宅建業法',
        'source_type' => 'web',
        'source_url' => 'https://private.example/source',
    ]);
}

function fakeTermExplanation(string $explanation = '媒介契約とは、不動産の取引について宅建業者へ仲介を依頼する契約です。'): void
{
    Http::preventStrayRequests();
    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['explanation' => $explanation], JSON_UNESCAPED_UNICODE)]]]]],
            'usageMetadata' => ['promptTokenCount' => 40, 'candidatesTokenCount' => 30, 'totalTokenCount' => 70],
        ]),
    ]);
}

test('a cache miss generates and stores only a general shared explanation once', function (): void {
    $user = User::factory()->create();
    $learningSet = sharedDictionaryLearningSet($user);
    fakeTermExplanation();

    $explanation = app(TermExplanationService::class)->ensure($user, $learningSet, '  媒介契約  ');

    expect($explanation)->not->toBeNull()
        ->and($explanation->subject_key)->toBe('宅建')
        ->and($explanation->normalized_term)->toBe('媒介契約')
        ->and($explanation->explanation)->toBe('不動産の取引について宅建業者へ仲介を依頼する契約です。')
        ->and($explanation->provider)->toBe('gemini');
    $this->assertDatabaseHas('term_explanations', [
        'subject_key' => '宅建',
        'normalized_term' => '媒介契約',
        'subject_label' => '宅建',
        'topic_label' => '宅建業法',
    ]);
    $this->assertDatabaseHas('ai_usage_logs', [
        'user_id' => $user->id,
        'feature' => 'term_explanation',
        'success' => true,
    ]);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        $prompt = $request->data()['contents'][0]['parts'][0]['text'];

        return str_contains($prompt, '媒介契約')
            && str_contains($prompt, '宅建業法')
            && str_contains($prompt, '「○○とは」「○○は」で始めない')
            && str_contains($prompt, '60〜140文字程度')
            && str_contains($prompt, '具体例は理解に本当に必要な場合だけ')
            && ! str_contains($prompt, '個人の教材タイトル')
            && ! str_contains($prompt, 'private.example');
    });
});

test('an existing shared explanation is reused across users without Gemini or a usage log', function (): void {
    $secondUser = User::factory()->create();
    TermExplanation::factory()->create([
        'subject_key' => 'linuc',
        'subject_label' => 'LinuC',
        'normalized_term' => 'systemd',
        'display_term' => 'systemd',
        'explanation' => '共有の一般説明です。',
    ]);
    Http::preventStrayRequests();

    $result = app(TermExplanationService::class)->ensure($secondUser, sharedDictionaryLearningSet($secondUser, 'LinuC'), 'SYSTEMD');

    expect($result?->explanation)->toBe('共有の一般説明です。');
    $this->assertDatabaseCount('term_explanations', 1);
    $this->assertDatabaseCount('ai_usage_logs', 0);
    Http::assertNothingSent();
});

test('the same term may have separate explanations for separate subjects', function (): void {
    TermExplanation::factory()->create([
        'subject_key' => 'ネットワーク',
        'subject_label' => 'ネットワーク',
        'normalized_term' => 'port',
        'display_term' => 'port',
    ]);
    TermExplanation::factory()->create([
        'subject_key' => 'プログラミング',
        'subject_label' => 'プログラミング',
        'normalized_term' => 'port',
        'display_term' => 'port',
    ]);

    expect(TermExplanation::query()->where('normalized_term', 'port')->count())->toBe(2);
});

test('a Gemini failure never prevents the personal term fallback from being saved', function (): void {
    $user = User::factory()->create();
    $learningSet = sharedDictionaryLearningSet($user);
    $question = $learningSet->questions()->create([
        'question' => '媒介契約について答えてください。',
        'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D',
        'correct_option' => 'A',
        'explanation' => '媒介契約は不動産取引で利用される契約です。',
    ]);
    Http::preventStrayRequests();
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'busy']], 503)]);

    $this->actingAs($user)->postJson(route('learning-terms.store'), [
        'question_id' => $question->id,
        'selected_text' => '媒介契約',
        'source_field' => 'explanation',
    ])->assertCreated()->assertJsonPath('created', true);

    $this->assertDatabaseHas('learning_terms', [
        'learning_set_id' => $learningSet->id,
        'term' => '媒介契約',
        'description' => '媒介契約は不動産取引で利用される契約です。',
    ]);
    $this->assertDatabaseCount('term_explanations', 0);
    $this->assertDatabaseHas('ai_usage_logs', ['feature' => 'term_explanation', 'success' => false]);
});

test('a generated term explanation is copied to the saved personal term', function (): void {
    $user = User::factory()->create();
    $learningSet = sharedDictionaryLearningSet($user);
    $question = $learningSet->questions()->create([
        'question' => '媒介契約について答えてください。',
        'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D',
        'correct_option' => 'A',
        'explanation' => '媒介契約は不動産取引で利用される契約です。',
    ]);
    fakeTermExplanation();

    $this->actingAs($user)->postJson(route('learning-terms.store'), [
        'question_id' => $question->id,
        'selected_text' => '媒介契約',
        'source_field' => 'explanation',
    ])->assertCreated();

    $this->assertDatabaseHas('learning_terms', [
        'learning_set_id' => $learningSet->id,
        'term' => '媒介契約',
        'description' => '不動産の取引について宅建業者へ仲介を依頼する契約です。',
    ]);
});
