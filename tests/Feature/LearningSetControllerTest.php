<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('guests are redirected to login for learning set pages', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->get('/learning-sets/create')->assertRedirect(route('login'));
    $this->post('/learning-sets', ['title' => 'Laravel基礎'])->assertRedirect(route('login'));
});

test('an authenticated user can create a manual learning set', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/learning-sets', [
        'title' => 'Laravel基礎',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('learning_sets', [
        'user_id' => $user->id,
        'title' => 'Laravel基礎',
        'source_type' => 'manual',
    ]);
});

test('dashboard renders the focused learning layout without the old supporting copy', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertSee('学習中')
        ->assertSee('クイック学習')
        ->assertDontSee('読むだけで、')
        ->assertDontSee('続けることで、知識はきっとあなたの力になります。')
        ->assertDontSee('最近の学び');

    expect(preg_match_all('/<article[^>]+data-learning-item/', $response->getContent()))->toBe(0);
});

test('a new user sees clear first learning actions and links', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('学習を始めよう')
        ->assertSee('Chrome右上のWeb Learning拡張機能を開いて、')
        ->assertSee('Learning ModeをON')
        ->assertSee('いつも通りWebページを読む')
        ->assertSee('学習候補が見つかったら問題を作る')
        ->assertSee('href="'.route('onboarding.show').'"', false)
        ->assertSee('href="'.route('screenshot-learning.create').'"', false)
        ->assertDontSee('拡張機能をONにする');
});

test('an existing learning set keeps the regular dashboard list', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '既存の学習セット', 'source_type' => 'manual']);
    $learningSet->questions()->create([
        'question' => '問題',
        'option_a' => 'A',
        'option_b' => 'B',
        'option_c' => 'C',
        'option_d' => 'D',
        'correct_option' => 'A',
    ]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('既存の学習セット')
        ->assertDontSee('学習を始めよう');
});

test('a user with only a deleted learning set is not treated as a new user', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '削除済みの学習セット', 'source_type' => 'manual']);
    $learningSet->delete();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('進行中の学習はありません')
        ->assertDontSee('学習を始めよう');
});

test('dashboard navigation does not expose AI usage', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('AI利用状況')
        ->assertDontSee('ai-usage.index');
});

test('dashboard presents the restored learning sections without the removed hero copy', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('よい学びを、今日も。')
        ->assertDontSee('今日はどんな知識に出会いますか？')
        ->assertDontSee('続けることで、知識はきっとあなたの力になります。')
        ->assertSee('学習を始める・続ける')
        ->assertDontSee('CONTINUE LEARNING')
        ->assertDontSee('aria-label="画像プレースホルダー"', false);
});

test('dashboard hides collection listings while retaining collection navigation and menus', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => 'Linux入門', 'topic' => 'Linuxの基本', 'source_type' => 'manual']);
    $learningSet->questions()->create(['question' => 'Linuxとは？', 'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A']);
    $collection = $user->learningCollections()->create(['name' => 'LinuC']);
    $collection->learningSets()->attach($learningSet, ['assigned_by' => 'manual']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('text-[#3155D9]">web</span>', false)
        ->assertSee('text-[#171717] dark:text-white"> learning</span>', false)
        ->assertSee('text-[#3155D9]">.</span>', false)
        ->assertDontSee('>W</span>', false)
        ->assertDontSee('>Web Learning</span>', false)
        ->assertSee('学習を始める・続ける')
        ->assertSee('コレクション')
        ->assertSee('コレクションに追加')
        ->assertDontSee('COLLECTIONS')
        ->assertDontSee('dashboard-collections-title', false);
});

test('dashboard cloaks hidden learning set controls and prevents blank searches', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => 'Linux入門', 'topic' => 'Linuxの基本', 'source_type' => 'manual']);
    $learningSet->questions()->create(['question' => 'Linuxとは？', 'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('x-cloak x-show="menuOpen"', false)
        ->assertSee('x-cloak x-show="collectionDialog"', false)
        ->assertSee('x-cloak x-show="deleteDialog"', false)
        ->assertSee('elements.q.value.trim()', false);
});

test('dashboard renders learning set deletion status as an auto dismissing toast', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->withSession(['status' => '学習セットを削除しました。'])->get(route('dashboard'))
        ->assertSee('学習セットを削除しました。')
        ->assertSee('削除済みを見る →')
        ->assertSee('href="'.route('collections.deleted').'"', false)
        ->assertSee('role="status"', false)
        ->assertSee('aria-live="polite"', false)
        ->assertSee('x-cloak', false)
        ->assertSee('setTimeout(() => show = false, 4500)', false)
        ->assertSee('aria-label="通知を閉じる"', false);
});

test('dashboard toast omits the deleted learning sets link for restoration status', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->withSession(['status' => '学習セットを復元しました。'])->get(route('dashboard'))
        ->assertSee('学習セットを復元しました。')
        ->assertDontSee('削除済みを見る →');
});

test('dashboard does not show saved learning history or another users data', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => 'Linuxの知識', 'source_type' => 'web']);
    $existingLearningSet = $user->learningSets()->create(['title' => '用語未登録の既存セット', 'source_type' => 'manual']);
    $otherLearningSet = $otherUser->learningSets()->create(['title' => '他人のセット', 'topic' => '他人だけの学習項目', 'source_type' => 'web']);
    $otherUser->quizAttempts()->create(['learning_set_id' => $otherLearningSet->id, 'current_question_index' => 0, 'total_questions' => 1, 'status' => 'in_progress', 'started_at' => now()]);

    foreach (range(1, 7) as $number) {
        $learningSet->learningTerms()->create(['term' => '自分の用語'.$number, 'source_type' => 'web']);
    }
    $otherLearningSet->learningTerms()->create(['term' => '他人だけの秘密用語', 'source_type' => 'web']);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertDontSee($existingLearningSet->title)
        ->assertDontSee('自分の用語1')
        ->assertDontSee('他人だけの学習項目')
        ->assertDontSee('他人だけの秘密用語');
});

test('dashboard shows one zero-answer quiz with scroll tracking data', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '元記事の長いタイトル', 'topic' => 'Linuxのサービス管理', 'source_type' => 'web']);
    $learningSet->questions()->create(['question' => 'systemdとは？', 'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A']);
    $user->quizAttempts()->create(['learning_set_id' => $learningSet->id, 'current_question_index' => 0, 'total_questions' => 1, 'status' => 'in_progress', 'started_at' => now()]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response
        ->assertSee('Linuxのサービス管理')
        ->assertSee('0 / 1問 完了')
        ->assertSee('学習を始める')
        ->assertSee('data-learning-item', false)
        ->assertSee('data-topic="Linuxのサービス管理"', false)
        ->assertSee('data-title="元記事の長いタイトル"', false)
        ->assertSee('data-resume-url="'.route('learning-sets.quiz', $learningSet).'"', false)
        ->assertDontSee('動作確認用');

    expect(preg_match_all('/<article[^>]+data-learning-item/', $response->getContent()))->toBe(1);
});

test('dashboard shows an unstarted learning set without creating an attempt', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => 'Chrome拡張の記事 - Web学習', 'topic' => 'ネットワーク基礎', 'source_type' => 'web']);
    foreach (range(1, 3) as $number) {
        $learningSet->questions()->create(['question' => 'ネットワーク問題'.$number, 'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A']);
    }

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertSee('これから学ぶ')
        ->assertSee('ネットワーク基礎')
        ->assertSee('0 / 3問 完了')
        ->assertSee('学習を始める →')
        ->assertSee('data-topic="ネットワーク基礎"', false)
        ->assertSee('data-title="Chrome拡張の記事 - Web学習"', false)
        ->assertSee('data-resume-url="'.route('learning-sets.quiz', $learningSet).'"', false);
    expect($user->quizAttempts()->exists())->toBeFalse();
});

test('dashboard renders Lucide icons for learning sets and navigation', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '未知の学習', 'topic' => '独自テーマ', 'source_type' => 'manual']);
    $learningSet->questions()->create(['question' => '問題', 'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-lucide="house"', false)
        ->assertSee('data-lucide="book-open"', false)
        ->assertSee('data-lucide="search"', false)
        ->assertDontSee('<svg', false);
});

test('dashboard presents today totals timeline categories and natural sticky copy', function () {
    $this->travelTo('2026-09-08 10:00:00');
    $user = User::factory()->create();
    $subjects = [
        ['Linux入門', 'Linuxのシェルについて学ぶ', 'LINUX'],
        ['AWS入門', 'AWSとクラウドの基礎', 'CLOUD'],
        ['Laravel入門', 'PHPとLaravelの基礎', 'PROGRAMMING'],
        ['読書メモ', '未知の学習テーマ', 'LEARNING'],
    ];

    foreach ($subjects as $index => [$title, $topic]) {
        $learningSet = $user->learningSets()->create([
            'title' => $title,
            'topic' => $topic,
            'source_type' => 'manual',
            'created_at' => now()->subMinutes($index),
        ]);
        $learningSet->questions()->create([
            'question' => $title.'の問題',
            'option_a' => 'A',
            'option_b' => 'B',
            'option_c' => 'C',
            'option_d' => 'D',
            'correct_option' => 'A',
            'next_review_at' => $index === 0 ? now()->subDay() : now()->addDay(),
        ]);
    }

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertSee('TODAY')
        ->assertSee('9月8日')
        ->assertSee('学習中 <strong class="font-semibold text-[#171717] dark:text-stone-100">4</strong>セット', false)
        ->assertSee('今日の復習 <strong class="font-semibold text-[#178C78]">1</strong>問', false)
        ->assertSee('data-learning-timeline', false)
        ->assertSee('LINUX')
        ->assertSee('CLOUD')
        ->assertSee('PROGRAMMING')
        ->assertSee('LEARNING')
        ->assertSee('data-completed="0"', false)
        ->assertSee('data-total="1"', false)
        ->assertSee('data-status="これから学ぶ"', false)
        ->assertSee('Linuxのシェル')
        ->assertDontSee('Linuxのシェルについて学ぶについて学ぶ')
        ->assertDontSee('読むだけで、')
        ->assertDontSee('最近の学び');

    expect(substr_count($response->getContent(), 'data-dashboard-today'))->toBe(1)
        ->and($response->getContent())->not->toContain('>Dashboard<')
        ->and($response->getContent())->not->toContain('id="in-progress-title"')
        ->and($response->getContent())->toContain('id="continue-learning-title"');
});

test('dashboard displays one resumed item per learning set and excludes completed history', function () {
    $user = User::factory()->create();
    $activeSet = $user->learningSets()->create(['title' => '重複しない教材', 'topic' => 'SQL基礎', 'source_type' => 'manual']);
    $completedSet = $user->learningSets()->create(['title' => '完了済み教材', 'source_type' => 'manual']);
    foreach ([$activeSet, $completedSet] as $learningSet) {
        foreach (range(0, 2) as $index) {
            $learningSet->questions()->create(['question' => '問題'.$learningSet->id.'-'.$index, 'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A']);
        }
    }
    $activeAttempt = $user->quizAttempts()->create(['learning_set_id' => $activeSet->id, 'current_question_index' => 1, 'total_questions' => 3, 'status' => 'in_progress', 'started_at' => now()]);
    $activeAttempt->answers()->create(['question_id' => $activeSet->questions()->first()->id, 'question_index' => 0, 'selected_option' => 'A', 'is_correct' => true, 'answered_at' => now()]);
    $user->quizAttempts()->create(['learning_set_id' => $completedSet->id, 'current_question_index' => 3, 'total_questions' => 3, 'status' => 'completed', 'started_at' => now()->subHour(), 'completed_at' => now()]);

    $response = $this->actingAs($user)->get(route('dashboard'));
    $html = $response->getContent();

    $response
        ->assertSee('途中から再開')
        ->assertSee('1 / 3問 完了')
        ->assertSee('2問目から続ける →')
        ->assertDontSee('完了済み教材');
    expect(substr_count($html, 'data-topic="SQL基礎"'))->toBe(1);
});

test('dashboard quick learning shows the most recently touched quiz or review attempt', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => 'AWS入門', 'topic' => 'AWSとクラウドコンピューティング', 'source_type' => 'manual']);
    $quizQuestion = $learningSet->questions()->create(['question' => '通常学習の問題', 'option_a' => '通常A', 'option_b' => '通常B', 'option_c' => '通常C', 'option_d' => '通常D', 'correct_option' => 'A', 'next_review_at' => now()->subDay()]);
    $reviewLearningSet = $user->learningSets()->create(['title' => '復習教材', 'source_type' => 'manual']);
    $reviewQuestion = $reviewLearningSet->questions()->create(['question' => '復習の問題', 'option_a' => '復習A', 'option_b' => '復習B', 'option_c' => '復習C', 'option_d' => '復習D', 'correct_option' => 'A', 'next_review_at' => now()->subDay()]);
    $quizAttempt = $user->quizAttempts()->create(['learning_set_id' => $learningSet->id, 'current_question_index' => 0, 'total_questions' => 1, 'status' => 'in_progress', 'started_at' => now()]);
    $reviewAttempt = $user->reviewAttempts()->create(['question_ids' => [$reviewQuestion->id], 'current_question_index' => 0, 'total_questions' => 1, 'status' => 'in_progress', 'started_at' => now()]);
    $quizAttempt->timestamps = false;
    $quizAttempt->forceFill(['updated_at' => now()->subMinute()])->save();
    $reviewAttempt->timestamps = false;
    $reviewAttempt->forceFill(['updated_at' => now()])->save();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('続きから：今日の復習')
        ->assertSee('復習の問題')
        ->assertDontSee('通常学習の問題')
        ->assertDontSee('復習を始める')
        ->assertDontSee('今日の1問');

    $quizAttempt->forceFill(['updated_at' => now()->addMinute()])->save();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('続きから：通常学習')
        ->assertSee($quizQuestion->question)
        ->assertSee('data-quick-learning-content', false)
        ->assertSee('data-selection-toolbar', false)
        ->assertSee('data-selection-field="question"', false)
        ->assertSee('data-selection-field="explanation"', false)
        ->assertDontSee($reviewQuestion->question)
        ->assertDontSee('復習を始める')
        ->assertDontSee('今日の1問');
});

test('dashboard shows an owned random question without creating progress or changing review statistics', function () {
    Http::preventStrayRequests();
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '所有する参照記事', 'source_type' => 'web', 'source_url' => 'https://example.com/owned']);
    $question = $learningSet->questions()->create([
        'question' => 'ランダム表示する問題',
        'option_a' => '選択肢A',
        'option_b' => '正解の選択肢B',
        'option_c' => '選択肢C',
        'option_d' => '選択肢D',
        'correct_option' => 'B',
        'explanation' => '保存済みのランダム問題解説',
        'review_stage' => 2,
        'next_review_at' => now()->addDays(7),
        'review_count' => 4,
        'correct_review_count' => 3,
    ]);
    $foreignSet = $otherUser->learningSets()->create(['title' => '他人の参照記事', 'source_type' => 'web']);
    $foreignSet->questions()->create(['question' => '他人だけのランダム問題', 'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A']);
    $originalNextReviewAt = $question->next_review_at->toISOString();

    $html = $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('今日の1問')
        ->assertSee('保存した問題からランダムに出題')
        ->assertSee('ランダム表示する問題')
        ->assertSee('data-quick-learning-content', false)
        ->assertSee('data-selection-toolbar', false)
        ->assertSee('ChatGPTに聞く ↗')
        ->assertDontSee('他人だけのランダム問題')
        ->assertSee('name="quick_random_option" value="A"', false)
        ->assertSee('name="quick_random_option" value="B"', false)
        ->assertSee('name="quick_random_option" value="C"', false)
        ->assertSee('name="quick_random_option" value="D"', false)
        ->assertSee('次の1問')
        ->assertDontSee('回答する')
        ->assertSee('正解を見る')
        ->assertSee('正解：</span>B. 正解の選択肢B', false)
        ->assertSee('解説を見る')
        ->assertSee('保存済みのランダム問題解説')
        ->assertSee('参照サイトを見る')
        ->assertSee('href="https://example.com/owned"', false)
        ->assertSee('target="_blank"', false)
        ->assertSee('rel="noopener noreferrer"', false)
        ->getContent();

    expect($html)->toContain(':class="showCorrect ? \'text-[#3155D9]\' : \'\'"');
    $this->assertDatabaseCount('quiz_attempts', 0);
    $this->assertDatabaseCount('quiz_attempt_answers', 0);
    $this->assertDatabaseCount('review_attempts', 0);
    $this->assertDatabaseCount('review_attempt_answers', 0);
    $question->refresh();
    expect($question->review_stage)->toBe(2)
        ->and($question->next_review_at->toISOString())->toBe($originalNextReviewAt)
        ->and($question->last_reviewed_at)->toBeNull()
        ->and($question->review_count)->toBe(4)
        ->and($question->correct_review_count)->toBe(3);
    Http::assertNothingSent();
    $this->assertDatabaseCount('ai_usage_logs', 0);
});

test('dashboard avoids repeating the previous random question when another is available', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => 'ランダム教材', 'source_type' => 'manual']);
    $first = $learningSet->questions()->create(['question' => 'ランダム候補1', 'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A']);
    $second = $learningSet->questions()->create(['question' => 'ランダム候補2', 'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A']);

    $this->actingAs($user)->get(route('dashboard'))->assertSessionHas('quick_random_question_id');
    $previousQuestionId = session('quick_random_question_id');

    $response = $this->actingAs($user)->get(route('dashboard'));
    $currentQuestionId = session('quick_random_question_id');

    expect([$first->id, $second->id])->toContain($previousQuestionId)
        ->and([$first->id, $second->id])->toContain($currentQuestionId)
        ->and($currentQuestionId)->not->toBe($previousQuestionId);
    $response->assertSee($currentQuestionId === $first->id ? 'ランダム候補1' : 'ランダム候補2');
});

test('dashboard can repeat the only owned random question', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '1問だけの教材', 'source_type' => 'manual']);
    $question = $learningSet->questions()->create(['question' => '唯一のランダム問題', 'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A']);

    $this->actingAs($user)->get(route('dashboard'))->assertSee($question->question);
    $this->actingAs($user)->get(route('dashboard'))->assertSee($question->question);

    expect(session('quick_random_question_id'))->toBe($question->id);
});

test('dashboard shows the learning CTA when only other users have saved questions', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $learningSet = $otherUser->learningSets()->create(['title' => '他人の教材', 'source_type' => 'manual']);
    $learningSet->questions()->create(['question' => '表示禁止の問題', 'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('まだ問題がありません')
        ->assertSee('Webを見るか、スクリーンショットから学習を始めると、ここにクイック学習が表示されます。')
        ->assertSee('href="'.route('screenshot-learning.create').'"', false)
        ->assertSee('href="'.route('onboarding.show').'"', false)
        ->assertDontSee('今日の1問')
        ->assertDontSee('表示禁止の問題');
});

test('an owner can add a question to their learning set', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create([
        'title' => 'Laravel基礎',
        'source_type' => 'manual',
    ]);

    $response = $this->actingAs($user)->post(route('learning-sets.questions.store', $learningSet), [
        'question' => 'Laravelのルーティング定義ファイルはどれですか？',
        'option_a' => 'routes/web.php',
        'option_b' => 'config/app.php',
        'option_c' => 'app/Models/User.php',
        'option_d' => 'resources/css/app.css',
        'correct_option' => 'A',
        'explanation' => 'Webルートはroutes/web.phpに定義します。',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('learning-sets.quiz', $learningSet));

    $this->assertDatabaseHas('questions', [
        'learning_set_id' => $learningSet->id,
        'question' => 'Laravelのルーティング定義ファイルはどれですか？',
        'correct_option' => 'A',
        'review_stage' => 0,
        'review_count' => 0,
        'correct_review_count' => 0,
    ]);
    expect($learningSet->questions()->firstOrFail()->next_review_at->isSameSecond(now()->addDay()))->toBeTrue();
});

test('another user receives a 404 for a learning set they do not own', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $learningSet = $owner->learningSets()->create([
        'title' => '所有者専用セット',
        'source_type' => 'manual',
    ]);

    $this->actingAs($otherUser)
        ->get(route('learning-sets.questions.create', $learningSet))
        ->assertNotFound();

    $this->actingAs($otherUser)
        ->post(route('learning-sets.questions.store', $learningSet), [
            'question' => '不正な追加',
            'option_a' => 'A',
            'option_b' => 'B',
            'option_c' => 'C',
            'option_d' => 'D',
            'correct_option' => 'A',
        ])
        ->assertNotFound();

    $this->actingAs($otherUser)
        ->get(route('learning-sets.quiz', $learningSet))
        ->assertNotFound();

    $this->actingAs($otherUser)
        ->post(route('learning-sets.quiz.grade', $learningSet), [
            'answers' => [],
        ])
        ->assertNotFound();
});

test('a quiz stores answers one at a time and displays the final score', function () {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create([
        'title' => 'PHP基礎',
        'source_type' => 'manual',
    ]);
    $correctQuestion = $learningSet->questions()->create([
        'question' => 'PHPの開始タグはどれですか？',
        'option_a' => '<?php',
        'option_b' => '<php>',
        'option_c' => '<script>',
        'option_d' => '<%',
        'correct_option' => 'A',
        'explanation' => 'PHPコードは<?phpで開始します。',
    ]);
    $incorrectQuestion = $learningSet->questions()->create([
        'question' => '配列の要素数を数える関数はどれですか？',
        'option_a' => 'array_count()',
        'option_b' => 'count()',
        'option_c' => 'length()',
        'option_d' => 'size()',
        'correct_option' => 'B',
        'explanation' => 'count()を使います。',
    ]);

    $this->actingAs($user)->get(route('learning-sets.quiz', $learningSet))->assertSee('問題 1 / 2');
    $this->actingAs($user)->post(route('learning-sets.quiz.grade', $learningSet), ['question_index' => 0, 'selected_option' => 'A'])->assertRedirect();
    $response = $this->actingAs($user)->post(route('learning-sets.quiz.grade', $learningSet), ['question_index' => 1, 'selected_option' => 'C'])->assertRedirect();

    $response = $this->actingAs($user)->get(route('learning-sets.quiz', $learningSet));
    $response
        ->assertSee('2問中1問正解')
        ->assertSee('正答率 50%')
        ->assertSee('LEARNING RESULT')
        ->assertDontSee('bg-[#F2F0EA]', false)
        ->assertDontSee('overflow-hidden rounded-2xl border border-stone-200 bg-[#F2F0EA]', false)
        ->assertSee('border-b border-stone-300 pb-8', false)
        ->assertSee('問題別結果')
        ->assertSee('●</span> 正解', false)
        ->assertSee('●</span> 不正解', false)
        ->assertSee('あなたの回答：A. &lt;?php', false)
        ->assertSee('あなたの回答：C. length()', false)
        ->assertSee('もう一度学習する →')
        ->assertSee('href="'.route('dashboard').'"', false);
    $this->assertDatabaseCount('quiz_attempt_answers', 2);
});
