<?php

use App\Models\AiUsageLog;
use App\Models\LearningTerm;
use App\Models\TermExplanation;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;

/** @param array<string, mixed> $learningSetAttributes */
function personalLearningTerm(User $user, string $term = 'Python', string $description = '読みやすい文法が特徴です。', array $learningSetAttributes = []): LearningTerm
{
    $learningSet = $user->learningSets()->create([
        'title' => 'Web開発の基礎',
        'subject' => 'Web開発',
        'topic' => 'Python',
        'source_type' => 'manual',
        ...$learningSetAttributes,
    ]);

    return $learningSet->learningTerms()->create([
        'term' => $term,
        'description' => $description,
        'source_type' => $learningSet->source_type,
    ]);
}

test('all personal term management routes require authentication', function (): void {
    $term = personalLearningTerm(User::factory()->create());
    $term->delete();

    $this->get(route('learning-terms.index'))->assertRedirect(route('login'));
    $this->get(route('learning-terms.edit', $term->id))->assertRedirect(route('login'));
    $this->patch(route('learning-terms.update', $term->id))->assertRedirect(route('login'));
    $this->delete(route('learning-terms.destroy', $term->id))->assertRedirect(route('login'));
    $this->patch(route('learning-terms.restore', $term->id))->assertRedirect(route('login'));
});

test('the index shows only personal term names and descriptions for the current user newest first', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $older = personalLearningTerm($user, '古い用語', '古い個人説明');
    $older->forceFill(['created_at' => now()->subDay()])->saveQuietly();
    $newer = personalLearningTerm($user, '新しい用語', '新しい個人説明', [
        'title' => '宅建業法の基礎',
        'subject' => '宅建',
        'topic' => '媒介契約',
    ]);
    $deleted = personalLearningTerm($user, '削除した用語', '削除した説明');
    $deleted->delete();
    personalLearningTerm($otherUser, '他人の秘密用語', '他人の秘密説明');
    TermExplanation::factory()->create(['display_term' => '共有だけの用語', 'normalized_term' => '共有だけの用語', 'explanation' => '共有辞書説明']);

    $response = $this->actingAs($user)->get(route('learning-terms.index'));

    $response->assertSee('マイ用語')
        ->assertSee('保存中 2語')
        ->assertSee('新しい用語')
        ->assertSee('新しい個人説明')
        ->assertSee('古い用語')
        ->assertDontSee('宅建業法の基礎')
        ->assertDontSee('宅建 / 媒介契約')
        ->assertDontSee($newer->created_at->format('Y.m.d'))
        ->assertDontSee('削除した用語')
        ->assertDontSee('他人の秘密用語')
        ->assertDontSee('他人の秘密説明')
        ->assertDontSee('共有だけの用語')
        ->assertDontSee('共有辞書説明');
    expect(strpos($response->getContent(), '新しい用語'))->toBeLessThan(strpos($response->getContent(), '古い用語'));
});

test('personal terms can be searched through term description and learning set metadata', function (string $searchQuery): void {
    $user = User::factory()->create();
    personalLearningTerm($user, 'Python', '読みやすい文法が特徴です。', [
        'title' => 'Web開発の基礎',
        'subject' => 'プログラミング',
        'topic' => 'データ分析',
    ]);
    personalLearningTerm($user, '媒介契約', '不動産取引の契約です。', [
        'title' => '宅建入門',
        'subject' => '宅建',
        'topic' => '宅建業法',
    ]);

    $this->actingAs($user)->get(route('learning-terms.index', ['q' => $searchQuery]))
        ->assertSee('Python')
        ->assertDontSee('媒介契約');
})->with([
    'term' => 'Python',
    'description' => '読みやすい',
    'learning set title' => 'Web開発の基礎',
    'subject' => 'プログラミング',
    'topic' => 'データ分析',
]);

test('the index distinguishes an empty library from an empty search result', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('learning-terms.index'))
        ->assertSee('まだ保存した用語はありません。');

    personalLearningTerm($user);
    $this->actingAs($user)->get(route('learning-terms.index', ['q' => '該当なし']))
        ->assertSee('一致する用語がありません。');
});

test('the index paginates personal terms twenty five at a time', function (): void {
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => '用語集', 'source_type' => 'manual']);
    foreach (range(1, 26) as $number) {
        $learningSet->learningTerms()->create(['term' => '用語'.$number, 'description' => '説明'.$number]);
    }

    $this->actingAs($user)->get(route('learning-terms.index'))
        ->assertViewHas('terms', fn (LengthAwarePaginator $terms): bool => $terms->perPage() === 25 && $terms->total() === 26)
        ->assertSee('page=2', false);
});

test('an owner can open the edit page including a soft deleted learning set title', function (): void {
    $user = User::factory()->create();
    $term = personalLearningTerm($user);
    $term->learningSet->delete();

    $this->actingAs($user)->get(route('learning-terms.edit', $term))
        ->assertSee('用語を編集')
        ->assertSee('Python')
        ->assertSee('読みやすい文法が特徴です。')
        ->assertSee('Web開発の基礎')
        ->assertSee('（削除済み）');
});

test('updating a personal term normalizes only editable fields without APIs logs or shared dictionary changes', function (): void {
    $user = User::factory()->create();
    $term = personalLearningTerm($user);
    $otherLearningSet = $user->learningSets()->create(['title' => '変更先にできないセット', 'source_type' => 'manual']);
    $shared = TermExplanation::factory()->create([
        'normalized_term' => 'python',
        'display_term' => 'Python',
        'explanation' => '共有説明は変更しない',
    ]);
    Http::preventStrayRequests();

    $this->actingAs($user)->patch(route('learning-terms.update', $term), [
        'term' => "  Ｐｙｔｈｏｎ\n 入門  ",
        'description' => '  自分向けの説明です。  ',
        'learning_set_id' => $otherLearningSet->id,
        'source_url' => 'https://attacker.example',
    ])->assertRedirect(route('learning-terms.index'))->assertSessionHas('status', '用語を更新しました。');

    expect($term->fresh()->term)->toBe('Python 入門')
        ->and($term->fresh()->description)->toBe('自分向けの説明です。')
        ->and($term->fresh()->learning_set_id)->toBe($term->learning_set_id)
        ->and($term->fresh()->source_url)->toBeNull()
        ->and($shared->fresh()->explanation)->toBe('共有説明は変更しない');
    expect(AiUsageLog::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('an update rejects empty overlength and normalized duplicate terms', function (): void {
    $user = User::factory()->create();
    personalLearningTerm($user, 'Python', '既存説明');
    $term = personalLearningTerm($user, '変更前', '変更前の説明');

    $this->actingAs($user)->patch(route('learning-terms.update', $term), [
        'term' => ' Ｐｙｔｈｏｎ ',
        'description' => '更新説明',
    ])->assertSessionHasErrors(['term' => '同じ用語がすでに保存されています。']);
    $this->actingAs($user)->patch(route('learning-terms.update', $term), [
        'term' => str_repeat('a', 101),
        'description' => '更新説明',
    ])->assertSessionHasErrors('term');
    $this->actingAs($user)->patch(route('learning-terms.update', $term), [
        'term' => '有効な用語',
        'description' => '   ',
    ])->assertSessionHasErrors('description');

    expect($term->fresh()->term)->toBe('変更前')
        ->and($term->fresh()->description)->toBe('変更前の説明');
});

test('other users cannot edit update delete or restore a personal term', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $term = personalLearningTerm($owner);

    $this->actingAs($otherUser)->get(route('learning-terms.edit', $term))->assertNotFound();
    $this->actingAs($otherUser)->patch(route('learning-terms.update', $term), ['term' => '改ざん', 'description' => '改ざん'])->assertNotFound();
    $this->actingAs($otherUser)->delete(route('learning-terms.destroy', $term))->assertNotFound();
    $term->delete();
    $this->actingAs($otherUser)->patch(route('learning-terms.restore', $term->id))->assertNotFound();

    expect($term->fresh()->term)->toBe('Python')
        ->and($term->fresh()->trashed())->toBeTrue();
});

test('deleting a personal term is reversible and never permanently removes its row', function (): void {
    $user = User::factory()->create();
    $term = personalLearningTerm($user);

    $this->actingAs($user)->delete(route('learning-terms.destroy', $term))
        ->assertRedirect(route('learning-terms.index'))
        ->assertSessionHas('status', '用語を削除しました。');

    $this->assertSoftDeleted($term);
    $this->assertDatabaseHas('learning_terms', ['id' => $term->id, 'term' => 'Python']);
    $this->actingAs($user)->get(route('learning-terms.index', ['status' => 'deleted']))
        ->assertSee('Python')
        ->assertSee('読みやすい文法が特徴です。')
        ->assertSee('復元');
});

test('an owner can restore a deleted personal term', function (): void {
    $user = User::factory()->create();
    $term = personalLearningTerm($user);
    $term->delete();

    $this->actingAs($user)->patch(route('learning-terms.restore', $term->id))
        ->assertRedirect(route('learning-terms.index'))
        ->assertSessionHas('status', '用語を復元しました。');

    expect($term->fresh()->trashed())->toBeFalse();
});

test('restoring a normalized duplicate personal term is rejected', function (): void {
    $user = User::factory()->create();
    $deleted = personalLearningTerm($user, 'Ｐｙｔｈｏｎ', '削除済み説明');
    $deleted->delete();
    personalLearningTerm($user, 'Python', '保存中の説明');

    $this->actingAs($user)->patch(route('learning-terms.restore', $deleted->id))
        ->assertRedirect(route('learning-terms.index', ['status' => 'deleted']))
        ->assertSessionHas('error', '同じ用語が保存中のため、復元できません。');

    expect($deleted->fresh()->trashed())->toBeTrue();
});

test('the index safely escapes personal text and omits learning set metadata', function (): void {
    $user = User::factory()->create();
    personalLearningTerm($user, '<script>term</script>', '<img src=x onerror=alert(1)>', [
        'title' => '<script>title</script>',
        'subject' => '<b>subject</b>',
        'topic' => '<i>topic</i>',
    ]);

    $this->actingAs($user)->get(route('learning-terms.index'))
        ->assertDontSee('<script>term</script>', false)
        ->assertDontSee('<img src=x onerror=alert(1)>', false)
        ->assertDontSee('<script>title</script>', false)
        ->assertDontSee('<b>subject</b>', false)
        ->assertDontSee('<i>topic</i>', false)
        ->assertSee('&lt;script&gt;term&lt;/script&gt;', false)
        ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
        ->assertDontSee('&lt;script&gt;title&lt;/script&gt;', false)
        ->assertDontSee('&lt;b&gt;subject&lt;/b&gt;', false)
        ->assertDontSee('&lt;i&gt;topic&lt;/i&gt;', false);
});

test('the sidebar links to personal terms between collections and deleted items', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('learning-terms.index'));
    $content = $response->getContent();

    $response->assertSee('href="'.route('learning-terms.index').'"', false);
    expect(strpos($content, 'コレクション'))->toBeLessThan(strpos($content, 'マイ用語'))
        ->and(strpos($content, 'マイ用語'))->toBeLessThan(strpos($content, '削除済み'));
});
