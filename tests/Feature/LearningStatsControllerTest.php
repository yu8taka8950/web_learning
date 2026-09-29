<?php

use App\Models\Question;
use App\Models\QuizAttempt;
use App\Models\ReviewAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-03 10:00:00', 'Asia/Tokyo'));
});

test('guests are redirected to login', function () {
    $this->get(route('learning-stats.index'))->assertRedirect(route('login'));
});

test('authenticated users can view empty learning statistics without division by zero', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('learning-stats.index'))
        ->assertOk()
        ->assertViewHas('learningSetCount', 0)
        ->assertViewHas('questionCount', 0)
        ->assertViewHas('dueReviewCount', 0)
        ->assertViewHas('totalReviewCount', 0)
        ->assertViewHas('totalCorrectReviewCount', 0)
        ->assertViewHas('reviewAccuracy', 0)
        ->assertViewHas('weekStats', fn ($weekStats): bool => $weekStats->count() === 7 && $weekStats->every(fn (array $day): bool => $day['count'] === 0))
        ->assertViewHas('recentReviewAnswers', fn ($answers): bool => $answers->isEmpty())
        ->assertSee('今日')
        ->assertSee('解いた問題')
        ->assertSee('復習する問題')
        ->assertSee('今週')
        ->assertSee('今週の学習記録はまだありません。')
        ->assertSee('これまで')
        ->assertSee('要復習')
        ->assertSee('現在は要復習の問題はありません。')
        ->assertSee('まだ復習記録はありません。');
});

test('learning statistics use a two-column editorial layout instead of dashboard cards', function (): void {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '記録セット', 'source_type' => 'manual']);
    createStatsQuestion($learningSet, [
        'question' => '苦手な問題の表示確認',
        'review_count' => 2,
        'correct_review_count' => 0,
        'next_review_at' => now(),
        'last_reviewed_at' => now(),
    ]);

    $this->actingAs($user)->get(route('learning-stats.index'))
        ->assertOk()
        ->assertSee('今日')
        ->assertSee('復習する問題')
        ->assertSee('復習を始める →')
        ->assertSee('今週')
        ->assertSee('lg:grid-cols-[minmax(0,7fr)_minmax(15rem,3fr)]', false)
        ->assertSee('data-weekly-days', false)
        ->assertSee('lg:col-span-2', false)
        ->assertSee('border border-transparent', false)
        ->assertSee('hover:border-[#3155D9]/45', false)
        ->assertDontSee('bg-[#EEF8F5]', false)
        ->assertDontSee('data-weekly-bars', false)
        ->assertSee('これまで')
        ->assertDontSee('学習の記録')
        ->assertDontSee('今日の復習対象')
        ->assertSee('要復習')
        ->assertSee('最近の復習')
        ->assertSee('苦手な問題の表示確認')
        ->assertSee('border-b border-stone-300', false)
        ->assertDontSee('rounded-xl bg-white p-5 shadow-sm dark:bg-gray-800', false)
        ->assertDontSee('overflow-x-auto rounded-xl bg-white shadow-sm', false);
});

test('learning statistics count answered quiz and review responses by day and in total', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '回答記録セット', 'source_type' => 'manual']);
    $otherSet = $otherUser->learningSets()->create(['title' => '他人セット', 'source_type' => 'manual']);
    $firstQuestion = createStatsQuestion($learningSet, ['question' => '今日の正解']);
    $secondQuestion = createStatsQuestion($learningSet, ['question' => '今日の不正解']);
    $pastQuestion = createStatsQuestion($learningSet, ['question' => '過去の回答']);
    $otherQuestion = createStatsQuestion($otherSet, ['question' => '他人の回答']);

    $quizAttempt = QuizAttempt::factory()->for($user)->for($learningSet)->create(['total_questions' => 3]);
    $quizAttempt->answers()->createMany([
        ['question_id' => $firstQuestion->id, 'question_index' => 0, 'selected_option' => 'A', 'is_correct' => true, 'answered_at' => now()],
        ['question_id' => $secondQuestion->id, 'question_index' => 1, 'selected_option' => 'B', 'is_correct' => false, 'answered_at' => now()],
        ['question_id' => $pastQuestion->id, 'question_index' => 2, 'selected_option' => 'A', 'is_correct' => true, 'answered_at' => now()->subDay()],
        ['question_id' => null, 'question_index' => 3, 'selected_option' => null, 'is_correct' => false, 'answered_at' => now()],
    ]);

    $reviewAttempt = ReviewAttempt::factory()->for($user)->create(['question_ids' => [$firstQuestion->id], 'total_questions' => 1]);
    $reviewAttempt->answers()->create(['question_id' => $firstQuestion->id, 'question_index' => 0, 'selected_option' => 'A', 'is_correct' => true, 'answered_at' => now()]);
    $otherAttempt = QuizAttempt::factory()->for($otherUser)->for($otherSet)->create();
    $otherAttempt->answers()->create(['question_id' => $otherQuestion->id, 'question_index' => 0, 'selected_option' => 'A', 'is_correct' => true, 'answered_at' => now()]);

    $response = $this->actingAs($user)->get(route('learning-stats.index'))->assertOk();
    $todayStats = $response->viewData('todayStats');
    $allTimeStats = $response->viewData('allTimeStats');

    expect($todayStats)->toBe(['answered' => 3, 'correct' => 2, 'accuracy' => 67])
        ->and($allTimeStats)->toBe(['answered' => 4, 'correct' => 3, 'accuracy' => 75])
        ->and($response->getContent())->toContain('3問', '2問', '67%', '4問', '75%')
        ->not->toContain('他人の回答');
});

test('weak question list shows the correct option text without learning set or next review metadata', function (): void {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '非表示にする学習セット', 'source_type' => 'manual']);
    foreach (['A', 'B', 'C', 'D'] as $option) {
        createStatsQuestion($learningSet, [
            'question' => "正解{$option}の問題",
            'option_a' => '答えA',
            'option_b' => '答えB',
            'option_c' => '答えC',
            'option_d' => '答えD',
            'correct_option' => $option,
            'review_count' => 2,
            'correct_review_count' => 0,
        ]);
    }

    $this->actingAs($user)->get(route('learning-stats.index'))
        ->assertOk()
        ->assertSee('答えA')
        ->assertSee('答えB')
        ->assertSee('答えC')
        ->assertSee('答えD')
        ->assertSee('正解を見る')
        ->assertSee('x-show="revealed"', false)
        ->assertDontSee('学習セット：', false)
        ->assertDontSee('次回復習：', false)
        ->assertDontSee('非表示にする学習セット');
});

test('learning set question and due review counts include only owned data', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $firstSet = $user->learningSets()->create(['title' => '自分のセット1', 'source_type' => 'manual']);
    $secondSet = $user->learningSets()->create(['title' => '自分のセット2', 'source_type' => 'manual']);
    createStatsQuestion($firstSet, ['next_review_at' => now()]);
    createStatsQuestion($firstSet, ['next_review_at' => now()->subDay()]);
    createStatsQuestion($secondSet, ['next_review_at' => now()->addDay()]);
    createStatsQuestion($otherUser->learningSets()->create(['title' => '他人のセット', 'source_type' => 'manual']), ['next_review_at' => now()->subDay()]);

    $this->actingAs($user)->get(route('learning-stats.index'))
        ->assertOk()
        ->assertViewHas('learningSetCount', 2)
        ->assertViewHas('questionCount', 3)
        ->assertViewHas('dueReviewCount', 2)
        ->assertSee('学習セット')
        ->assertSee('3問')
        ->assertDontSee('他人のセット');
});

test('review totals and accuracy are calculated from owned questions', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '集計セット', 'source_type' => 'manual']);
    createStatsQuestion($learningSet, ['review_count' => 3, 'correct_review_count' => 2]);
    createStatsQuestion($learningSet, ['review_count' => 5, 'correct_review_count' => 4]);
    createStatsQuestion($otherUser->learningSets()->create(['title' => '除外セット', 'source_type' => 'manual']), ['review_count' => 100, 'correct_review_count' => 100]);

    $this->actingAs($user)->get(route('learning-stats.index'))
        ->assertOk()
        ->assertViewHas('totalReviewCount', 8)
        ->assertViewHas('totalCorrectReviewCount', 6)
        ->assertViewHas('reviewAccuracy', 75);
});

test('weak questions are limited to five and ordered by accuracy then review count', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '苦手セット', 'source_type' => 'manual']);
    createStatsQuestion($learningSet, ['question' => '対象外の1回復習', 'review_count' => 1, 'correct_review_count' => 0]);
    createStatsQuestion($learningSet, ['question' => '正答率50パーセント', 'review_count' => 2, 'correct_review_count' => 1]);
    createStatsQuestion($learningSet, ['question' => '正答率25パーセント4回', 'review_count' => 4, 'correct_review_count' => 1]);
    createStatsQuestion($learningSet, ['question' => '正答率25パーセント8回', 'review_count' => 8, 'correct_review_count' => 2]);

    foreach (range(1, 8) as $number) {
        createStatsQuestion($learningSet, [
            'question' => sprintf('追加苦手問題%02d', $number),
            'review_count' => 10,
            'correct_review_count' => $number,
        ]);
    }

    $weakQuestions = $this->actingAs($user)
        ->get(route('learning-stats.index'))
        ->assertOk()
        ->viewData('weakQuestions');

    expect($weakQuestions)->toHaveCount(5)
        ->and($weakQuestions->pluck('question')->all())->toBe(['追加苦手問題01', '追加苦手問題02', '正答率25パーセント8回', '正答率25パーセント4回', '追加苦手問題03'])
        ->and($weakQuestions->pluck('question'))->not->toContain('対象外の1回復習', '正答率50パーセント');
});

test('weekly answers are grouped from Monday through Sunday with accessible circles', function (): void {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '今週セット', 'source_type' => 'manual']);
    $mondayQuestion = createStatsQuestion($learningSet, ['question' => '月曜日の問題']);
    $wednesdayQuestion = createStatsQuestion($learningSet, ['question' => '水曜日の問題']);

    $quizAttempt = QuizAttempt::factory()->for($user)->for($learningSet)->create(['total_questions' => 2]);
    $quizAttempt->answers()->createMany([
        ['question_id' => $mondayQuestion->id, 'question_index' => 0, 'selected_option' => 'A', 'is_correct' => true, 'answered_at' => now()->startOfWeek()],
        ['question_id' => $mondayQuestion->id, 'question_index' => 1, 'selected_option' => 'A', 'is_correct' => true, 'answered_at' => now()->startOfWeek()->addHour()],
    ]);
    $reviewAttempt = ReviewAttempt::factory()->for($user)->create(['question_ids' => [$wednesdayQuestion->id], 'total_questions' => 1]);
    $reviewAttempt->answers()->create(['question_id' => $wednesdayQuestion->id, 'question_index' => 0, 'selected_option' => 'A', 'is_correct' => false, 'answered_at' => now()->startOfWeek()->addDays(2)]);

    $response = $this->actingAs($user)->get(route('learning-stats.index'))->assertOk();
    $weekStats = $response->viewData('weekStats');

    expect($weekStats)->toHaveCount(7)
        ->and($weekStats->pluck('count')->all())->toBe([2, 0, 1, 0, 0, 0, 0]);

    $response
        ->assertSee('aria-label="月曜日 2問"', false)
        ->assertSee('aria-label="火曜日 0問"', false)
        ->assertSee('aria-label="水曜日 1問"', false)
        ->assertSee('rounded-full', false)
        ->assertSee('bg-[#EEF3FF]', false)
        ->assertDontSee('style="height:', false);
});

test('recent review answers are limited to five, ordered by answer time, and scoped to the user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '最近セット', 'source_type' => 'manual']);
    $otherSet = $otherUser->learningSets()->create(['title' => '他人の最近セット', 'source_type' => 'manual']);

    foreach (range(1, 6) as $number) {
        $question = createStatsQuestion($learningSet, ['question' => sprintf('最近の復習%02d', $number)]);
        $attempt = ReviewAttempt::factory()->for($user)->create(['question_ids' => [$question->id], 'total_questions' => 1]);
        $attempt->answers()->create([
            'question_id' => $question->id,
            'question_index' => 0,
            'selected_option' => 'A',
            'is_correct' => true,
            'answered_at' => now()->subMinutes($number),
        ]);
    }

    $otherQuestion = createStatsQuestion($otherSet, ['question' => '他人の復習']);
    $otherAttempt = ReviewAttempt::factory()->for($otherUser)->create(['question_ids' => [$otherQuestion->id], 'total_questions' => 1]);
    $otherAttempt->answers()->create(['question_id' => $otherQuestion->id, 'question_index' => 0, 'selected_option' => 'A', 'is_correct' => true, 'answered_at' => now()]);

    $recentAnswers = $this->actingAs($user)
        ->get(route('learning-stats.index'))
        ->assertOk()
        ->viewData('recentReviewAnswers');

    expect($recentAnswers)->toHaveCount(5)
        ->and($recentAnswers->first()->question->question)->toBe('最近の復習01')
        ->and($recentAnswers->last()->question->question)->toBe('最近の復習05')
        ->and($recentAnswers->pluck('question.question'))->not->toContain('最近の復習06', '他人の復習');
});

test('recent review timeline displays a short date and correct or incorrect status', function (): void {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '最近の復習セット名', 'source_type' => 'manual']);
    $correctQuestion = createStatsQuestion($learningSet, ['question' => '正解の復習問題']);
    $incorrectQuestion = createStatsQuestion($learningSet, ['question' => '不正解の復習問題']);
    $correctAttempt = ReviewAttempt::factory()->for($user)->create(['question_ids' => [$correctQuestion->id], 'total_questions' => 1]);
    $correctAttempt->answers()->create(['question_id' => $correctQuestion->id, 'question_index' => 0, 'selected_option' => 'A', 'is_correct' => true, 'answered_at' => now()]);
    $incorrectAttempt = ReviewAttempt::factory()->for($user)->create(['question_ids' => [$incorrectQuestion->id], 'total_questions' => 1]);
    $incorrectAttempt->answers()->create(['question_id' => $incorrectQuestion->id, 'question_index' => 0, 'selected_option' => null, 'is_correct' => false, 'answered_at' => now()->subMinute()]);

    $this->actingAs($user)->get(route('learning-stats.index'))
        ->assertOk()
        ->assertSee('正解の復習問題')
        ->assertSee('不正解の復習問題')
        ->assertSee(now()->format('n/j'))
        ->assertSee('aria-hidden="true">○</span>', false)
        ->assertSee('aria-hidden="true">×</span>', false)
        ->assertDontSee('最近の復習セット名')
        ->assertDontSee('復習成績：', false)
        ->assertDontSee('最終復習：', false)
        ->assertDontSee('次回復習：', false);
});

test('learning statistics escape question text from review records', function (): void {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => 'XSSセット', 'source_type' => 'manual']);
    $questionText = '<script>alert("review")</script>';
    $question = createStatsQuestion($learningSet, ['question' => $questionText]);
    $attempt = ReviewAttempt::factory()->for($user)->create(['question_ids' => [$question->id], 'total_questions' => 1]);
    $attempt->answers()->create(['question_id' => $question->id, 'question_index' => 0, 'selected_option' => 'A', 'is_correct' => true, 'answered_at' => now()]);

    $this->actingAs($user)->get(route('learning-stats.index'))
        ->assertOk()
        ->assertSee(e($questionText), false)
        ->assertDontSee($questionText, false);
});

test('learning statistics remain on their details page and are not duplicated on dashboard', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    createStatsQuestion($user->learningSets()->create(['title' => '自分のセット', 'source_type' => 'manual']), ['review_count' => 4, 'correct_review_count' => 3]);
    createStatsQuestion($otherUser->learningSets()->create(['title' => '他人のセット', 'source_type' => 'manual']), ['review_count' => 20, 'correct_review_count' => 20]);

    $this->actingAs($user)->get(route('learning-stats.index'))
        ->assertOk()
        ->assertSee('保存した問題')
        ->assertViewHas('allTimeStats');

    $this->actingAs($user)->get(route('dashboard'))
        ->assertDontSee('保存問題')
        ->assertDontSee('復習正答率');
});

test('viewing learning statistics does not make external API requests', function () {
    Http::preventStrayRequests();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('learning-stats.index'))->assertOk();

    Http::assertNothingSent();
});

/** @param array<string, mixed> $overrides */
function createStatsQuestion(object $learningSet, array $overrides = []): Question
{
    return $learningSet->questions()->create(array_replace([
        'question' => fake()->unique()->sentence(),
        'option_a' => '正しい選択肢',
        'option_b' => '誤った選択肢B',
        'option_c' => '誤った選択肢C',
        'option_d' => '誤った選択肢D',
        'correct_option' => 'A',
        'explanation' => '解説です。',
        'review_stage' => 0,
        'next_review_at' => now()->addDay(),
        'last_reviewed_at' => null,
        'review_count' => 0,
        'correct_review_count' => 0,
    ], $overrides));
}
