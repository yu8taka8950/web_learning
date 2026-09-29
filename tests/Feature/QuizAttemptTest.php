<?php

use App\Models\ExtensionQuizDraft;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

test('a draft creates an owned attempt and shows one question with a single next action', function () {
    $user = User::factory()->create();
    $draft = resumeDraft(8);

    $response = $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token));

    $response->assertOk()
        ->assertSee('問題 1 / 8')
        ->assertSee('質問0')
        ->assertDontSee('質問1')
        ->assertSee('正解を見る')
        ->assertSee('解説を見る')
        ->assertSee('参照サイトを見る')
        ->assertSee('次にすすむ →')
        ->assertSee('選択せずに進むと未回答として記録されます')
        ->assertDontSee('回答する');
    $this->assertDatabaseHas('quiz_attempts', ['user_id' => $user->id, 'extension_quiz_draft_id' => $draft->id, 'status' => 'in_progress']);
    expect($draft->fresh()->user_id)->toBe($user->id);
});

test('a draft marks only its correct option for the answer details toggle', function () {
    $user = User::factory()->create();
    $draft = resumeDraft(2);

    $html = $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token))->getContent();

    expect($html)
        ->toContain('x-data="{ showCorrect: false }"')
        ->toContain('@toggle="showCorrect = $event.target.open"')
        ->toContain(':class="showCorrect ? \'text-[#3155D9]\' : \'\'"')
        ->toContain('<strong>A.</strong> 正解0')
        ->toContain('<strong>B.</strong> 誤答B');
    expect(substr_count($html, ':class="showCorrect ? \'text-[#3155D9]\' : \'\'"'))->toBe(1);
});

test('a draft next action stores its answer and moves directly to the next question', function () {
    Http::preventStrayRequests();
    $user = User::factory()->create();
    $draft = resumeDraft(2);
    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token));

    $this->actingAs($user)->post(route('extension-quiz-drafts.grade', $draft->token), ['question_index' => 0, 'selected_option' => 'B']);
    $response = $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token));

    $response->assertOk()
        ->assertSee('問題 2 / 2')
        ->assertSee('質問1')
        ->assertSee('正解を見る')
        ->assertSee('解説を見る')
        ->assertSee('参照サイトを見る')
        ->assertSee('href="https://example.com"', false)
        ->assertSee('target="_blank"', false)
        ->assertSee('rel="noopener noreferrer"', false)
        ->assertSee('この問題・解説はAIによって生成されています。');
    Http::assertNothingSent();
    $this->assertDatabaseHas('quiz_attempt_answers', ['question_index' => 0, 'selected_option' => 'B', 'is_correct' => false]);
});

test('unsafe draft source URLs are never rendered as links', function () {
    $user = User::factory()->create();
    $draft = resumeDraft(2, ['source_url' => 'javascript:alert(1)']);
    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token));
    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token))
        ->assertSee('参照サイトを見る')
        ->assertSee('この問題には参照URLが登録されていません。')
        ->assertDontSee('javascript:alert(1)', false);
});

test('a screenshot draft explains that no reference URL is registered after answering', function () {
    $user = User::factory()->create();
    $draft = resumeDraft(2, ['source_type' => 'screenshot', 'source_url' => null]);
    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token));
    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token))
        ->assertSee('参照サイトを見る')
        ->assertSee('この問題には参照URLが登録されていません。');
});

test('a saved learning set shows its learning aids and advances with one action', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => 'LinuCの記事', 'topic' => 'Linux基礎', 'source_type' => 'web', 'source_url' => 'http://example.com/linux']);
    foreach (range(0, 1) as $index) {
        $learningSet->questions()->create(['question' => 'Linux質問'.$index, 'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A', 'explanation' => 'Linux解説'.$index]);
    }

    $this->actingAs($user)->get(route('learning-sets.quiz', $learningSet))
        ->assertSee('method="POST"', false)
        ->assertSee('action="'.route('learning-sets.quiz.grade', $learningSet).'"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('type="submit"', false)
        ->assertSee('x-data="{ solutionOpen: false }"', false)
        ->assertSee("solutionOpen && 'A' === 'A'", false)
        ->assertSee("solutionOpen && 'B' === 'A'", false)
        ->assertSee("solutionOpen && 'C' === 'A'", false)
        ->assertSee("solutionOpen && 'D' === 'A'", false)
        ->assertSee("x-on:toggle=\"\$dispatch('solution-toggled', { open: \$event.target.open })\"", false)
        ->assertSee('参照サイトを見る')
        ->assertSee('http://example.com/linux', false)
        ->assertSee('次にすすむ →')
        ->assertDontSee('回答する');
    $this->actingAs($user)->post(route('learning-sets.quiz.grade', $learningSet), ['question_index' => 0, 'selected_option' => 'A']);

    $this->actingAs($user)->get(route('learning-sets.quiz', $learningSet))
        ->assertSee('問題 2 / 2')
        ->assertSee('LinuCの記事')
        ->assertSee('href="http://example.com/linux"', false)
        ->assertSee('rel="noopener noreferrer"', false);
});

test('a saved learning set shows correct answer explanation and AI help in one solution', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => 'PHP教材', 'source_type' => 'manual']);
    $learningSet->questions()->create(['question' => '変数はどれですか？', 'option_a' => '定数', 'option_b' => '変数', 'option_c' => '関数', 'option_d' => '配列', 'correct_option' => 'B', 'explanation' => '変数の解説']);

    $html = $this->actingAs($user)->get(route('learning-sets.quiz', $learningSet))->getContent();

    expect($html)
        ->toContain('正解を見る')
        ->toContain('正解')
        ->toContain('変数の解説')
        ->toContain('AIに質問する')
        ->not->toContain('解説を見る')
        ->toContain('<strong>A.</strong> 定数')
        ->toContain('<strong>B.</strong> 変数');
});

test('an unselected draft option is stored as incorrect and advances to the next question', function () {
    $user = User::factory()->create();
    $draft = resumeDraft(2);
    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token));

    $this->actingAs($user)->post(route('extension-quiz-drafts.grade', $draft->token), ['question_index' => 0])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('extension-quiz-drafts.quiz', $draft->token));

    $attempt = QuizAttempt::query()->firstOrFail();
    expect($attempt->answers()->count())->toBe(1)
        ->and($attempt->answers()->firstOrFail()->selected_option)->toBeNull()
        ->and($attempt->answers()->firstOrFail()->is_correct)->toBeFalse()
        ->and($attempt->current_question_index)->toBe(1);
    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token))
        ->assertSee('問題 2 / 2')
        ->assertSee('1問完了');
});

test('viewing a draft question without submitting creates no answer and resumes the same question', function () {
    $user = User::factory()->create();
    $draft = resumeDraft(2);

    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token))->assertSee('問題 1 / 2');
    $this->flushSession();

    expect(QuizAttempt::query()->firstOrFail()->answers()->exists())->toBeFalse();
    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token))->assertSee('問題 1 / 2');
});

test('draft resume uses answer row existence when an earlier answer is unselected', function () {
    $user = User::factory()->create();
    $draft = resumeDraft(3);
    $attempt = $user->quizAttempts()->create([
        'extension_quiz_draft_id' => $draft->id,
        'current_question_index' => 0,
        'total_questions' => 3,
        'status' => 'in_progress',
        'started_at' => now(),
    ]);
    $attempt->answers()->create(['question_index' => 0, 'selected_option' => 'A', 'is_correct' => true, 'answered_at' => now()]);
    $attempt->answers()->create(['question_index' => 1, 'selected_option' => null, 'is_correct' => false, 'answered_at' => now()]);

    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token))
        ->assertSee('問題 3 / 3')
        ->assertSee('質問2')
        ->assertSee('2問完了');
});

test('opening the correct answer before submitting no option still records an incorrect unanswered answer', function () {
    $user = User::factory()->create();
    $draft = resumeDraft(2);

    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token))->assertSee('正解を見る');
    $this->actingAs($user)->post(route('extension-quiz-drafts.grade', $draft->token), ['question_index' => 0]);

    $this->assertDatabaseHas('quiz_attempt_answers', [
        'question_index' => 0,
        'selected_option' => null,
        'is_correct' => false,
    ]);
});

test('draft resumes at the first unanswered question for any completed count', function (int $answeredCount, int $expectedQuestion) {
    $user = User::factory()->create();
    $draft = resumeDraft(8);
    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token));

    for ($index = 0; $index < $answeredCount; $index++) {
        $this->actingAs($user)->post(route('extension-quiz-drafts.grade', $draft->token), ['question_index' => $index, 'selected_option' => 'A']);
    }

    $this->flushSession();
    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token))->assertOk()->assertSee("問題 {$expectedQuestion} / 8")->assertSee('質問'.($expectedQuestion - 1));
})->with([[1, 2], [3, 4], [5, 6], [7, 8]]);

test('a duplicate answer post is idempotent and does not advance twice', function () {
    $user = User::factory()->create();
    $draft = resumeDraft(3);
    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token));
    $payload = ['question_index' => 0, 'selected_option' => 'A', 'return_to' => 'dashboard'];

    $this->actingAs($user)->post(route('extension-quiz-drafts.grade', $draft->token), $payload);
    $this->actingAs($user)->post(route('extension-quiz-drafts.grade', $draft->token), $payload);

    $attempt = QuizAttempt::query()->firstOrFail();
    expect($attempt->answers()->count())->toBe(1)->and($attempt->current_question_index)->toBe(1);
});

test('a duplicate unanswered post creates only one answer and advances once', function () {
    $user = User::factory()->create();
    $draft = resumeDraft(3);
    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token));
    $payload = ['question_index' => 0];

    $this->actingAs($user)->post(route('extension-quiz-drafts.grade', $draft->token), $payload);
    $this->actingAs($user)->post(route('extension-quiz-drafts.grade', $draft->token), $payload);

    $attempt = QuizAttempt::query()->firstOrFail();
    expect($attempt->answers()->count())->toBe(1)->and($attempt->current_question_index)->toBe(1);
});

test('dashboard answers a saved learning set quiz and returns with its next question', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => 'Dashboard PHP教材', 'source_type' => 'web', 'source_url' => 'https://example.com/php']);
    foreach (range(0, 1) as $index) {
        $learningSet->questions()->create(['question' => 'Dashboard PHP質問'.$index, 'option_a' => '正解', 'option_b' => '誤答B', 'option_c' => '誤答C', 'option_d' => '誤答D', 'correct_option' => 'A', 'explanation' => 'PHP解説'.$index, 'next_review_at' => now()->subDay()]);
    }
    $this->actingAs($user)->get(route('learning-sets.quiz', $learningSet));

    $dashboard = $this->actingAs($user)->get(route('dashboard'));
    $dashboard
        ->assertSee('action="'.route('learning-sets.quiz.grade', $learningSet).'"', false)
        ->assertDontSee('今日の1問')
        ->assertSee('name="selected_option" value="D"', false)
        ->assertSee('PHP解説0')
        ->assertSee('href="https://example.com/php"', false);

    $response = $this->actingAs($user)->post(route('learning-sets.quiz.grade', $learningSet), [
        'question_index' => 0,
        'return_to' => 'dashboard',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    $this->assertDatabaseHas('quiz_attempt_answers', ['question_index' => 0, 'selected_option' => null, 'is_correct' => false]);
    $this->actingAs($user)->get(route('dashboard'))->assertSee('問題 2 / 2')->assertSee('Dashboard PHP質問1');
});

test('dashboard answers a draft quiz and returns with its next question', function () {
    $user = User::factory()->create();
    $draft = resumeDraft(2, ['source_title' => 'Dashboard Draft元記事']);
    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token));

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('action="'.route('extension-quiz-drafts.grade', $draft->token).'"', false)
        ->assertDontSee('今日の1問')
        ->assertSee('name="selected_option" value="A"', false)
        ->assertSee('質問0')
        ->assertSee('解説0')
        ->assertSee('Dashboard Draft元記事');

    $response = $this->actingAs($user)->post(route('extension-quiz-drafts.grade', $draft->token), [
        'question_index' => 0,
        'selected_option' => 'A',
        'return_to' => 'dashboard',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertDatabaseHas('quiz_attempt_answers', ['question_index' => 0, 'selected_option' => 'A', 'is_correct' => true]);
    $this->actingAs($user)->get(route('dashboard'))->assertSee('問題 2 / 2')->assertSee('質問1');
});

test('quick learning return target only accepts the dashboard allow-listed value', function () {
    $user = User::factory()->create();
    $draft = resumeDraft(2);
    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token));

    $this->actingAs($user)->post(route('extension-quiz-drafts.grade', $draft->token), [
        'question_index' => 0,
        'selected_option' => 'A',
        'return_to' => 'https://malicious.example',
    ])->assertSessionHasErrors(['return_to']);

    expect(QuizAttempt::query()->firstOrFail()->answers()->exists())->toBeFalse();
});

test('dashboard final quiz answers retain each existing result destination', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '最終問題教材', 'source_type' => 'manual']);
    $learningSet->questions()->create(['question' => '最終問題', 'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A']);
    $this->actingAs($user)->get(route('learning-sets.quiz', $learningSet));

    $this->actingAs($user)->get(route('dashboard'))->assertSee('結果を見る');
    $this->actingAs($user)->post(route('learning-sets.quiz.grade', $learningSet), [
        'question_index' => 0,
        'selected_option' => 'A',
        'return_to' => 'dashboard',
    ])->assertRedirect(route('learning-sets.quiz', $learningSet));

    $draft = resumeDraft(1, ['user_id' => $user->id]);
    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token));
    $this->actingAs($user)->post(route('extension-quiz-drafts.grade', $draft->token), [
        'question_index' => 0,
        'selected_option' => 'A',
        'return_to' => 'dashboard',
    ])->assertRedirect(route('extension-quiz-drafts.result', $draft->token));
});

test('completing the final question records completion and result score', function () {
    $user = User::factory()->create();
    $draft = resumeDraft(2);
    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token));
    $this->actingAs($user)->post(route('extension-quiz-drafts.grade', $draft->token), ['question_index' => 0, 'selected_option' => 'A']);
    $this->actingAs($user)->post(route('extension-quiz-drafts.grade', $draft->token), ['question_index' => 1, 'selected_option' => 'B']);

    $attempt = QuizAttempt::query()->firstOrFail();
    expect($attempt->status)->toBe('completed')->and($attempt->completed_at)->not->toBeNull()->and($attempt->current_question_index)->toBe(2);
    $this->actingAs($user)->get(route('extension-quiz-drafts.result', $draft->token))->assertSee('2問中 1問正解')->assertSee('正答率 50%');
});

test('an unanswered final draft question completes the attempt and appears in the result without affecting the score', function () {
    $user = User::factory()->create();
    $draft = resumeDraft(2);
    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token));
    $this->actingAs($user)->post(route('extension-quiz-drafts.grade', $draft->token), ['question_index' => 0, 'selected_option' => 'A']);

    $response = $this->actingAs($user)->post(route('extension-quiz-drafts.grade', $draft->token), ['question_index' => 1]);
    $this->actingAs($user)->post(route('extension-quiz-drafts.grade', $draft->token), ['question_index' => 1]);

    $attempt = QuizAttempt::query()->firstOrFail();
    $response->assertSessionHasNoErrors()->assertRedirect(route('extension-quiz-drafts.result', $draft->token));
    expect($attempt->fresh()->status)->toBe('completed')
        ->and($attempt->answers()->whereNull('selected_option')->count())->toBe(1)
        ->and($user->quizAttempts()->count())->toBe(1);
    $this->actingAs($user)->get(route('extension-quiz-drafts.result', $draft->token))
        ->assertSee('2問中 1問正解')
        ->assertSee('正答率 50%')
        ->assertSee('aria-hidden="true">－</span>', false)
        ->assertSeeInOrder(['あなたの回答', '未回答']);
});

test('a saved learning set stores an unanswered option and completes with unanswered result details', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => 'PHP教材', 'source_type' => 'manual']);
    $learningSet->questions()->create(['question' => 'PHP質問', 'option_a' => '正解', 'option_b' => '誤答B', 'option_c' => '誤答C', 'option_d' => '誤答D', 'correct_option' => 'A']);
    $this->actingAs($user)->get(route('learning-sets.quiz', $learningSet))
        ->assertSee('選択せずに進むと未回答として記録されます');

    $this->actingAs($user)->post(route('learning-sets.quiz.grade', $learningSet), ['question_index' => 0])
        ->assertSessionHasNoErrors();

    $answer = QuizAttempt::query()->firstOrFail()->answers()->firstOrFail();
    expect($answer->selected_option)->toBeNull()->and($answer->is_correct)->toBeFalse();
    $this->actingAs($user)->get(route('learning-sets.quiz', $learningSet))
        ->assertSee('1問中0問正解')
        ->assertSee('正答率 0%')
        ->assertSee('○</span> 未回答', false)
        ->assertSee('あなたの回答：未回答');
});

test('other users cannot access draft progress and expired drafts remain hidden', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $draft = resumeDraft(2, ['user_id' => $owner->id]);
    $expired = resumeDraft(2, ['user_id' => $owner->id, 'expires_at' => now()->subMinute()]);

    $this->actingAs($other)->get(route('extension-quiz-drafts.quiz', $draft->token))
        ->assertOk()
        ->assertSee('Web Learningのアカウントが変更されました')
        ->assertDontSee('質問0');
    $this->actingAs($other)->post(route('extension-quiz-drafts.grade', $draft->token), ['question_index' => 0, 'selected_option' => 'A'])
        ->assertNotFound();
    $this->actingAs($owner)->get(route('extension-quiz-drafts.quiz', $expired->token))->assertNotFound();
});

test('an incomplete draft cannot be saved', function () {
    $user = User::factory()->create();
    $draft = resumeDraft(2);
    $this->actingAs($user)->get(route('extension-quiz-drafts.quiz', $draft->token));
    $this->actingAs($user)->post(route('extension-quiz-drafts.grade', $draft->token), ['question_index' => 0, 'selected_option' => 'A']);

    $this->actingAs($user)->post(route('extension-quiz-drafts.save', $draft->token))->assertNotFound();
    expect($user->learningSets()->exists())->toBeFalse();
});

test('a saved learning set resumes and creates a separate attempt when restarted', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '元記事', 'topic' => 'Linux基礎', 'source_type' => 'web']);
    foreach (range(0, 2) as $index) {
        $learningSet->questions()->create(['question' => '保存質問'.$index, 'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A']);
    }
    $this->actingAs($user)->get(route('learning-sets.quiz', $learningSet));
    $this->actingAs($user)->post(route('learning-sets.quiz.grade', $learningSet), ['question_index' => 0, 'selected_option' => 'A']);

    $this->actingAs($user)->get(route('learning-sets.quiz', $learningSet))->assertSee('問題 2 / 3')->assertSee('保存質問1');
    foreach ([1, 2] as $index) {
        $this->actingAs($user)->post(route('learning-sets.quiz.grade', $learningSet), ['question_index' => $index, 'selected_option' => 'A']);
    }
    $completedAttempt = QuizAttempt::query()->firstOrFail();
    $this->actingAs($user)->post(route('learning-sets.quiz.restart', $learningSet))->assertRedirect(route('learning-sets.quiz', $learningSet));

    expect($completedAttempt->fresh()->status)->toBe('completed')->and($user->quizAttempts()->count())->toBe(2);
});

test('another user cannot access or answer a saved learning set quiz', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $learningSet = $owner->learningSets()->create(['title' => '非公開教材', 'source_type' => 'manual']);
    $learningSet->questions()->create(['question' => '秘密の質問', 'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A']);

    $this->actingAs($other)->get(route('learning-sets.quiz', $learningSet))->assertNotFound();
    $this->actingAs($other)->post(route('learning-sets.quiz.grade', $learningSet), ['question_index' => 0, 'selected_option' => 'A'])->assertNotFound();
    expect(QuizAttempt::query()->exists())->toBeFalse();
});

test('dashboard shows owned resumable attempts and hides expired and foreign attempts', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $draft = resumeDraft(8, ['user_id' => $user->id, 'topic' => 'Linuxのサービス管理']);
    $expired = resumeDraft(8, ['user_id' => $user->id, 'topic' => '期限切れ', 'expires_at' => now()->subMinute()]);
    $foreign = resumeDraft(8, ['user_id' => $other->id, 'topic' => '他人の学習']);
    foreach ([[$user, $draft, 3], [$user, $expired, 1], [$other, $foreign, 1]] as [$owner, $subject, $count]) {
        $attempt = $owner->quizAttempts()->create(['extension_quiz_draft_id' => $subject->id, 'current_question_index' => $count, 'total_questions' => 8, 'status' => 'in_progress', 'started_at' => now()]);
        foreach (range(0, $count - 1) as $index) {
            $attempt->answers()->create(['question_index' => $index, 'selected_option' => 'A', 'is_correct' => true, 'answered_at' => now()]);
        }
    }

    $this->actingAs($user)->get(route('dashboard'))->assertSee('Linuxのサービス管理')->assertSee('3 / 8問 完了')->assertSee('4問目から続ける')->assertDontSee('期限切れ')->assertDontSee('他人の学習');
});

/** @param array<string, mixed> $overrides */
function resumeDraft(int $count, array $overrides = []): ExtensionQuizDraft
{
    $questions = collect(range(0, $count - 1))->map(fn (int $index): array => ['term' => '用語'.$index, 'question' => '質問'.$index, 'option_a' => '正解'.$index, 'option_b' => '誤答B', 'option_c' => '誤答C', 'option_d' => '誤答D', 'correct_option' => 'A', 'explanation' => '解説'.$index])->all();

    return ExtensionQuizDraft::query()->create(array_replace(['token' => (string) Str::uuid(), 'source_type' => 'web', 'source_title' => '元記事', 'topic' => 'テスト学習テーマ', 'source_url' => 'https://example.com', 'selected_terms' => [], 'generated_questions' => $questions, 'expires_at' => now()->addDay()], $overrides));
}
