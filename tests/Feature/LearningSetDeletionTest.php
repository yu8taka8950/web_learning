<?php

use App\Models\LearningSet;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\ReviewAttempt;
use App\Models\ReviewAttemptAnswer;
use App\Models\User;

function deletableLearningSet(User $user, array $attributes = []): LearningSet
{
    $learningSet = $user->learningSets()->create([
        'title' => '削除対象Linux教材',
        'topic' => 'systemd',
        'source_type' => 'manual',
        ...$attributes,
    ]);
    $learningSet->questions()->create([
        'question' => '削除後に出ない問題', 'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D',
        'correct_option' => 'A', 'next_review_at' => now()->subDay(), 'review_stage' => 2, 'review_count' => 3, 'correct_review_count' => 2,
    ]);

    return $learningSet;
}

test('soft deleting a learning set preserves questions and quiz and review history', function (): void {
    $user = User::factory()->create();
    $learningSet = deletableLearningSet($user);
    $question = $learningSet->questions()->firstOrFail();
    $quizAttempt = QuizAttempt::factory()->for($user)->create(['learning_set_id' => $learningSet->id, 'total_questions' => 1, 'status' => 'completed', 'completed_at' => now()]);
    $quizAnswer = QuizAttemptAnswer::factory()->for($quizAttempt)->create(['question_id' => $question->id]);
    $reviewAttempt = ReviewAttempt::factory()->for($user)->create(['question_ids' => [$question->id], 'total_questions' => 1, 'status' => 'completed', 'completed_at' => now()]);
    $reviewAnswer = ReviewAttemptAnswer::factory()->for($reviewAttempt)->create(['question_id' => $question->id]);

    $this->actingAs($user)->delete(route('learning-sets.destroy', $learningSet))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('status', '学習セットを削除しました。');

    $this->assertSoftDeleted($learningSet);
    $this->assertModelExists($question);
    $this->assertModelExists($quizAttempt);
    $this->assertModelExists($quizAnswer);
    $this->assertModelExists($reviewAttempt);
    $this->assertModelExists($reviewAnswer);
});

test('a deleted learning set is excluded from dashboard quick learning reviews study mode and collections', function (): void {
    $user = User::factory()->create();
    $learningSet = deletableLearningSet($user);
    $question = $learningSet->questions()->firstOrFail();
    $collection = $user->learningCollections()->create(['name' => 'LinuC']);
    $collection->learningSets()->attach($learningSet, ['assigned_by' => 'manual']);
    $learningSet->delete();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee($learningSet->title)
        ->assertViewHas('dashboardDueReviewCount', 0)
        ->assertViewHas('quickLearningQuestion', null);
    $this->actingAs($user)->get(route('reviews.today'))->assertSee('今日の復習はありません');
    $this->actingAs($user)->get(route('study-mode.index'))->assertSee('まだ学習できる問題がありません。');
    $this->actingAs($user)->get(route('collections.show', $collection))->assertDontSee($learningSet->title);
    expect($question->fresh()->review_stage)->toBe(2)
        ->and($question->fresh()->review_count)->toBe(3);
});

test('study mode safely interrupts when a fixed question learning set is deleted', function (): void {
    $user = User::factory()->create();
    $learningSet = deletableLearningSet($user);
    $question = $learningSet->questions()->firstOrFail();

    $this->actingAs($user)->withSession(['study_mode_attempt' => [
        'mode' => 'input', 'question_ids' => [$question->id], 'current_index' => 0, 'answers' => [], 'feedback' => null,
        'answer_token' => 'token', 'next_token' => null, 'settings' => [],
    ]]);
    $learningSet->delete();

    $this->get(route('study-mode.play'))
        ->assertRedirect(route('study-mode.index'))
        ->assertSessionHas('status', 'この学習セットは削除されたため、学習を続けられません。')
        ->assertSessionMissing('study_mode_attempt');
});

test('a user can restore only their own deleted learning set', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $learningSet = deletableLearningSet($user);
    $foreignSet = deletableLearningSet($otherUser, ['title' => '他人の削除教材']);
    $learningSet->delete();
    $foreignSet->delete();

    $this->actingAs($user)->post(route('learning-sets.restore', $foreignSet->id))->assertNotFound();
    $this->actingAs($user)->post(route('learning-sets.restore', $learningSet->id))->assertRedirect(route('dashboard'));

    expect($learningSet->fresh()->trashed())->toBeFalse();
});

test('a user cannot delete another users learning set', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $learningSet = deletableLearningSet($owner);

    $this->actingAs($otherUser)->delete(route('learning-sets.destroy', $learningSet))->assertNotFound();

    expect($learningSet->fresh()->trashed())->toBeFalse();
});

test('dashboard search finds title topic category and collection names including completed sets', function (string $query): void {
    $user = User::factory()->create();
    $learningSet = deletableLearningSet($user, ['title' => '完了済みの基礎教材', 'topic' => 'systemd']);
    $collection = $user->learningCollections()->create(['name' => 'LinuC試験対策']);
    $collection->learningSets()->attach($learningSet, ['assigned_by' => 'manual']);
    QuizAttempt::factory()->for($user)->create([
        'learning_set_id' => $learningSet->id, 'status' => 'completed', 'total_questions' => 1, 'completed_at' => now(),
    ]);

    $this->actingAs($user)->get(route('dashboard', ['q' => $query]))
        ->assertOk()
        ->assertSee('SEARCH RESULTS')
        ->assertSee($learningSet->title)
        ->assertSee('完了済み')
        ->assertSee('もう一度学ぶ');
})->with([
    'title' => ['完了済み'],
    'topic' => ['systemd'],
    'category case insensitive' => ['LINUX'],
    'collection' => ['LinuC試験対策'],
]);

test('dashboard search omits deleted sets and has a clear empty state without changing quick learning', function (): void {
    $user = User::factory()->create();
    $activeSet = deletableLearningSet($user, ['title' => 'Quick専用教材', 'topic' => '一般']);
    $activeQuestion = $activeSet->questions()->firstOrFail();
    $activeQuestion->update(['next_review_at' => now()->addDay()]);
    $deletedSet = deletableLearningSet($user, ['title' => '削除済み検索語', 'topic' => '削除済み']);
    $deletedSet->delete();

    $this->actingAs($user)->get(route('dashboard', ['q' => '該当なし']))
        ->assertOk()
        ->assertSee('該当する学習セットがありません。')
        ->assertSee('検索をクリア')
        ->assertViewHas('quickLearningQuestion', fn ($question): bool => $question?->id === $activeQuestion->id);
    $this->actingAs($user)->get(route('dashboard', ['q' => '削除済み検索語']))
        ->assertViewHas('dashboardLearningItems', fn ($items): bool => $items->isEmpty());
});
