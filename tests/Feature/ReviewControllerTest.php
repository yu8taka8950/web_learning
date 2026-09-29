<?php

use App\Models\Question;
use App\Models\ReviewAttempt;
use App\Models\TermExplanation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-03 10:00:00', 'Asia/Tokyo'));
});

test('guests are redirected to login from every review route', function () {
    $this->get(route('reviews.today'))->assertRedirect(route('login'));
    $this->post(route('reviews.grade'))->assertRedirect(route('login'));
    $this->get(route('reviews.result'))->assertRedirect(route('login'));
});

test('a user with no due questions sees an empty review message without creating an attempt', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('reviews.today'))
        ->assertOk()
        ->assertSee('今日の復習はありません')
        ->assertSee('Dashboardへ戻る');

    expect(ReviewAttempt::query()->exists())->toBeFalse();
});

test('review excludes the current answer from explanation popovers', function (): void {
    $user = User::factory()->create();
    reviewQuestion($user, [
        'option_a' => '自己発見取引',
        'explanation' => '自己発見取引とは、媒介契約後の取引です。',
    ], ['subject' => '宅建']);
    foreach (['自己発見取引' => '正解の説明', '媒介契約' => '媒介契約の説明'] as $term => $explanation) {
        TermExplanation::factory()->create(['subject_key' => '宅建', 'subject_label' => '宅建', 'normalized_term' => $term, 'display_term' => $term, 'explanation' => $explanation]);
    }

    $html = $this->actingAs($user)->get(route('reviews.today'))->getContent();

    expect(substr_count($html, 'class="term-trigger'))->toBe(1)
        ->and($html)->toContain('媒介契約');
});

test('starting a review fixes owned due question ids in schedule and id order', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $oldest = reviewQuestion($user, ['question' => '最古', 'next_review_at' => now()->subDays(2)]);
    $sameTimeFirst = reviewQuestion($user, ['question' => '同時刻1', 'next_review_at' => now()->subDay()]);
    $sameTimeSecond = reviewQuestion($user, ['question' => '同時刻2', 'next_review_at' => now()->subDay()]);
    reviewQuestion($user, ['question' => '未来', 'next_review_at' => now()->addDay()]);
    reviewQuestion($otherUser, ['question' => '他人', 'next_review_at' => now()->subDays(3)]);

    $this->actingAs($user)->get(route('reviews.today'))->assertSee('問題 1 / 3')->assertSee('最古');

    $attempt = ReviewAttempt::query()->firstOrFail();
    expect($attempt->user_id)->toBe($user->id)
        ->and($attempt->question_ids)->toBe([$oldest->id, $sameTimeFirst->id, $sameTimeSecond->id])
        ->and($attempt->total_questions)->toBe(3);
});

test('a review attempt contains at most twenty questions', function () {
    $user = User::factory()->create();
    foreach (range(1, 21) as $number) {
        reviewQuestion($user, ['question' => sprintf('復習順序-%02d', $number), 'next_review_at' => now()->subDays(22 - $number)]);
    }

    $this->actingAs($user)->get(route('reviews.today'))->assertSee('問題 1 / 20');

    $attempt = ReviewAttempt::query()->firstOrFail();
    expect($attempt->question_ids)->toHaveCount(20);
});

test('an in progress attempt is resumed without adding newly due questions', function () {
    $user = User::factory()->create();
    $first = reviewQuestion($user, ['question' => '固定問題1']);
    $second = reviewQuestion($user, ['question' => '固定問題2']);
    $this->actingAs($user)->get(route('reviews.today'));
    $attempt = ReviewAttempt::query()->firstOrFail();
    reviewQuestion($user, ['question' => '後から期限到来']);

    $this->actingAs($user)->get(route('reviews.today'))->assertSee('問題 1 / 2')->assertDontSee('後から期限到来');

    expect(ReviewAttempt::query()->count())->toBe(1)
        ->and($attempt->fresh()->question_ids)->toBe([$first->id, $second->id]);
});

test('today displays one question with learning aids and the normal quiz interaction', function () {
    $user = User::factory()->create();
    $first = reviewQuestion($user, ['question' => '表示する問題', 'correct_option' => 'B'], [
        'title' => '元記事タイトル',
        'topic' => '復習テーマ',
        'source_type' => 'web',
        'source_url' => 'https://example.com/source',
    ]);
    reviewQuestion($user, ['question' => 'まだ表示しない問題']);

    $html = $this->actingAs($user)->get(route('reviews.today'))
        ->assertSee('問題 1 / 2')
        ->assertSee($first->question)
        ->assertDontSee('まだ表示しない問題')
        ->assertSee('次にすすむ →')
        ->assertDontSee('回答する')
        ->assertSee('正解を見る')
        ->assertDontSee('解説を見る')
        ->assertSee('AIに質問する')
        ->assertSee('data-selection-toolbar', false)
        ->assertSee('aria-label="用語を保存"', false)
        ->assertSee('参照サイトを見る')
        ->assertSee('href="https://example.com/source"', false)
        ->assertSee('target="_blank"', false)
        ->assertSee('rel="noopener noreferrer"', false)
        ->assertSee('この問題・解説はAIによって生成されています。')
        ->getContent();

    expect($html)->toContain($first->explanation)
        ->toContain('AIに質問する')
        ->not->toContain('解説を見る');
});

test('review references reject unsafe URLs and explain missing screenshot URLs', function () {
    $user = User::factory()->create();
    reviewQuestion($user, [], ['source_type' => 'screenshot', 'source_url' => null]);

    $this->actingAs($user)->get(route('reviews.today'))
        ->assertSee('この問題には参照URLが登録されていません。');

    $other = User::factory()->create();
    reviewQuestion($other, [], ['source_type' => 'web', 'source_url' => 'javascript:alert(1)']);
    $this->actingAs($other)->get(route('reviews.today'))
        ->assertSee('この問題には参照URLが登録されていません。')
        ->assertDontSee('javascript:alert(1)', false);
});

test('answers A through D are accepted and compared with the database correct option', function (string $selectedOption) {
    $user = User::factory()->create();
    reviewQuestion($user, ['correct_option' => $selectedOption]);
    $attempt = beginReview($this, $user);

    $this->actingAs($user)->post(route('reviews.grade'), reviewPayload($attempt, 0, $selectedOption))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('reviews.result'));

    expect($attempt->answers()->firstOrFail()->is_correct)->toBeTrue();
})->with(['A', 'B', 'C', 'D']);

test('answer validation rejects values other than A through D or null', function () {
    $user = User::factory()->create();
    reviewQuestion($user);
    $attempt = beginReview($this, $user);

    $this->actingAs($user)->post(route('reviews.grade'), reviewPayload($attempt, 0, 'E'))
        ->assertSessionHasErrors(['selected_option']);

    expect($attempt->answers()->exists())->toBeFalse();
});

test('correct answers update stage counters timestamps and persist one answer', function (int $stage, int $expectedStage, int $days) {
    $user = User::factory()->create();
    $question = reviewQuestion($user, [
        'review_stage' => $stage,
        'review_count' => 2,
        'correct_review_count' => 1,
    ]);
    $attempt = beginReview($this, $user);

    $this->actingAs($user)->post(route('reviews.grade'), reviewPayload($attempt, 0, 'A'));

    $question->refresh();
    expect($attempt->answers()->firstOrFail()->is_correct)->toBeTrue()
        ->and($question->review_stage)->toBe($expectedStage)
        ->and($question->next_review_at->equalTo(now()->addDays($days)))->toBeTrue()
        ->and($question->last_reviewed_at->equalTo(now()))->toBeTrue()
        ->and($question->review_count)->toBe(3)
        ->and($question->correct_review_count)->toBe(2);
})->with([
    'stage 0' => [0, 1, 3],
    'stage 1' => [1, 2, 7],
    'stage 2' => [2, 3, 14],
    'stage 3' => [3, 3, 14],
]);

test('incorrect and unanswered reviews reset the schedule without incrementing correct count', function (?string $selectedOption) {
    $user = User::factory()->create();
    $question = reviewQuestion($user, [
        'review_stage' => 2,
        'review_count' => 4,
        'correct_review_count' => 3,
    ]);
    $attempt = beginReview($this, $user);

    $this->actingAs($user)->post(route('reviews.grade'), reviewPayload($attempt, 0, $selectedOption))
        ->assertSessionHasNoErrors();

    $answer = $attempt->answers()->firstOrFail();
    $question->refresh();
    expect($answer->selected_option)->toBe($selectedOption)
        ->and($answer->is_correct)->toBeFalse()
        ->and($question->review_stage)->toBe(0)
        ->and($question->next_review_at->equalTo(now()->addDay()))->toBeTrue()
        ->and($question->last_reviewed_at->equalTo(now()))->toBeTrue()
        ->and($question->review_count)->toBe(5)
        ->and($question->correct_review_count)->toBe(3);
})->with(['incorrect' => 'B', 'unanswered' => null]);

test('review resumes at the first index without an answer after session data is cleared', function (int $answeredCount, int $expectedQuestion) {
    $user = User::factory()->create();
    foreach (range(1, 8) as $number) {
        reviewQuestion($user, ['question' => '再開問題'.$number]);
    }
    $attempt = beginReview($this, $user);
    foreach (range(0, $answeredCount - 1) as $questionIndex) {
        $this->actingAs($user)->post(route('reviews.grade'), reviewPayload($attempt, $questionIndex, $questionIndex === 0 ? null : 'A'));
    }
    $this->flushSession();

    $this->actingAs($user)->get(route('reviews.today'))
        ->assertSee("問題 {$expectedQuestion} / 8")
        ->assertSee('再開問題'.$expectedQuestion);
})->with([[1, 2], [3, 4], [5, 6], [7, 8]]);

test('viewing a question without posting leaves it unanswered and resumes the same question', function () {
    $user = User::factory()->create();
    reviewQuestion($user);

    $this->actingAs($user)->get(route('reviews.today'))->assertSee('問題 1 / 1');
    $this->flushSession();

    expect(ReviewAttempt::query()->firstOrFail()->answers()->exists())->toBeFalse();
    $this->actingAs($user)->get(route('reviews.today'))->assertSee('問題 1 / 1');
});

test('another user cannot answer or resume a review attempt', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    reviewQuestion($owner);
    $attempt = beginReview($this, $owner);

    $this->actingAs($other)->post(route('reviews.grade'), reviewPayload($attempt, 0, 'A'))->assertNotFound();
    $this->actingAs($other)->get(route('reviews.today'))->assertSee('今日の復習はありません');

    expect($attempt->answers()->exists())->toBeFalse();
});

test('duplicate posts create one answer and update the review schedule once', function () {
    $user = User::factory()->create();
    $question = reviewQuestion($user, ['review_stage' => 0]);
    $attempt = beginReview($this, $user);
    $payload = reviewPayload($attempt, 0, 'A');

    $this->actingAs($user)->post(route('reviews.grade'), $payload);
    $this->actingAs($user)->post(route('reviews.grade'), $payload);

    expect($attempt->answers()->count())->toBe(1)
        ->and($question->fresh()->review_count)->toBe(1)
        ->and($question->fresh()->review_stage)->toBe(1)
        ->and($user->reviewAttempts()->count())->toBe(1);
});

test('an unanswered final question completes the attempt and result includes score breakdown', function () {
    Http::preventStrayRequests();
    $user = User::factory()->create();
    reviewQuestion($user, ['question' => '正解した問題']);
    reviewQuestion($user, ['question' => '未回答の問題']);
    $attempt = beginReview($this, $user);
    $this->actingAs($user)->post(route('reviews.grade'), reviewPayload($attempt, 0, 'A'));

    $response = $this->actingAs($user)->post(route('reviews.grade'), reviewPayload($attempt, 1, null));

    $response->assertRedirect(route('reviews.result'));
    expect($attempt->fresh()->status)->toBe('completed')
        ->and($attempt->fresh()->completed_at)->not->toBeNull();
    $this->actingAs($user)->get(route('reviews.result'))
        ->assertSee('今日の復習結果')
        ->assertSee('1 / 2問 正解')
        ->assertSee('正答率 50%')
        ->assertSee('正解：</dt><dd class="inline">1問', false)
        ->assertSee('不正解：</dt><dd class="inline">0問', false)
        ->assertSee('未回答：</dt><dd class="inline">1問', false)
        ->assertSee('あなたの回答：</dt><dd class="inline">未回答', false);
    Http::assertNothingSent();
});

test('dashboard prioritizes fixed attempt progress and removes it after completion', function () {
    $user = User::factory()->create();
    foreach (range(1, 3) as $number) {
        reviewQuestion($user, ['question' => 'Dashboard問題'.$number]);
    }
    $attempt = beginReview($this, $user);
    $this->actingAs($user)->post(route('reviews.grade'), reviewPayload($attempt, 0, null));

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('1 / 3問 完了')
        ->assertSee('残り2問')
        ->assertSee('name="review_attempt_id" value="'.$attempt->id.'"', false)
        ->assertSee('name="selected_option" value="A"', false)
        ->assertSee('次にすすむ');

    $this->actingAs($user)->post(route('reviews.grade'), reviewPayload($attempt, 1, 'A'));
    $this->actingAs($user)->post(route('reviews.grade'), reviewPayload($attempt, 2, 'A'));
    $this->actingAs($user)->get(route('dashboard'))
        ->assertDontSee('3 / 3問 完了')
        ->assertSee('今日の1問')
        ->assertDontSee('続きから：今日の復習');
});

test('dashboard review form stores an unanswered answer updates its schedule and returns with the next question', function () {
    $user = User::factory()->create();
    $first = reviewQuestion($user, ['question' => 'Dashboard復習1', 'review_stage' => 2, 'review_count' => 4]);
    reviewQuestion($user, ['question' => 'Dashboard復習2']);
    $attempt = beginReview($this, $user);

    $dashboard = $this->actingAs($user)->get(route('dashboard'));

    $dashboard
        ->assertSee('action="'.route('reviews.grade').'"', false)
        ->assertDontSee('今日の1問')
        ->assertDontSee('復習を始める')
        ->assertSee('name="return_to" value="dashboard"', false)
        ->assertSee('正解を見る')
        ->assertSee('解説を見る')
        ->assertSee('参照サイトを見る')
        ->assertSee(':class="showCorrect ? \'text-[#3155D9]\' : \'\'"', false);

    $response = $this->actingAs($user)->post(route('reviews.grade'), [
        'review_attempt_id' => $attempt->id,
        'question_index' => 0,
        'return_to' => 'dashboard',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    $this->assertDatabaseHas('review_attempt_answers', [
        'review_attempt_id' => $attempt->id,
        'question_index' => 0,
        'selected_option' => null,
        'is_correct' => false,
    ]);
    expect($first->fresh()->review_stage)->toBe(0)
        ->and($first->fresh()->next_review_at->equalTo(now()->addDay()))->toBeTrue()
        ->and($first->fresh()->review_count)->toBe(5);
    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('問題 2 / 2')
        ->assertSee('Dashboard復習2');
});

test('dashboard review final answer redirects to the existing result and duplicate posts update once', function () {
    $user = User::factory()->create();
    $question = reviewQuestion($user, ['review_count' => 0]);
    $attempt = beginReview($this, $user);
    $payload = [
        ...reviewPayload($attempt, 0, 'A'),
        'return_to' => 'dashboard',
    ];

    $this->actingAs($user)->post(route('reviews.grade'), $payload)->assertRedirect(route('reviews.result'));
    $this->actingAs($user)->post(route('reviews.grade'), $payload)->assertRedirect(route('reviews.result'));

    expect($attempt->answers()->count())->toBe(1)
        ->and($question->fresh()->review_count)->toBe(1);
});

test('dashboard offers to start due reviews without creating an attempt', function () {
    $user = User::factory()->create();
    reviewQuestion($user);
    reviewQuestion($user);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('今日の復習')
        ->assertSee('2問あります')
        ->assertSee('忘れる前に、今日の復習を進めましょう。')
        ->assertSee('復習を始める')
        ->assertSee('href="'.route('reviews.today').'"', false)
        ->assertDontSee('続きから：今日の復習')
        ->assertDontSee('今日の1問');

    expect($user->reviewAttempts()->exists())->toBeFalse();
});

test('a normal learning set quiz does not update the review schedule', function () {
    $user = User::factory()->create();
    $question = reviewQuestion($user, ['review_stage' => 2, 'review_count' => 4]);
    $originalNextReviewAt = $question->next_review_at->toISOString();

    $this->actingAs($user)->get(route('learning-sets.quiz', $question->learningSet));
    $this->actingAs($user)->post(route('learning-sets.quiz.grade', $question->learningSet), [
        'question_index' => 0,
        'selected_option' => 'A',
    ]);

    $question->refresh();
    expect($question->review_stage)->toBe(2)
        ->and($question->review_count)->toBe(4)
        ->and($question->next_review_at->toISOString())->toBe($originalNextReviewAt);
});

/**
 * @param  array<string, mixed>  $questionOverrides
 * @param  array<string, mixed>  $learningSetOverrides
 */
function reviewQuestion(User $user, array $questionOverrides = [], array $learningSetOverrides = []): Question
{
    $learningSet = $user->learningSets()->create(array_replace([
        'title' => fake()->words(3, true),
        'source_type' => 'manual',
    ], $learningSetOverrides));

    return $learningSet->questions()->create(array_replace([
        'question' => fake()->unique()->sentence(),
        'option_a' => '正しい選択肢',
        'option_b' => '誤った選択肢B',
        'option_c' => '誤った選択肢C',
        'option_d' => '誤った選択肢D',
        'correct_option' => 'A',
        'explanation' => '復習で表示する解説です。',
        'review_stage' => 0,
        'next_review_at' => now()->subMinute(),
        'last_reviewed_at' => null,
        'review_count' => 0,
        'correct_review_count' => 0,
    ], $questionOverrides));
}

function beginReview(object $test, User $user): ReviewAttempt
{
    $test->actingAs($user)->get(route('reviews.today'))->assertOk();

    return $user->reviewAttempts()->where('status', 'in_progress')->firstOrFail();
}

/** @return array<string, int|string|null> */
function reviewPayload(ReviewAttempt $attempt, int $questionIndex, ?string $selectedOption): array
{
    return [
        'review_attempt_id' => $attempt->id,
        'question_index' => $questionIndex,
        'selected_option' => $selectedOption,
    ];
}
