<?php

use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

function createStudyModeQuestion(User $user, array $questionAttributes = [], array $learningSetAttributes = []): Question
{
    $learningSet = $user->learningSets()->create([
        'title' => $learningSetAttributes['title'] ?? '学習モード教材',
        'source_type' => 'manual',
        ...$learningSetAttributes,
    ]);

    return $learningSet->questions()->create([
        'question' => 'PHPで変数を表す記号は何ですか？',
        'option_a' => '$',
        'option_b' => '#',
        'option_c' => '&',
        'option_d' => '@',
        'correct_option' => 'A',
        'explanation' => 'PHPの変数はドル記号で始まります。',
        ...$questionAttributes,
    ]);
}

function startStudyMode(TestCase $test, User $user, string $mode = 'input', array $overrides = []): array
{
    $test->actingAs($user)->post(route('study-mode.start', $mode), [
        'scope' => 'all',
        'question_count' => 5,
        ...$overrides,
    ])->assertRedirect(route('study-mode.play'));

    return session('study_mode_attempt');
}

test('study mode routes require authentication', function () {
    $this->get(route('study-mode.index'))->assertRedirect(route('login'));
    $this->get(route('study-mode.input'))->assertRedirect(route('login'));
    $this->post(route('study-mode.start', 'input'))->assertRedirect(route('login'));
    $this->get(route('study-mode.play'))->assertRedirect(route('login'));
    $this->post(route('study-mode.answer'))->assertRedirect(route('login'));
    $this->get(route('study-mode.result'))->assertRedirect(route('login'));
});

test('study mode top shows both modes and an active sidebar item', function () {
    $user = User::factory()->create();
    createStudyModeQuestion($user);

    $this->actingAs($user)->get(route('study-mode.index'))
        ->assertOk()
        ->assertSee('学習モード')
        ->assertSee('入力式で学ぶ')
        ->assertSee('ランダムで学ぶ')
        ->assertSee('bg-blue-50 text-[#3155D9]', false)
        ->assertSee('href="'.route('study-mode.input').'"', false)
        ->assertSee('href="'.route('study-mode.random').'"', false);
});

test('study mode explains when no saved questions are available', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('study-mode.index'))
        ->assertOk()
        ->assertSee('まだ学習できる問題がありません。')
        ->assertSee('学習セットを見る')
        ->assertSee('スクリーンショットから学ぶ');
});

test('input mode hides choices while keeping learning aids available', function () {
    $user = User::factory()->create();
    $question = createStudyModeQuestion($user);
    startStudyMode($this, $user);

    $this->actingAs($user)->get(route('study-mode.play'))
        ->assertOk()
        ->assertSee($question->question)
        ->assertSee('name="typed_answer"', false)
        ->assertDontSee('name="selected_option"', false)
        ->assertSee('正解を見る')
        ->assertDontSee('解説を見る')
        ->assertSee('AIに質問する')
        ->assertSee('参照サイトを見る');

    expect($question->input_question)->toBeNull();
});

test('input mode uses the input question and keeps it on the result page', function () {
    $user = User::factory()->create();
    $question = createStudyModeQuestion($user, [
        'question' => '次のうち、Linuxで現在のディレクトリを表示するコマンドはどれか？',
        'input_question' => 'Linuxで現在のディレクトリを表示するコマンドを入力してください。',
        'option_a' => 'pwd',
    ]);
    $attempt = startStudyMode($this, $user);

    $this->actingAs($user)->get(route('study-mode.play'))
        ->assertSee($question->input_question)
        ->assertDontSee($question->question)
        ->assertDontSee('name="selected_option"', false);

    $this->actingAs($user)->post(route('study-mode.answer'), [
        'question_id' => $question->id,
        'answer_token' => $attempt['answer_token'],
        'typed_answer' => 'pwd',
    ]);
    $this->actingAs($user)->post(route('study-mode.next'), ['next_token' => session('study_mode_attempt.next_token')])
        ->assertRedirect(route('study-mode.result'));

    $this->actingAs($user)->get(route('study-mode.result'))
        ->assertSee($question->input_question)
        ->assertDontSee($question->question)
        ->assertSee('pwd');
});

test('random mode keeps the multiple choice question when an input question is available', function () {
    $user = User::factory()->create();
    $question = createStudyModeQuestion($user, [
        'question' => '4択用の問題文',
        'input_question' => '入力式用の問題文',
    ]);
    startStudyMode($this, $user, 'random');

    $this->actingAs($user)->get(route('study-mode.play'))
        ->assertSee($question->question)
        ->assertDontSee($question->input_question)
        ->assertSee('name="selected_option"', false)
        ->assertSee('A. $', false)
        ->assertSee('value="D"', false);
});

test('input answers are normalized before exact comparison', function (string $correctAnswer, string $typedAnswer) {
    $user = User::factory()->create();
    $question = createStudyModeQuestion($user, ['option_a' => $correctAnswer]);
    $attempt = startStudyMode($this, $user);

    $this->actingAs($user)->post(route('study-mode.answer'), [
        'question_id' => $question->id,
        'answer_token' => $attempt['answer_token'],
        'typed_answer' => $typedAnswer,
    ])->assertRedirect(route('study-mode.play'));

    expect(session('study_mode_attempt.feedback.status'))->toBe('correct');
})->with([
    'exact match' => ['systemctl', 'systemctl'],
    'case insensitive' => ['systemctl', 'SystemCtl'],
    'surrounding and repeated whitespace' => ['system ctl', '  system   ctl  '],
    'full width alphanumeric NFKC' => ['ABC123', 'ＡＢＣ１２３'],
]);

test('input mode distinguishes incorrect and unanswered answers', function (?string $typedAnswer, string $expectedStatus) {
    $user = User::factory()->create();
    $question = createStudyModeQuestion($user, ['option_a' => 'systemctl']);
    $attempt = startStudyMode($this, $user);

    $this->actingAs($user)->post(route('study-mode.answer'), [
        'question_id' => $question->id,
        'answer_token' => $attempt['answer_token'],
        'typed_answer' => $typedAnswer,
    ])->assertRedirect(route('study-mode.play'));

    expect(session('study_mode_attempt.feedback.status'))->toBe($expectedStatus);
})->with([
    'incorrect' => ['systemd', 'incorrect'],
    'empty' => ['', 'unanswered'],
    'full width whitespace' => ['　　', 'unanswered'],
]);

test('study mode cannot use another users question or learning set', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherQuestion = createStudyModeQuestion($otherUser);

    $this->actingAs($owner)->post(route('study-mode.start', 'random'), [
        'scope' => 'learning_set',
        'learning_set_id' => $otherQuestion->learning_set_id,
        'question_count' => 5,
    ])->assertNotFound();

    $this->actingAs($owner)
        ->withSession(['study_mode_attempt' => [
            'mode' => 'input', 'question_ids' => [$otherQuestion->id], 'current_index' => 0,
            'answers' => [], 'feedback' => null, 'answer_token' => 'token', 'next_token' => null, 'settings' => [],
        ]])
        ->get(route('study-mode.play'))
        ->assertNotFound();
});

test('random mode fixes unique owned question ids and limits to available questions', function (int $requestedCount, int $expectedCount) {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '所有教材', 'source_type' => 'manual']);
    $otherLearningSet = $otherUser->learningSets()->create(['title' => '他人教材', 'source_type' => 'manual']);

    foreach (range(1, 12) as $number) {
        $learningSet->questions()->create(['question' => "所有問題{$number}", 'option_a' => '正解', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A']);
        $otherLearningSet->questions()->create(['question' => "他人問題{$number}", 'option_a' => '正解', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A']);
    }

    $attempt = startStudyMode($this, $user, 'random', ['question_count' => $requestedCount]);

    expect($attempt['question_ids'])->toHaveCount($expectedCount)
        ->and(array_unique($attempt['question_ids']))->toHaveCount($expectedCount)
        ->and($learningSet->questions()->whereKey($attempt['question_ids'])->count())->toBe($expectedCount);
})->with([[5, 5], [10, 10], [20, 12]]);

test('random mode can be limited to one owned learning set and displays four choices', function () {
    $user = User::factory()->create();
    $selectedSet = $user->learningSets()->create(['title' => 'Linux基礎', 'source_type' => 'manual']);
    $otherSet = $user->learningSets()->create(['title' => 'PHP基礎', 'source_type' => 'manual']);
    foreach (range(1, 3) as $number) {
        $selectedSet->questions()->create(['question' => "Linux問題{$number}", 'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A']);
        $otherSet->questions()->create(['question' => "PHP問題{$number}", 'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A']);
    }

    $attempt = startStudyMode($this, $user, 'random', ['scope' => 'learning_set', 'learning_set_id' => $selectedSet->id]);

    expect($attempt['question_ids'])->toHaveCount(3)
        ->and($selectedSet->questions()->whereKey($attempt['question_ids'])->count())->toBe(3);
    $this->actingAs($user)->get(route('study-mode.play'))
        ->assertSee('name="selected_option" value="A"', false)
        ->assertSee('name="selected_option" value="D"', false);
});

test('answer and next tokens prevent double submission from advancing twice', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '二重送信教材', 'source_type' => 'manual']);
    foreach (range(1, 2) as $number) {
        $learningSet->questions()->create(['question' => "問題{$number}", 'option_a' => '正解', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A']);
    }
    $attempt = startStudyMode($this, $user, 'random');
    $payload = ['question_id' => $attempt['question_ids'][0], 'answer_token' => $attempt['answer_token'], 'selected_option' => 'A'];

    $this->actingAs($user)->post(route('study-mode.answer'), $payload);
    $this->actingAs($user)->post(route('study-mode.answer'), $payload);
    $nextToken = session('study_mode_attempt.next_token');
    $this->actingAs($user)->post(route('study-mode.next'), ['next_token' => $nextToken]);
    $this->actingAs($user)->post(route('study-mode.next'), ['next_token' => $nextToken]);

    expect(session('study_mode_attempt.answers'))->toHaveCount(1)
        ->and(session('study_mode_attempt.current_index'))->toBe(1);
});

test('input practice result shows typed answers summary and leaves review data unchanged', function () {
    Http::preventStrayRequests();
    $user = User::factory()->create();
    $question = createStudyModeQuestion($user, [
        'option_a' => 'systemctl', 'review_stage' => 3, 'next_review_at' => now()->addDays(7),
        'review_count' => 4, 'correct_review_count' => 3,
    ]);
    $originalReviewAt = $question->next_review_at->toDateTimeString();
    $attempt = startStudyMode($this, $user);

    $this->actingAs($user)->post(route('study-mode.answer'), ['question_id' => $question->id, 'answer_token' => $attempt['answer_token'], 'typed_answer' => 'SystemCtl']);
    $this->actingAs($user)->post(route('study-mode.next'), ['next_token' => session('study_mode_attempt.next_token')])->assertRedirect(route('study-mode.result'));

    $this->actingAs($user)->get(route('study-mode.result'))
        ->assertOk()
        ->assertSee('正答率')
        ->assertSee('100%')
        ->assertSeeText('1 / 1問 正解')
        ->assertSee('SystemCtl')
        ->assertSee('systemctl')
        ->assertSee('もう一度学ぶ →')
        ->assertSee('学習モードへ戻る');
    $question->refresh();
    expect($question->review_stage)->toBe(3)
        ->and($question->next_review_at->toDateTimeString())->toBe($originalReviewAt)
        ->and($question->review_count)->toBe(4)
        ->and($question->correct_review_count)->toBe(3)
        ->and($user->quizAttempts()->count())->toBe(0)
        ->and($user->reviewAttempts()->count())->toBe(0);
    Http::assertNothingSent();
});

test('random practice result shows correct incorrect and unanswered selected answers', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '結果教材', 'source_type' => 'manual']);
    foreach (range(1, 3) as $number) {
        $learningSet->questions()->create(['question' => "結果問題{$number}", 'option_a' => '正解', 'option_b' => '誤答', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A']);
    }
    $attempt = startStudyMode($this, $user, 'random');
    $selections = ['A', 'B', null];

    foreach ($selections as $selection) {
        $attempt = session('study_mode_attempt');
        $this->actingAs($user)->post(route('study-mode.answer'), [
            'question_id' => $attempt['question_ids'][$attempt['current_index']],
            'answer_token' => $attempt['answer_token'],
            'selected_option' => $selection,
        ]);
        $this->actingAs($user)->post(route('study-mode.next'), ['next_token' => session('study_mode_attempt.next_token')]);
    }

    $this->actingAs($user)->get(route('study-mode.result'))
        ->assertOk()
        ->assertSee('33%')
        ->assertSee('● 正解')
        ->assertSee('● 不正解')
        ->assertSee('○ 未回答')
        ->assertSee('A. 正解')
        ->assertSee('B. 誤答');
});

test('input and random configuration can preselect an owned collection', function (): void {
    $user = User::factory()->create();
    $question = createStudyModeQuestion($user);
    $collection = $user->learningCollections()->create(['name' => 'LinuC対策']);
    $collection->learningSets()->attach($question->learning_set_id, ['assigned_by' => 'manual']);

    foreach (['input', 'random'] as $mode) {
        $this->actingAs($user)->get(route("study-mode.{$mode}", ['collection' => $collection->id]))
            ->assertOk()
            ->assertSee('コレクションから選ぶ')
            ->assertSee('LinuC対策')
            ->assertSee('1問・1セット')
            ->assertSee('value="'.$collection->id.'" selected', false);
    }
});

test('collection scope fixes only distinct questions from its active learning sets', function (): void {
    $user = User::factory()->create();
    $first = createStudyModeQuestion($user, ['question' => 'コレクション対象1']);
    $second = createStudyModeQuestion($user, ['question' => 'コレクション対象2']);
    $outside = createStudyModeQuestion($user, ['question' => '対象外']);
    $collection = $user->learningCollections()->create(['name' => '対象Collection']);
    $collection->learningSets()->attach([$first->learning_set_id, $second->learning_set_id], ['assigned_by' => 'manual']);

    $this->actingAs($user)->post(route('study-mode.start', 'input'), [
        'scope' => 'collection', 'collection_id' => $collection->id, 'question_count' => 20,
    ])->assertRedirect(route('study-mode.play'));

    $questionIds = session('study_mode_attempt.question_ids');
    expect($questionIds)->toHaveCount(2)
        ->and(array_diff($questionIds, [$first->id, $second->id]))->toBe([])
        ->and($questionIds)->not->toContain($outside->id)
        ->and(array_unique($questionIds))->toHaveCount(2);
});

test('collection scope rejects another users collection', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    createStudyModeQuestion($user);
    $foreignQuestion = createStudyModeQuestion($otherUser);
    $foreignCollection = $otherUser->learningCollections()->create(['name' => '他人Collection']);
    $foreignCollection->learningSets()->attach($foreignQuestion->learning_set_id, ['assigned_by' => 'manual']);

    $this->actingAs($user)->get(route('study-mode.input', ['collection' => $foreignCollection->id]))->assertNotFound();
    $this->actingAs($user)->post(route('study-mode.start', 'random'), [
        'scope' => 'collection', 'collection_id' => $foreignCollection->id, 'question_count' => 5,
    ])->assertNotFound();
});

test('collection scope excludes questions from soft deleted learning sets and caps to available count', function (): void {
    $user = User::factory()->create();
    $activeQuestion = createStudyModeQuestion($user, ['question' => 'Active']);
    $deletedQuestion = createStudyModeQuestion($user, ['question' => 'Deleted']);
    $collection = $user->learningCollections()->create(['name' => '一部削除']);
    $collection->learningSets()->attach([$activeQuestion->learning_set_id, $deletedQuestion->learning_set_id], ['assigned_by' => 'manual']);
    $deletedQuestion->learningSet->delete();

    $this->actingAs($user)->post(route('study-mode.start', 'random'), [
        'scope' => 'collection', 'collection_id' => $collection->id, 'question_count' => 20,
    ])->assertRedirect(route('study-mode.play'));

    expect(session('study_mode_attempt.question_ids'))->toBe([$activeQuestion->id]);
});

test('collection changes after starting do not change fixed question ids', function (): void {
    $user = User::factory()->create();
    $initialQuestion = createStudyModeQuestion($user, ['question' => 'Initial']);
    $laterQuestion = createStudyModeQuestion($user, ['question' => 'Later']);
    $collection = $user->learningCollections()->create(['name' => '固定対象']);
    $collection->learningSets()->attach($initialQuestion->learning_set_id, ['assigned_by' => 'manual']);

    $this->actingAs($user)->post(route('study-mode.start', 'random'), [
        'scope' => 'collection', 'collection_id' => $collection->id, 'question_count' => 20,
    ]);
    $fixedIds = session('study_mode_attempt.question_ids');
    $collection->learningSets()->attach($laterQuestion->learning_set_id, ['assigned_by' => 'manual']);

    expect(session('study_mode_attempt.question_ids'))->toBe($fixedIds)
        ->and($fixedIds)->toBe([$initialQuestion->id]);
});
