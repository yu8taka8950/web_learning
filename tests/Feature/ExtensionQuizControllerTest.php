<?php

use App\Models\ExtensionQuizDraft;
use App\Models\LearningTerm;
use App\Models\TermExplanation;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->extensionUser = User::factory()->create();
    $this->extensionToken = 'extension-user-token';
    $this->extensionUser->extensionAccessTokens()->create(['token_hash' => hash('sha256', $this->extensionToken)]);
    config()->set('services.gemini.key', 'gemini-test-key');
    config()->set('services.gemini.model', 'gemini-3.5-flash-lite');
});

test('returns 401 when no bearer token is provided for quiz generation', function () {
    $this->postJson(route('extension.generate-quiz'), validQuizGenerationPayload())->assertUnauthorized();
});

test('rejects the retired fixed extension token for quiz generation', function () {
    $this->withToken('web_learning_test_2026')
        ->postJson(route('extension.generate-quiz'), validQuizGenerationPayload())
        ->assertUnauthorized();
});

test('returns validation errors when no terms are selected', function () {
    $this->withToken($this->extensionToken)->postJson(route('extension.generate-quiz'), validQuizGenerationPayload(['terms' => []]))
        ->assertUnprocessable()->assertJsonValidationErrors(['terms']);
});

test('returns validation errors when more than twenty terms are selected', function () {
    $terms = array_fill(0, 21, ['term' => 'Amazon EC2', 'description' => '仮想サーバーサービスです。']);

    $this->withToken($this->extensionToken)->postJson(route('extension.generate-quiz'), validQuizGenerationPayload(['terms' => $terms]))
        ->assertUnprocessable()->assertJsonValidationErrors(['terms']);
});

test('generates a topic and twenty questions with one Gemini request', function () {
    $terms = collect(range(1, 20))->map(fn (int $number): array => ['term' => '用語'.$number, 'description' => '説明'.$number])->all();
    $questions = collect(range(1, 20))->map(fn (int $number): array => quizQuestion(['term' => '用語'.$number, 'question' => '質問'.$number]))->all();
    Http::preventStrayRequests();
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['subject' => 'LinuC', 'topic' => '20用語の基礎', 'questions' => $questions], JSON_UNESCAPED_UNICODE)]]]]]])]);

    $this->withToken($this->extensionToken)->postJson(route('extension.generate-quiz'), validQuizGenerationPayload(['terms' => $terms]))->assertOk();

    expect(ExtensionQuizDraft::query()->firstOrFail()->generated_questions)->toHaveCount(20);
    Http::assertSentCount(1);
});

test('creates a draft and returns its temporary quiz URL for a valid Gemini response', function () {
    fakeSuccessfulQuizGeneration();

    $response = $this->withToken($this->extensionToken)->postJson(route('extension.generate-quiz'), validQuizGenerationPayload());
    $draft = ExtensionQuizDraft::query()->firstOrFail();

    $response->assertOk()
        ->assertJsonPath('draft_token', $draft->token)
        ->assertJsonPath('preview_url', route('extension-quiz-drafts.quiz', $draft->token));

    expect($draft->generated_questions)->toHaveCount(1);
    expect($draft->generated_questions[0]['input_question'])->toBe('Amazon EC2の主な役割を入力してください。')
        ->and($draft->subject)->toBe('クラウド')
        ->and($draft->topic)->toBe('AWS・クラウド基礎');
    expect($draft->claimed_at)->toBeNull();
    expect($draft->user_id)->toBe($this->extensionUser->id);
    $this->assertDatabaseHas('ai_usage_logs', ['feature' => 'quiz_generation', 'success' => true, 'http_status' => 200]);
    $this->assertDatabaseCount('ai_usage_logs', 1);
    Http::assertSent(function (Request $request): bool {
        $questionSchema = $request->data()['generationConfig']['responseSchema']['properties']['questions']['items'];
        $prompt = $request->data()['contents'][0]['parts'][0]['text'];

        return $questionSchema['properties']['input_question']['type'] === 'string'
            && $request->data()['generationConfig']['responseSchema']['properties']['subject']['type'] === 'string'
            && in_array('subject', $request->data()['generationConfig']['responseSchema']['required'], true)
            && in_array('input_question', $questionSchema['required'], true)
            && in_array('explanation', $questionSchema['required'], true)
            && str_contains($prompt, '広い学習分野')
            && str_contains($prompt, '選択肢を一切見ずに')
            && str_contains($prompt, '選択肢を参照する表現を使わない')
            && str_contains($prompt, 'なぜその答えが正解なのか')
            && str_contains($prompt, '分野に合った具体例1つ')
            && str_contains($prompt, '2〜4文、150〜350文字程度')
            && str_contains($prompt, '問題文をそのまま繰り返したり')
            && str_contains($prompt, '数学では可能なら簡単な数式・数値例')
            && str_contains($prompt, '英語文法では短い英文例');
    });
});

test('an extension quiz draft shows the current users account change screen without foreign draft content', function () {
    fakeSuccessfulQuizGeneration();
    $this->withToken($this->extensionToken)->postJson(route('extension.generate-quiz'), validQuizGenerationPayload())->assertOk();
    $draft = ExtensionQuizDraft::query()->firstOrFail();
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->get(route('extension-quiz-drafts.quiz', $draft->token))
        ->assertOk()
        ->assertViewIs('extension-quiz-drafts.account-mismatch')
        ->assertSee('Web Learningのアカウントが変更されました')
        ->assertSee($otherUser->email)
        ->assertSee('このアカウントで続ける')
        ->assertSee('data-testid="reconnect-extension"', false)
        ->assertDontSee($draft->source_title)
        ->assertDontSee($draft->generated_questions[0]['question'])
        ->assertDontSee($draft->source_url)
        ->assertDontSee($this->extensionUser->name)
        ->assertDontSee($this->extensionUser->email);

    expect($draft->fresh()->user_id)->toBe($this->extensionUser->id);
});

test('an expired foreign extension quiz draft remains indistinguishable from a missing draft', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $draft = quizDraft([
        'user_id' => $owner->id,
        'expires_at' => now()->subSecond(),
    ]);

    $this->actingAs($otherUser)
        ->get(route('extension-quiz-drafts.quiz', $draft->token))
        ->assertNotFound()
        ->assertDontSee('Web Learningのアカウントが一致していません');
});

test('a reconnected users token creates a new draft without transferring the old draft', function () {
    $oldDraft = quizDraft(['user_id' => $this->extensionUser->id]);
    $newUser = User::factory()->create();
    $newToken = 'reconnected-user-token';
    $newUser->extensionAccessTokens()->create(['token_hash' => hash('sha256', $newToken)]);
    fakeSuccessfulQuizGeneration();

    $this->withToken($newToken)
        ->postJson(route('extension.generate-quiz'), validQuizGenerationPayload())
        ->assertOk();

    expect($oldDraft->fresh()->user_id)->toBe($this->extensionUser->id);
    expect(ExtensionQuizDraft::query()->whereKeyNot($oldDraft->id)->firstOrFail()->user_id)->toBe($newUser->id);
});

test('rejects a Gemini response without an input question', function () {
    $question = quizQuestion();
    unset($question['input_question']);
    Http::preventStrayRequests();
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['subject' => 'クラウド', 'topic' => 'AWS・クラウド基礎', 'questions' => [$question]], JSON_UNESCAPED_UNICODE)]]]]]])]);

    $this->withToken($this->extensionToken)->postJson(route('extension.generate-quiz'), validQuizGenerationPayload())
        ->assertStatus(502)
        ->assertJsonPath('message', 'Geminiの問題データが不正です。');

    expect(ExtensionQuizDraft::query()->exists())->toBeFalse();
    $this->assertDatabaseHas('ai_usage_logs', ['feature' => 'quiz_generation', 'success' => false]);
    Http::assertSentCount(1);
});

test('does not create a draft and records failed AI usage when Gemini returns 429', function () {
    Http::preventStrayRequests();
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(['error' => ['message' => 'Rate limit exceeded']], 429)]);

    $this->withToken($this->extensionToken)->postJson(route('extension.generate-quiz'), validQuizGenerationPayload())
        ->assertStatus(502)->assertJsonPath('message', 'Geminiによる問題生成に失敗しました。');

    expect(ExtensionQuizDraft::query()->exists())->toBeFalse();
    $this->assertDatabaseHas('ai_usage_logs', ['feature' => 'quiz_generation', 'success' => false, 'http_status' => 429]);
});

test('guests are redirected to login when viewing a temporary quiz', function () {
    $draft = quizDraft();

    $this->get(route('extension-quiz-drafts.quiz', $draft->token))->assertRedirect(route('login'));
});

test('an authenticated user can view one active temporary quiz question with optional learning aids', function () {
    $user = User::factory()->create();
    $draft = quizDraft();

    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token))
        ->assertOk()
        ->assertSee('Amazon EC2')
        ->assertSee('仮想サーバーを実行する')
        ->assertDontSee('correct_option')
        ->assertSee('正解を見る')
        ->assertSee('解説を見る')
        ->assertSee('data-selection-source', false)
        ->assertSee('ChatGPTに聞く ↗')
        ->assertDontSee('aria-label="用語を保存"', false)
        ->assertSee($draft->generated_questions[0]['explanation'])
        ->assertSee('結果を見る →')
        ->assertDontSee('回答する');
});

test('temporary quiz excludes its correct answer from popovers and has no save button', function (): void {
    $user = User::factory()->create();
    $draft = quizDraft(['subject' => '宅建', 'generated_questions' => [quizQuestion([
        'option_a' => '自己発見取引',
        'explanation' => '自己発見取引とは、媒介契約後の取引です。',
    ])]]);
    TermExplanation::factory()->create(['subject_key' => '宅建', 'subject_label' => '宅建', 'normalized_term' => '自己発見取引', 'display_term' => '自己発見取引', 'explanation' => '正解の説明']);
    TermExplanation::factory()->create(['subject_key' => '宅建', 'subject_label' => '宅建', 'normalized_term' => '媒介契約', 'display_term' => '媒介契約', 'explanation' => '契約の説明']);

    $html = $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token))->getContent();

    expect(substr_count($html, 'class="term-trigger'))->toBe(1);
    expect($html)
        ->not->toContain('自己発見取引</button>')
        ->toContain('媒介契約');
    $this->assertStringNotContainsString('aria-label="用語を保存"', $html);
    $this->assertStringContainsString('ChatGPTに聞く ↗', $html);
});

test('expired and claimed quiz drafts cannot be viewed', function () {
    $user = User::factory()->create();
    $expiredDraft = quizDraft(['expires_at' => now()->subSecond()]);
    $claimedDraft = quizDraft(['claimed_at' => now()]);

    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $expiredDraft->token))->assertNotFound();
    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $claimedDraft->token))->assertNotFound();
});

test('stores one temporary quiz answer at a time and does not save a learning set', function () {
    $user = User::factory()->create();
    $draft = quizDraft(['generated_questions' => threeQuizQuestions()]);

    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token));
    $response = $this->actingAs($user)->post(route('extension-quiz-drafts.grade', $draft->token), ['question_index' => 0, 'selected_option' => 'A']);

    $response->assertSessionHasNoErrors()->assertRedirect(route('extension-quiz-drafts.quiz', $draft->token));
    $this->assertDatabaseCount('quiz_attempt_answers', 1);

    expect($user->learningSets()->count())->toBe(0);
    expect($draft->fresh()->claimed_at)->toBeNull();
});

test('renders the complete temporary quiz result with answer states and concealed solutions', function () {
    $user = User::factory()->create();
    $draft = quizDraft([
        'generated_questions' => [
            quizQuestion([
                'question' => '選択肢Aが正解の問題',
                'option_a' => 'Aの正解',
                'correct_option' => 'A',
                'explanation' => '選択肢Aの解説',
            ]),
            quizQuestion([
                'question' => '選択肢Bが正解の問題',
                'option_a' => 'B問題で選んだ誤答',
                'option_b' => 'Bの正解',
                'correct_option' => 'B',
                'explanation' => '選択肢Bの解説',
            ]),
            quizQuestion([
                'question' => '選択肢Cが正解の問題',
                'option_c' => 'Cの正解',
                'correct_option' => 'C',
                'explanation' => '選択肢Cの解説',
            ]),
            quizQuestion([
                'question' => '選択肢Dが正解の問題',
                'option_d' => 'Dの正解',
                'correct_option' => 'D',
                'explanation' => '選択肢Dの解説',
            ]),
        ],
    ]);
    completeDraftQuiz($this, $user, $draft, ['A', 'A', null, 'D']);

    $this->actingAs($user)->get(route('extension-quiz-drafts.result', $draft->token))
        ->assertOk()
        ->assertSee('LEARNING COMPLETE')
        ->assertSee('AWS・クラウド基礎')
        ->assertSee('4問中')
        ->assertSee('2問正解')
        ->assertSee('正答率')
        ->assertSee('50%')
        ->assertSeeInOrder(['正解', '2'])
        ->assertSeeInOrder(['不正解', '1'])
        ->assertSeeInOrder(['未回答', '1'])
        ->assertSee('問題別結果')
        ->assertSee('問題1')
        ->assertSee('問題2')
        ->assertSee('問題3')
        ->assertSee('問題4')
        ->assertSee('選択肢Aが正解の問題')
        ->assertSee('選択肢Bが正解の問題')
        ->assertSee('選択肢Cが正解の問題')
        ->assertSee('選択肢Dが正解の問題')
        ->assertSee('A. Aの正解')
        ->assertSee('A. B問題で選んだ誤答')
        ->assertSee('未回答')
        ->assertSee('D. Dの正解')
        ->assertSee('aria-hidden="true">○</span>', false)
        ->assertSee('aria-hidden="true">×</span>', false)
        ->assertSee('aria-hidden="true">－</span>', false)
        ->assertDontSee('●</span> 正解', false)
        ->assertDontSee('●</span> 不正解', false)
        ->assertDontSee('○</span> 未回答', false)
        ->assertSee('class="sr-only">正解</span>', false)
        ->assertSee('class="sr-only">不正解</span>', false)
        ->assertSee('class="sr-only">未回答</span>', false)
        ->assertSee('正解を見る')
        ->assertSee('A. Aの正解')
        ->assertSee('B. Bの正解')
        ->assertSee('C. Cの正解')
        ->assertSee('D. Dの正解')
        ->assertSee('選択肢Aの解説')
        ->assertSee('選択肢Bの解説')
        ->assertSee('選択肢Cの解説')
        ->assertSee('選択肢Dの解説')
        ->assertSee('x-data="{ revealed: false }"', false)
        ->assertSee('x-show="revealed"', false)
        ->assertSee('x-cloak', false)
        ->assertSee(':aria-expanded="revealed.toString()"', false)
        ->assertSee('学習セットとして保存')
        ->assertSee('ホームへ戻る');
});

test('escapes user controlled content on the temporary quiz result', function () {
    $user = User::factory()->create();
    $title = '<script>titleAttack()</script>';
    $question = '<img src=x onerror=questionAttack()>';
    $option = '<svg onload=optionAttack()>';
    $explanation = '<iframe srcdoc=explanationAttack()>';
    $draft = quizDraft([
        'topic' => $title,
        'generated_questions' => [
            quizQuestion([
                'question' => $question,
                'option_a' => $option,
                'explanation' => $explanation,
            ]),
        ],
    ]);
    completeDraftQuiz($this, $user, $draft, ['A']);

    $this->actingAs($user)->get(route('extension-quiz-drafts.result', $draft->token))
        ->assertSee($title)
        ->assertSee($question)
        ->assertSee($option)
        ->assertSee($explanation)
        ->assertDontSee($title, false)
        ->assertDontSee($question, false)
        ->assertDontSee($option, false)
        ->assertDontSee($explanation, false);
});

test('does not reveal another users temporary quiz result', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $draft = quizDraft();
    completeDraftQuiz($this, $owner, $draft, ['A']);

    $this->actingAs($otherUser)
        ->get(route('extension-quiz-drafts.result', $draft->token))
        ->assertNotFound();
});

test('validates temporary quiz answers as A through D only', function () {
    $user = User::factory()->create();
    $draft = quizDraft();

    $this->actingAs($user)->from(route('extension-quiz-drafts.quiz', $draft->token))
        ->post(route('extension-quiz-drafts.grade', $draft->token), ['question_index' => 0, 'selected_option' => 'E'])
        ->assertRedirect(route('extension-quiz-drafts.quiz', $draft->token))
        ->assertSessionHasErrors(['selected_option']);
});

test('grading and saving a draft do not make additional Gemini requests', function () {
    Http::preventStrayRequests();
    $user = User::factory()->create();
    $draft = quizDraft();

    completeDraftQuiz($this, $user, $draft, ['A']);
    $this->actingAs($user)->post(route('extension-quiz-drafts.save', $draft->token))->assertRedirect(route('dashboard'));
});

test('saves a completed draft as a web learning set with all generated questions', function () {
    $user = User::factory()->create();
    $draft = quizDraft([
        'selected_terms' => [
            ['term' => ' AWS ', 'description' => ' クラウドサービスです。 '],
            ['term' => 'aws', 'description' => '重複する説明です。'],
            ['term' => 'systemd', 'description' => 'サービス管理の仕組みです。'],
            ['term' => 'systemctl', 'description' => 'systemdを操作するコマンドです。'],
        ],
        'generated_questions' => threeQuizQuestions(),
    ]);
    completeDraftQuiz($this, $user, $draft, ['A', 'B', 'D']);

    $response = $this->actingAs($user)->post(route('extension-quiz-drafts.save', $draft->token));
    $learningSet = $user->learningSets()->firstOrFail();

    $response->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    $this->assertDatabaseHas('learning_sets', [
        'id' => $learningSet->id,
        'user_id' => $user->id,
        'title' => 'AWSとは？初心者向け解説 - Web学習',
        'subject' => 'クラウド',
        'topic' => 'AWS・クラウド基礎',
        'source_type' => 'web',
        'source_url' => 'https://example.com/aws',
    ]);
    expect($learningSet->questions()->count())->toBe(3);
    $this->assertDatabaseHas('questions', [
        'learning_set_id' => $learningSet->id,
        'input_question' => 'Amazon EC2の主な役割を入力してください。',
    ]);
    expect($learningSet->learningTerms()->count())->toBe(3);
    $this->assertDatabaseHas('learning_terms', [
        'learning_set_id' => $learningSet->id,
        'term' => 'AWS',
        'description' => 'クラウドサービスです。',
        'source_type' => 'web',
        'source_url' => 'https://example.com/aws',
    ]);
    $this->assertDatabaseHas('learning_terms', ['learning_set_id' => $learningSet->id, 'term' => 'systemd']);
    $this->assertDatabaseHas('learning_terms', ['learning_set_id' => $learningSet->id, 'term' => 'systemctl']);
    foreach ($learningSet->questions()->get() as $question) {
        expect($question->review_stage)->toBe(0)
            ->and($question->next_review_at->isSameSecond(now()->addDay()))->toBeTrue()
            ->and($question->review_count)->toBe(0)
            ->and($question->correct_review_count)->toBe(0);
    }
    expect($draft->fresh()->claimed_at)->not->toBeNull();
    $autoCollection = $user->learningCollections()->where('auto_rule', 'cloud')->firstOrFail();
    expect($autoCollection->name)->toBe('クラウド / AWS');
    $this->assertDatabaseHas('learning_collection_learning_set', [
        'learning_collection_id' => $autoCollection->id,
        'learning_set_id' => $learningSet->id,
        'assigned_by' => 'auto',
    ]);

    $dashboard = $this->actingAs($user)->get(route('dashboard'));
    $dashboard
        ->assertSee('AWS・クラウド基礎')
        ->assertSee('これから学ぶ')
        ->assertSee('0 / 3問 完了')
        ->assertSee('学習を始める →')
        ->assertSee('data-learning-item', false)
        ->assertSee('data-topic="AWS・クラウド基礎"', false)
        ->assertSee('data-title="AWSとは？初心者向け解説 - Web学習"', false)
        ->assertSee('data-resume-url="'.route('learning-sets.quiz', $learningSet).'"', false);
    expect($user->quizAttempts()->where('learning_set_id', $learningSet->id)->exists())->toBeFalse();
});

test('does not save the same completed quiz draft twice', function () {
    $user = User::factory()->create();
    $draft = quizDraft();
    completeDraftQuiz($this, $user, $draft, ['A']);

    $this->actingAs($user)->post(route('extension-quiz-drafts.save', $draft->token))->assertRedirect(route('dashboard'));
    $this->actingAs($user)->post(route('extension-quiz-drafts.save', $draft->token))->assertNotFound();

    expect($user->learningSets()->count())->toBe(1);
    expect(LearningTerm::query()->count())->toBe(1);
});

test('leaving a completed result without saving creates no learning set', function () {
    $user = User::factory()->create();
    $draft = quizDraft();
    completeDraftQuiz($this, $user, $draft, ['A']);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect($user->learningSets()->count())->toBe(0);
    expect(LearningTerm::query()->exists())->toBeFalse();
    expect($draft->fresh()->claimed_at)->toBeNull();
});

function validQuizGenerationPayload(array $overrides = []): array
{
    return array_replace(['title' => 'AWSとは？初心者向け解説', 'url' => 'https://example.com/aws', 'terms' => [['term' => 'Amazon EC2', 'description' => 'AWS上で仮想サーバーを利用できるサービスです。']]], $overrides);
}

function quizQuestion(array $overrides = []): array
{
    return array_replace(['term' => 'Amazon EC2', 'question' => 'Amazon EC2の主な役割として最も適切なものはどれですか？', 'input_question' => 'Amazon EC2の主な役割を入力してください。', 'option_a' => '仮想サーバーを実行する', 'option_b' => 'メールを送信する', 'option_c' => 'DNSを管理する', 'option_d' => 'ソースコードを保存する', 'correct_option' => 'A', 'explanation' => 'Amazon EC2はクラウド上で仮想サーバーを実行するサービスです。'], $overrides);
}

function threeQuizQuestions(): array
{
    return [quizQuestion(), quizQuestion(['term' => 'Amazon S3', 'question' => 'Amazon S3は主に何を保存しますか？', 'correct_option' => 'B']), quizQuestion(['term' => 'Amazon RDS', 'question' => 'Amazon RDSの役割は何ですか？', 'correct_option' => 'C'])];
}

function fakeSuccessfulQuizGeneration(): void
{
    Http::preventStrayRequests();
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['subject' => 'クラウド', 'topic' => 'AWS・クラウド基礎', 'questions' => [quizQuestion()]], JSON_UNESCAPED_UNICODE)]]]]], 'usageMetadata' => ['promptTokenCount' => 120, 'candidatesTokenCount' => 80, 'totalTokenCount' => 200]])]);
}

function quizDraft(array $overrides = []): ExtensionQuizDraft
{
    return ExtensionQuizDraft::query()->create(array_replace(['token' => (string) Str::uuid(), 'source_title' => 'AWSとは？初心者向け解説', 'subject' => 'クラウド', 'topic' => 'AWS・クラウド基礎', 'source_url' => 'https://example.com/aws', 'selected_terms' => validQuizGenerationPayload()['terms'], 'generated_questions' => [quizQuestion()], 'expires_at' => now()->addDay()], $overrides));
}

function completeDraftQuiz(object $test, User $user, ExtensionQuizDraft $draft, array $answers): void
{
    $test->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token));
    foreach ($answers as $index => $answer) {
        $test->actingAs($user)->post(route('extension-quiz-drafts.grade', $draft->token), ['question_index' => $index, 'selected_option' => $answer])->assertRedirect();
    }
}
