<?php

use App\Models\AiUsageLog;
use App\Models\LearningTerm;
use App\Models\LearningTermBox;
use App\Models\TermExplanation;
use App\Models\User;
use App\Services\DictionaryNormalizer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function termBoxLearningTerm(User $user, string $term = '媒介契約', ?string $subject = '宅建', string $description = '個人用の説明です。'): LearningTerm
{
    $learningSet = $user->learningSets()->create([
        'title' => $subject === null ? '分野なし教材' : $subject.'教材',
        'subject' => $subject,
        'topic' => '検索用トピック',
        'source_type' => 'manual',
    ]);

    return $learningSet->learningTerms()->create(['term' => $term, 'description' => $description]);
}

function manualTermBox(User $user, string $name): LearningTermBox
{
    return $user->learningTermBoxes()->create([
        'name' => $name,
        'name_key' => app(DictionaryNormalizer::class)->key($name),
        'kind' => LearningTermBox::KIND_MANUAL,
    ]);
}

function legacyAutomaticTermBox(User $user, string $name = '旧自動カテゴリ'): LearningTermBox
{
    return $user->learningTermBoxes()->create([
        'name' => $name,
        'name_key' => app(DictionaryNormalizer::class)->key($name),
        'kind' => LearningTermBox::KIND_AUTO,
        'auto_rule' => 'subject:'.app(DictionaryNormalizer::class)->key($name),
    ]);
}

test('category routes require authentication', function (): void {
    $user = User::factory()->create();
    $term = termBoxLearningTerm($user);
    $category = manualTermBox($user, '試験前');

    $this->get(route('learning-terms.index', ['box' => $category]))->assertRedirect(route('login'));
    $this->post(route('learning-term-boxes.store'), ['name' => '苦手'])->assertRedirect(route('login'));
    $this->patch(route('learning-term-boxes.update', $category), ['name' => '重要'])->assertRedirect(route('login'));
    $this->delete(route('learning-term-boxes.destroy', $category))->assertRedirect(route('login'));
    $this->put(route('learning-terms.boxes.update', $term), ['manual_box_ids' => [$category->id]])->assertRedirect(route('login'));
    $this->put(route('learning-term-boxes.terms.update', $category), ['term_ids' => [$term->id]])->assertRedirect(route('login'));
    $this->delete(route('learning-term-boxes.terms.destroy', [$category, $term]))->assertRedirect(route('login'));
    $this->post(route('learning-terms.bulk-categories.update'), ['term_ids' => [$term->id], 'category_ids' => [$category->id]])->assertRedirect(route('login'));
});

test('a user can create normalize rename and soft delete a category without deleting terms or shared explanations', function (): void {
    Http::preventStrayRequests();
    $user = User::factory()->create();
    $term = termBoxLearningTerm($user);
    $sharedExplanation = TermExplanation::factory()->create();

    $this->actingAs($user)->post(route('learning-term-boxes.store'), [
        'name' => "  \u{FF33}\u{FF34}\u{FF35}\u{FF24}\u{FF39}\n  \u{3000}\u{FF22}\u{FF2F}\u{FF38}  ",
        'kind' => LearningTermBox::KIND_AUTO,
        'auto_rule' => 'attacker',
        'user_id' => User::factory()->create()->id,
    ])->assertRedirect(route('learning-terms.index'));
    $category = $user->learningTermBoxes()->firstOrFail();

    expect($category->name)->toBe('STUDY BOX')
        ->and($category->name_key)->toBe('study box')
        ->and($category->kind)->toBe(LearningTermBox::KIND_MANUAL)
        ->and($category->auto_rule)->toBeNull();

    $category->learningTerms()->attach($term, ['assigned_by' => 'manual']);
    $this->actingAs($user)->patch(route('learning-term-boxes.update', $category), ['name' => '  試験前に覚える  '])
        ->assertRedirect(route('learning-terms.index', ['box' => $category->id]));
    $this->actingAs($user)->delete(route('learning-term-boxes.destroy', $category))
        ->assertRedirect(route('learning-terms.index'));

    $this->assertSoftDeleted($category);
    $this->assertModelExists($term);
    $this->assertModelExists($sharedExplanation);
    $this->assertDatabaseMissing('learning_term_learning_term_box', ['learning_term_box_id' => $category->id]);
    expect(AiUsageLog::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('category names are unique after normalization', function (): void {
    $user = User::factory()->create();
    manualTermBox($user, 'Study Box');

    $this->actingAs($user)->post(route('learning-term-boxes.store'), ['name' => '  ＳＴＵＤＹ   ＢＯＸ '])
        ->assertSessionHasErrors(['name' => '同じ名前のカテゴリがあります。']);
});

test('category names are required and limited to eighty normalized characters', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('learning-term-boxes.store'), ['name' => " \n \u{3000} "])
        ->assertSessionHasErrors('name');
    $this->actingAs($user)->post(route('learning-term-boxes.store'), ['name' => str_repeat('類', 81)])
        ->assertSessionHasErrors('name');

    expect($user->learningTermBoxes()->count())->toBe(0);
});

test('legacy automatic categories cannot be viewed renamed or deleted', function (): void {
    $user = User::factory()->create();
    $category = legacyAutomaticTermBox($user);

    $this->actingAs($user)->get(route('learning-terms.index', ['box' => $category]))->assertNotFound();
    $this->actingAs($user)->patch(route('learning-term-boxes.update', $category), ['name' => '変更'])->assertForbidden();
    $this->actingAs($user)->delete(route('learning-term-boxes.destroy', $category))->assertForbidden();

    expect($category->fresh()->name)->toBe('旧自動カテゴリ')
        ->and($category->fresh()->trashed())->toBeFalse();
});

test('other users cannot view update delete or use a category and cannot assign foreign terms', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $ownerTerm = termBoxLearningTerm($owner);
    $otherTerm = termBoxLearningTerm($otherUser, 'Laravel', 'Web開発');
    $ownerCategory = manualTermBox($owner, '所有者カテゴリ');
    $otherCategory = manualTermBox($otherUser, '他人カテゴリ');

    $this->actingAs($otherUser)->get(route('learning-terms.index', ['box' => $ownerCategory]))->assertNotFound();
    $this->actingAs($otherUser)->patch(route('learning-term-boxes.update', $ownerCategory), ['name' => '改ざん'])->assertNotFound();
    $this->actingAs($otherUser)->delete(route('learning-term-boxes.destroy', $ownerCategory))->assertNotFound();
    $this->actingAs($owner)->put(route('learning-terms.boxes.update', $ownerTerm), ['manual_box_ids' => [$otherCategory->id]])->assertNotFound();
    $this->actingAs($owner)->put(route('learning-terms.boxes.update', $otherTerm), ['manual_box_ids' => [$ownerCategory->id]])->assertNotFound();
    $this->actingAs($owner)->put(route('learning-term-boxes.terms.update', $otherCategory), ['term_ids' => [$ownerTerm->id]])->assertNotFound();
    $this->actingAs($owner)->put(route('learning-term-boxes.terms.update', $ownerCategory), ['term_ids' => [$otherTerm->id]])->assertNotFound();
    $this->actingAs($owner)->delete(route('learning-term-boxes.terms.destroy', [$otherCategory, $ownerTerm]))->assertNotFound();
    $this->actingAs($owner)->delete(route('learning-term-boxes.terms.destroy', [$ownerCategory, $otherTerm]))->assertNotFound();

    $this->assertDatabaseCount('learning_term_learning_term_box', 0);
});

test('a term can use several categories and leave one without deleting the term', function (): void {
    Http::preventStrayRequests();
    $user = User::factory()->create();
    $term = termBoxLearningTerm($user);
    $firstCategory = manualTermBox($user, '試験前');
    $secondCategory = manualTermBox($user, '苦手');

    $this->actingAs($user)->put(route('learning-terms.boxes.update', $term), [
        'manual_box_ids' => [$firstCategory->id, $secondCategory->id],
    ])->assertRedirect(route('learning-terms.edit', $term));
    expect($term->boxes()->count())->toBe(2);

    $this->actingAs($user)->put(route('learning-terms.boxes.update', $term), [
        'manual_box_ids' => [$secondCategory->id],
    ])->assertRedirect(route('learning-terms.edit', $term));

    expect($term->boxes()->pluck('learning_term_boxes.id')->all())->toBe([$secondCategory->id]);
    $this->assertModelExists($term);
    expect(AiUsageLog::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('the index shows only manual categories before the all terms heading with escaped previews', function (): void {
    $user = User::factory()->create();
    $category = manualTermBox($user, '<script>重要</script>');
    $emptyCategory = manualTermBox($user, '面接対策');
    $legacyCategory = legacyAutomaticTermBox($user, '自動カテゴリ表示禁止');
    foreach (range(1, 4) as $number) {
        $term = termBoxLearningTerm($user, 'プレビュー'.$number);
        $category->learningTerms()->attach($term, ['assigned_by' => 'manual']);
    }
    $legacyCategory->learningTerms()->attach(termBoxLearningTerm($user, '通常一覧に残る用語'), ['assigned_by' => 'auto']);

    $response = $this->actingAs($user)->get(route('learning-terms.index'));
    $content = $response->getContent();

    $response
        ->assertViewHas('manualBoxes', fn ($categories): bool => $categories->first()->relationLoaded('learningTerms')
            && $categories->first()->learningTerms->count() === 3)
        ->assertSee('&lt;script&gt;重要&lt;/script&gt;', false)
        ->assertDontSee('<script>重要</script>', false)
        ->assertDontSee($legacyCategory->name)
        ->assertSee('カテゴリ')
        ->assertSee('＋ カテゴリを作る')
        ->assertSee('すべての用語')
        ->assertSee('保存中 5語')
        ->assertSee('まとめて追加')
        ->assertSee('表示中をすべて選択')
        ->assertSee('x-show="bulkMode"', false)
        ->assertSee('x-show="! bulkMode"', false)
        ->assertSee('面接対策')
        ->assertSee('0語')
        ->assertSee('プレビュー1')
        ->assertSee('プレビュー2')
        ->assertSee('プレビュー3')
        ->assertSee('プレビュー4')
        ->assertSee('通常一覧に残る用語')
        ->assertSee('>追加</button>', false)
        ->assertDontSee('>カテゴリ追加</button>', false)
        ->assertSee('value="'.$category->id.'" checked', false)
        ->assertDontSee('自動分類')
        ->assertDontSee('分類を更新')
        ->assertDontSee('未分類')
        ->assertDontSee('保存した用語を見返したり、必要に応じてカテゴリで整理できます。')
        ->assertDontSee('カテゴリは、整理したいときだけ自由に追加できます。');

    expect(substr_count($content, 'id="new-box-name"'))->toBe(1)
        ->and(substr_count($content, 'すべての用語'))->toBe(1)
        ->and(substr_count($content, '>追加</button>'))->toBe(5)
        ->and(strpos($content, 'id="categories-title"'))->toBeLessThan(strpos($content, 'すべての用語'));
    expect($emptyCategory->fresh()->trashed())->toBeFalse();
});

test('the index remains natural without categories and uncategorized terms stay in all terms', function (): void {
    $user = User::factory()->create();
    $term = termBoxLearningTerm($user, 'カテゴリなし用語');

    $response = $this->actingAs($user)->get(route('learning-terms.index'));

    $response
        ->assertSee('カテゴリ')
        ->assertSee('まだカテゴリはありません。必要なときに自分でカテゴリを作成できます。')
        ->assertSee('すべての用語')
        ->assertSee('カテゴリなし用語')
        ->assertSee('まとめて追加')
        ->assertSee(':disabled="selectedTermIds.length === 0"', false)
        ->assertSee('>追加</button>', false)
        ->assertSee('まだカテゴリがありません。')
        ->assertSee('＋ カテゴリを作る')
        ->assertDontSee('未分類');
    expect($term->boxes()->exists())->toBeFalse();
});

test('the edit page shows only manual category selections', function (): void {
    $user = User::factory()->create();
    $term = termBoxLearningTerm($user);
    $selectedCategory = manualTermBox($user, '試験前');
    $otherCategory = manualTermBox($user, '苦手');
    $legacyCategory = legacyAutomaticTermBox($user);
    $selectedCategory->learningTerms()->attach($term, ['assigned_by' => 'manual']);
    $legacyCategory->learningTerms()->attach($term, ['assigned_by' => 'auto']);

    $this->actingAs($user)->get(route('learning-terms.edit', $term))
        ->assertSee('カテゴリ')
        ->assertSee('試験前')
        ->assertSee('苦手')
        ->assertDontSee($legacyCategory->name)
        ->assertDontSee('自動分類')
        ->assertDontSee('未分類')
        ->assertSee('value="'.$selectedCategory->id.'" checked', false)
        ->assertSee('value="'.$otherCategory->id.'"', false);
});

test('a selected category has a focused detail interface and paginates only its active terms', function (): void {
    $user = User::factory()->create();
    $category = manualTermBox($user, '試験前');
    foreach (range(1, 26) as $number) {
        $term = termBoxLearningTerm($user, 'カテゴリ内用語'.$number, '宅建', '説明'.$number);
        $category->learningTerms()->attach($term, ['assigned_by' => 'manual']);
    }
    termBoxLearningTerm($user, 'カテゴリ外用語', 'Web開発');

    $response = $this->actingAs($user)->get(route('learning-terms.index', ['box' => $category, 'q' => '一致しない検索語']));
    $content = $response->getContent();

    $response
        ->assertViewHas('terms', fn (LengthAwarePaginator $terms): bool => $terms->total() === 26
            && $terms->perPage() === 25
            && $terms->every(fn (LearningTerm $term): bool => $term->term !== 'カテゴリ外用語'))
        ->assertSee('box='.$category->id, false)
        ->assertSee('page=2', false)
        ->assertDontSee('q=%E4%B8%80%E8%87%B4%E3%81%97%E3%81%AA%E3%81%84%E6%A4%9C%E7%B4%A2%E8%AA%9E', false)
        ->assertSee('カテゴリ内用語')
        ->assertSee('＋ 用語を追加')
        ->assertSee('>⋯</button>', false)
        ->assertDontSee('まとめて追加')
        ->assertSee('名前を変更')
        ->assertSee('カテゴリを削除')
        ->assertSee('>編集</a>', false)
        ->assertSee('>外す</button>', false)
        ->assertDontSee('>追加</button>', false)
        ->assertDontSee('>削除</button>', false)
        ->assertDontSee('>保存中', false)
        ->assertDontSee('>削除済み</a>', false)
        ->assertDontSee('＋ カテゴリを作る')
        ->assertDontSee('id="term-search"', false)
        ->assertDontSee('このカテゴリから検索...')
        ->assertDontSee('このカテゴリを検索...')
        ->assertSee('x-show="renameCategoryOpen"', false)
        ->assertSee('id="box-name-edit"', false);

    expect(substr_count($content, '>外す</button>'))->toBe(25);

    $this->actingAs($user)->get(route('learning-terms.index'))
        ->assertSee('id="term-search"', false)
        ->assertSee('カテゴリ外用語')
        ->assertSee('カテゴリ内用語1');
});

test('removing a term from category detail preserves the term other memberships and shared dictionary', function (): void {
    Http::preventStrayRequests();
    $user = User::factory()->create();
    $term = termBoxLearningTerm($user, 'クラウドサービス');
    $currentCategory = manualTermBox($user, 'LinuC');
    $otherCategory = manualTermBox($user, '試験前');
    $sharedExplanation = TermExplanation::factory()->create();
    $currentCategory->learningTerms()->attach($term, ['assigned_by' => 'manual']);
    $otherCategory->learningTerms()->attach($term, ['assigned_by' => 'manual']);

    $this->actingAs($user)->delete(route('learning-term-boxes.terms.destroy', [$currentCategory, $term]))
        ->assertRedirect(route('learning-terms.index', ['box' => $currentCategory->id]));

    $this->assertDatabaseMissing('learning_term_learning_term_box', [
        'learning_term_id' => $term->id,
        'learning_term_box_id' => $currentCategory->id,
    ]);
    $this->assertDatabaseHas('learning_term_learning_term_box', [
        'learning_term_id' => $term->id,
        'learning_term_box_id' => $otherCategory->id,
        'assigned_by' => 'manual',
    ]);
    $this->assertModelExists($term);
    $this->assertModelExists($sharedExplanation);
    expect(AiUsageLog::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('a category detail can search and add several active owned terms even when initially empty', function (): void {
    Http::preventStrayRequests();
    $user = User::factory()->create();
    $category = manualTermBox($user, 'LinuC');
    $firstTerm = termBoxLearningTerm($user, 'systemd', 'LinuC');
    $secondTerm = termBoxLearningTerm($user, 'fstab', 'LinuC');
    $unrelatedTerm = termBoxLearningTerm($user, 'JavaScript', 'Web開発');

    $this->actingAs($user)->get(route('learning-terms.index', ['box' => $category]))
        ->assertOk()
        ->assertSee('＋ 用語を追加')
        ->assertSee('このカテゴリにはまだ用語がありません。')
        ->assertSee('systemd')
        ->assertSee('fstab')
        ->assertSee('class="flex min-h-11 items-center gap-3 px-1 py-2.5 text-sm leading-6 cursor-pointer"', false)
        ->assertSee('class="size-[18px] shrink-0 rounded border-stone-300 text-[#3155D9] focus:ring-[#3155D9]"', false);

    $this->actingAs($user)->get(route('learning-terms.index', ['box' => $category, 'term_picker_q' => 'sys']))
        ->assertSee('systemd')
        ->assertDontSee('value="'.$secondTerm->id.'"', false)
        ->assertDontSee('value="'.$unrelatedTerm->id.'"', false);

    $sharedExplanation = TermExplanation::factory()->create();
    $originalFirstTerm = $firstTerm->only(['term', 'description']);
    $this->actingAs($user)->put(route('learning-term-boxes.terms.update', $category), [
        'term_ids' => [$firstTerm->id, $secondTerm->id],
        'assigned_by' => 'auto',
        'user_id' => User::factory()->create()->id,
    ])->assertRedirect(route('learning-terms.index', ['box' => $category->id]));

    $this->assertDatabaseHas('learning_term_learning_term_box', [
        'learning_term_id' => $firstTerm->id,
        'learning_term_box_id' => $category->id,
        'assigned_by' => 'manual',
    ]);
    $this->assertDatabaseHas('learning_term_learning_term_box', [
        'learning_term_id' => $secondTerm->id,
        'learning_term_box_id' => $category->id,
        'assigned_by' => 'manual',
    ]);
    $this->actingAs($user)->get(route('learning-terms.index', ['box' => $category]))
        ->assertSee('value="'.$firstTerm->id.'" checked disabled', false)
        ->assertSee('value="'.$secondTerm->id.'" checked disabled', false);
    expect($firstTerm->fresh()->only(['term', 'description']))->toBe($originalFirstTerm);
    $this->assertModelExists($sharedExplanation);
    expect(AiUsageLog::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('bulk category addition attaches several active terms to several categories without detaching existing memberships', function (): void {
    Http::preventStrayRequests();
    $user = User::factory()->create();
    $firstTerm = termBoxLearningTerm($user, 'Python');
    $secondTerm = termBoxLearningTerm($user, 'JavaScript');
    $thirdTerm = termBoxLearningTerm($user, 'systemd');
    $firstCategory = manualTermBox($user, 'LinuC');
    $secondCategory = manualTermBox($user, '試験前');
    $existingCategory = manualTermBox($user, '苦手');
    $sharedExplanation = TermExplanation::factory()->create();
    $firstCategory->learningTerms()->attach($firstTerm, ['assigned_by' => 'manual']);
    $existingCategory->learningTerms()->attach($firstTerm, ['assigned_by' => 'manual']);
    $originalFirstTerm = $firstTerm->only(['term', 'description']);

    $this->actingAs($user)->post(route('learning-terms.bulk-categories.update'), [
        'term_ids' => [$firstTerm->id, $secondTerm->id, $thirdTerm->id],
        'category_ids' => [$firstCategory->id, $secondCategory->id],
        'assigned_by' => 'auto',
        'user_id' => User::factory()->create()->id,
    ])->assertRedirect(route('learning-terms.index'))
        ->assertSessionHas('status', '3語を2カテゴリに追加しました。');

    foreach ([$firstTerm, $secondTerm, $thirdTerm] as $term) {
        foreach ([$firstCategory, $secondCategory] as $category) {
            $this->assertDatabaseHas('learning_term_learning_term_box', [
                'learning_term_id' => $term->id,
                'learning_term_box_id' => $category->id,
                'assigned_by' => 'manual',
            ]);
        }
    }
    $this->assertDatabaseHas('learning_term_learning_term_box', [
        'learning_term_id' => $firstTerm->id,
        'learning_term_box_id' => $existingCategory->id,
        'assigned_by' => 'manual',
    ]);
    expect($firstTerm->fresh()->only(['term', 'description']))->toBe($originalFirstTerm)
        ->and(DB::table('learning_term_learning_term_box')->count())->toBe(7);
    $this->assertModelExists($sharedExplanation);
    expect(AiUsageLog::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('bulk category addition accepts an active term whose learning set is soft deleted', function (): void {
    $user = User::factory()->create();
    $term = termBoxLearningTerm($user, '教材削除後も管理できる用語');
    $category = manualTermBox($user, '復習');
    $learningSetId = $term->learning_set_id;

    $term->learningSetIncludingDeleted()->delete();

    $this->actingAs($user)->from(route('learning-terms.index'))->post(route('learning-terms.bulk-categories.update'), [
        'term_ids' => [$term->id],
        'category_ids' => [$category->id],
    ])->assertRedirect(route('learning-terms.index'));

    $this->assertDatabaseHas('learning_term_learning_term_box', [
        'learning_term_id' => $term->id,
        'learning_term_box_id' => $category->id,
        'assigned_by' => 'manual',
    ]);
    $this->assertModelExists($term);
    $this->assertSoftDeleted('learning_sets', ['id' => $learningSetId]);
});

test('the bulk category form uses a post action with csrf and expected fields', function (): void {
    $user = User::factory()->create();
    termBoxLearningTerm($user, 'フォーム確認用語');
    manualTermBox($user, 'フォーム確認カテゴリ');

    $this->actingAs($user)->get(route('learning-terms.index'))
        ->assertOk()
        ->assertSee('method="POST"', false)
        ->assertSee('action="'.route('learning-terms.bulk-categories.update').'"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('name="term_ids[]"', false)
        ->assertSee('name="category_ids[]"', false);
});

test('bulk category addition rejects empty, foreign, deleted, and automatic records', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $term = termBoxLearningTerm($user, '所有用語');
    $foreignTerm = termBoxLearningTerm($otherUser, '他人用語');
    $deletedTerm = termBoxLearningTerm($user, '削除済み用語');
    $deletedTerm->delete();
    $category = manualTermBox($user, '自分カテゴリ');
    $foreignCategory = manualTermBox($otherUser, '他人カテゴリ');
    $automaticCategory = legacyAutomaticTermBox($user, '旧自動カテゴリ');

    $this->actingAs($user)->post(route('learning-terms.bulk-categories.update'), [
        'term_ids' => [$foreignTerm->id],
        'category_ids' => [$category->id],
    ])->assertNotFound();
    $this->actingAs($user)->post(route('learning-terms.bulk-categories.update'), [
        'term_ids' => [$term->id],
        'category_ids' => [$foreignCategory->id],
    ])->assertNotFound();
    $this->actingAs($user)->post(route('learning-terms.bulk-categories.update'), [
        'term_ids' => [$deletedTerm->id],
        'category_ids' => [$category->id],
    ])->assertNotFound();
    $this->actingAs($user)->post(route('learning-terms.bulk-categories.update'), [
        'term_ids' => [$term->id],
        'category_ids' => [$automaticCategory->id],
    ])->assertNotFound();
    $this->actingAs($user)->post(route('learning-terms.bulk-categories.update'), [
        'term_ids' => [],
        'category_ids' => [],
    ])->assertSessionHasErrors(['term_ids', 'category_ids']);

    $this->assertDatabaseCount('learning_term_learning_term_box', 0);
});

test('the active list syncs checked manual categories while the deleted list hides category actions', function (): void {
    $user = User::factory()->create();
    $term = termBoxLearningTerm($user, 'カテゴリ操作用語');
    $selectedCategory = manualTermBox($user, '選択済み');
    $availableCategory = manualTermBox($user, '追加候補');
    $foreignCategory = manualTermBox(User::factory()->create(), '他人のカテゴリ');
    $selectedCategory->learningTerms()->attach($term, ['assigned_by' => 'manual']);

    $this->actingAs($user)->get(route('learning-terms.index'))
        ->assertSee('>追加</button>', false)
        ->assertSee('>編集</a>', false)
        ->assertSee('>削除</button>', false)
        ->assertSee('value="'.$selectedCategory->id.'" checked', false)
        ->assertSee('value="'.$availableCategory->id.'"', false)
        ->assertSee('class="flex min-h-11 cursor-pointer items-center gap-3 px-1 py-2.5 text-sm leading-6"', false)
        ->assertSee('class="size-[18px] shrink-0 rounded border-stone-300 text-[#3155D9] focus:ring-[#3155D9]"', false)
        ->assertDontSee($foreignCategory->name);

    $this->actingAs($user)
        ->from(route('learning-terms.index'))
        ->put(route('learning-terms.boxes.update', $term), [
            'manual_box_ids' => [$availableCategory->id],
            'return_to' => 'index',
        ])
        ->assertRedirect(route('learning-terms.index'));

    $this->assertDatabaseMissing('learning_term_learning_term_box', [
        'learning_term_id' => $term->id,
        'learning_term_box_id' => $selectedCategory->id,
    ]);
    $this->assertDatabaseHas('learning_term_learning_term_box', [
        'learning_term_id' => $term->id,
        'learning_term_box_id' => $availableCategory->id,
        'assigned_by' => 'manual',
    ]);
    $this->assertModelExists($term);

    $term->delete();
    $this->actingAs($user)->get(route('learning-terms.index', ['status' => 'deleted']))
        ->assertSee('カテゴリ操作用語')
        ->assertSee('復元')
        ->assertDontSee('カテゴリ追加');
});

test('soft deleted terms leave category counts and restore with manual membership but no automatic category', function (): void {
    $user = User::factory()->create();
    $term = termBoxLearningTerm($user, '復元用語', '宅建');
    $category = manualTermBox($user, '試験前');
    $category->learningTerms()->attach($term, ['assigned_by' => 'manual']);
    $term->delete();

    $this->actingAs($user)->get(route('learning-terms.index'))
        ->assertSee('試験前')
        ->assertSee('0語')
        ->assertDontSee('復元用語');
    $this->actingAs($user)->get(route('learning-terms.index', ['status' => 'deleted']))
        ->assertSee('復元用語')
        ->assertSee('復元')
        ->assertDontSee('まとめて追加');

    $term->learningSetIncludingDeleted->update(['subject' => '法律']);
    $this->actingAs($user)->patch(route('learning-terms.restore', $term->id));

    expect($term->fresh()->trashed())->toBeFalse();
    $this->assertDatabaseHas('learning_term_learning_term_box', [
        'learning_term_id' => $term->id,
        'learning_term_box_id' => $category->id,
        'assigned_by' => 'manual',
    ]);
    $this->assertDatabaseMissing('learning_term_boxes', ['user_id' => $user->id, 'kind' => LearningTermBox::KIND_AUTO]);
});

test('the retirement command removes only automatic memberships and soft deletes automatic categories', function (): void {
    Http::preventStrayRequests();
    $user = User::factory()->create();
    $term = termBoxLearningTerm($user, '既存用語');
    $manualCategory = manualTermBox($user, '試験前');
    $automaticCategory = legacyAutomaticTermBox($user, '宅建');
    $manualCategory->learningTerms()->attach($term, ['assigned_by' => 'manual']);
    $automaticCategory->learningTerms()->attach($term, ['assigned_by' => 'auto']);
    $secondTerm = termBoxLearningTerm($user, '手動所属だけの既存用語');
    $automaticCategory->learningTerms()->attach($secondTerm, ['assigned_by' => 'manual']);

    $this->artisan('learning-terms:retire-automatic-categories')->assertSuccessful();
    $this->artisan('learning-terms:retire-automatic-categories')->assertSuccessful();

    $this->assertSoftDeleted($automaticCategory);
    $this->assertNotSoftDeleted($manualCategory);
    $this->assertDatabaseMissing('learning_term_learning_term_box', ['assigned_by' => 'auto']);
    $this->assertDatabaseHas('learning_term_learning_term_box', [
        'learning_term_id' => $term->id,
        'learning_term_box_id' => $manualCategory->id,
        'assigned_by' => 'manual',
    ]);
    $this->assertDatabaseHas('learning_term_learning_term_box', [
        'learning_term_id' => $secondTerm->id,
        'learning_term_box_id' => $automaticCategory->id,
        'assigned_by' => 'manual',
    ]);
    $this->assertModelExists($term);
    $this->assertModelExists($secondTerm);
    expect(AiUsageLog::query()->count())->toBe(0);
    Http::assertNothingSent();
});
