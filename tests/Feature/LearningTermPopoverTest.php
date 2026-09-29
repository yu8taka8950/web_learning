<?php

use App\Models\TermExplanation;
use App\Models\User;

test('solution renders only the current users active described terms as accessible popovers', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '現在の教材', 'source_type' => 'manual']);
    $learningSet->questions()->create(['question' => '問題', 'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A', 'explanation' => 'Apacheはsystemdで管理されます。']);
    $terms = $user->learningSets()->create(['title' => '過去の教材', 'source_type' => 'manual']);
    $terms->learningTerms()->create(['term' => 'systemd', 'description' => '古い説明']);
    $newerTerms = $user->learningSets()->create(['title' => '新しい過去の教材', 'source_type' => 'manual']);
    $newerTerms->learningTerms()->create(['term' => 'systemd', 'description' => 'systemdとは、新しい説明']);
    $terms->learningTerms()->create(['term' => '空説明', 'description' => null]);
    $otherTerms = $otherUser->learningSets()->create(['title' => '他人の教材', 'source_type' => 'manual']);
    $otherTerms->learningTerms()->create(['term' => 'Apache', 'description' => '他人の説明']);
    $deletedTerms = $user->learningSets()->create(['title' => '削除済み', 'source_type' => 'manual']);
    $deletedTerms->learningTerms()->create(['term' => '管理', 'description' => '削除済み説明']);
    $deletedTerms->delete();

    $response = $this->actingAs($user)->get(route('learning-sets.quiz', $learningSet));

    $response->assertSee('term-trigger', false)
        ->assertSee('systemd')
        ->assertSee('systemdとは、新しい説明')
        ->assertDontSee('古い説明')
        ->assertDontSee('他人の説明')
        ->assertDontSee('削除済み説明')
        ->assertSee('aria-haspopup="dialog"', false)
        ->assertSee('aria-controls="learning-term-popover"', false)
        ->assertSee('data-selection-source', false);
});

test('solution escapes untrusted term descriptions and explanation text', function (): void {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '教材', 'source_type' => 'manual']);
    $learningSet->questions()->create(['question' => '問題', 'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A', 'explanation' => '<script>alert(1)</script> systemd']);
    $terms = $user->learningSets()->create(['title' => '用語', 'source_type' => 'manual']);
    $terms->learningTerms()->create(['term' => 'systemd', 'description' => '<img src=x onerror=alert(1)>']);

    $this->actingAs($user)->get(route('learning-sets.quiz', $learningSet))
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('\\u003Cimg src=x onerror=alert(1)\\u003E', false)
        ->assertDontSee('<img src=x onerror=alert(1)>', false);
});

test('a new user sees only the shared explanation for the current subject', function (): void {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $privateSet = $firstUser->learningSets()->create([
        'title' => 'Aさんの教材',
        'subject' => '宅建',
        'source_type' => 'web',
        'source_url' => 'https://private.example/a',
    ]);
    $privateSet->learningTerms()->create([
        'term' => '媒介契約',
        'description' => '先生の授業では秘密の覚え方を使います。',
        'source_url' => 'https://private.example/a',
    ]);
    TermExplanation::factory()->create([
        'subject_key' => '宅建',
        'subject_label' => '宅建',
        'normalized_term' => '媒介契約',
        'display_term' => '媒介契約',
        'explanation' => '媒介契約とは、不動産取引について宅建業者へ仲介を依頼する契約です。',
    ]);
    TermExplanation::factory()->create([
        'subject_key' => 'linuc',
        'subject_label' => 'LinuC',
        'normalized_term' => '媒介契約',
        'display_term' => '媒介契約',
        'explanation' => '別分野の説明です。',
    ]);
    $currentSet = $secondUser->learningSets()->create(['title' => '宅建教材', 'subject' => '宅建', 'source_type' => 'manual']);
    $currentSet->questions()->create([
        'question' => '問題',
        'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D',
        'correct_option' => 'A',
        'explanation' => '媒介契約を締結した場合について確認します。',
    ]);

    $this->actingAs($secondUser)->get(route('learning-sets.quiz', $currentSet))
        ->assertSee('term-trigger', false)
        ->assertSee('不動産取引について宅建業者へ仲介を依頼する契約です。')
        ->assertDontSee('媒介契約とは、不動産取引について')
        ->assertDontSee('先生の授業では秘密の覚え方を使います。')
        ->assertDontSee('private.example')
        ->assertDontSee('別分野の説明です。');
});

test('shared explanations are escaped and automatic popovers are limited to five prioritized terms', function (): void {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '数学教材', 'subject' => '数学', 'source_type' => 'manual']);
    $learningSet->questions()->create([
        'question' => '問題',
        'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D',
        'correct_option' => 'A',
        'explanation' => '平方完成 二次関数 判別式 因数分解 平方根 実数 解',
    ]);
    foreach (['平方完成', '二次関数', '判別式', '因数分解', '平方根', '実数'] as $index => $term) {
        TermExplanation::factory()->create([
            'subject_key' => '数学',
            'subject_label' => '数学',
            'normalized_term' => $term,
            'display_term' => $term,
            'explanation' => $index === 0 ? '<script>alert(1)</script>' : $term.'の一般説明',
        ]);
    }

    $response = $this->actingAs($user)->get(route('learning-sets.quiz', $learningSet));

    expect(substr_count($response->getContent(), 'class="term-trigger'))->toBe(5);
    $response->assertSee('alert(1)', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('the same normalized term is rendered as a popover only once per explanation', function (): void {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '用語教材', 'subject' => 'Web', 'source_type' => 'manual']);
    $learningSet->questions()->create([
        'question' => '問題',
        'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D',
        'correct_option' => 'A',
        'explanation' => '変数を使います。変数は値を持ちます。変数を更新できます。',
    ]);
    TermExplanation::factory()->create([
        'subject_key' => 'web',
        'subject_label' => 'Web',
        'normalized_term' => '変数',
        'display_term' => '変数',
        'explanation' => '値を保持する名前です。',
    ]);

    $html = $this->actingAs($user)->get(route('learning-sets.quiz', $learningSet))->getContent();

    expect(substr_count($html, 'class="term-trigger'))->toBe(1);
    expect($html)->toContain('を使います。')
        ->and($html)->toContain('値を持ちます。')
        ->and($html)->toContain('更新できます。');
});

test('the current question answer is excluded while another registered term remains interactive', function (): void {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '正解除外教材', 'subject' => '宅建', 'source_type' => 'manual']);
    $learningSet->questions()->create([
        'question' => '問題', 'option_a' => '自己発見取引', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D',
        'correct_option' => 'A', 'explanation' => '自己発見取引とは、媒介契約後の取引です。',
    ]);
    foreach (['自己発見取引' => '正解の説明', '媒介契約' => '媒介契約の説明'] as $term => $explanation) {
        TermExplanation::factory()->create(['subject_key' => '宅建', 'subject_label' => '宅建', 'normalized_term' => $term, 'display_term' => $term, 'explanation' => $explanation]);
    }

    $html = $this->actingAs($user)->get(route('learning-sets.quiz', $learningSet))->getContent();

    expect(substr_count($html, 'class="term-trigger'))->toBe(1)
        ->and($html)->toContain('媒介契約')
        ->and($html)->not->toContain('aria-label="用語を表示: 自己発見取引"');
});

test('a term that is correct in another question remains interactive', function (): void {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '別問題教材', 'subject' => '宅建', 'source_type' => 'manual']);
    $learningSet->questions()->create([
        'question' => '問題', 'option_a' => '別の正解', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D',
        'correct_option' => 'A', 'explanation' => '自己発見取引について確認します。',
    ]);
    TermExplanation::factory()->create(['subject_key' => '宅建', 'subject_label' => '宅建', 'normalized_term' => '自己発見取引', 'display_term' => '自己発見取引', 'explanation' => '取引の説明です。']);

    $html = $this->actingAs($user)->get(route('learning-sets.quiz', $learningSet))->getContent();

    expect(substr_count($html, 'class="term-trigger'))->toBe(1)
        ->and($html)->toContain('自己発見取引');
});
