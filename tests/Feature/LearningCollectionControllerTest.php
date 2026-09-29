<?php

use App\Models\LearningCollection;
use App\Models\LearningSet;
use App\Models\User;
use App\Services\LearningCollectionAutoAssignService;
use Illuminate\Support\Facades\Http;

function collectionLearningSet(User $user, string $title = 'Linux基礎', ?string $topic = 'systemd', ?string $subject = null): LearningSet
{
    $learningSet = $user->learningSets()->create(['title' => $title, 'topic' => $topic, 'subject' => $subject, 'source_type' => 'manual']);
    $learningSet->questions()->create([
        'question' => '確認問題', 'option_a' => '正解', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D', 'correct_option' => 'A',
    ]);

    return $learningSet;
}

test('collection routes require authentication', function (): void {
    $this->get(route('collections.index'))->assertRedirect(route('login'));
    $this->post(route('collections.store'))->assertRedirect(route('login'));
    $this->post(route('collections.auto-organize'))->assertRedirect(route('login'));
    $this->get(route('collections.deleted'))->assertRedirect(route('login'));
});

test('a user can create view update and soft delete a collection', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('collections.store'), [
        'name' => 'サーバー学習',
        'description' => '運用知識を整理',
        'user_id' => User::factory()->create()->id,
        'auto_rule' => 'linuc',
    ])->assertRedirect();
    $collection = LearningCollection::query()->firstOrFail();

    expect($collection->user_id)->toBe($user->id)
        ->and($collection->auto_rule)->toBeNull();
    $learningSet = collectionLearningSet($user, '保持される教材', '一般');
    $collection->learningSets()->attach($learningSet, ['assigned_by' => 'manual']);
    $this->actingAs($user)->get(route('collections.index'))
        ->assertSee('サーバー学習')
        ->assertSee('href="'.route('collections.index').'"', false)
        ->assertSee('bg-blue-50 text-[#3155D9]', false);
    $this->actingAs($user)->get(route('collections.show', $collection))->assertSee('運用知識を整理');
    $this->actingAs($user)->patch(route('collections.update', $collection), [
        'name' => 'サーバー試験対策',
        'description' => '更新後',
        'auto_rule' => 'cloud',
    ])->assertRedirect(route('collections.show', $collection));
    $this->actingAs($user)->delete(route('collections.destroy', $collection))->assertRedirect(route('collections.index'));

    $this->assertSoftDeleted($collection);
    $this->assertModelExists($learningSet);
    expect($collection->fresh()->auto_rule)->toBeNull();
    $this->actingAs($user)->get(route('collections.deleted'))
        ->assertSee('サーバー試験対策');

    $this->actingAs($user)->post(route('collections.restore', $collection->id))
        ->assertRedirect(route('collections.show', $collection->id));
    expect($collection->fresh()->trashed())->toBeFalse()
        ->and($collection->fresh()->learningSets()->whereKey($learningSet)->exists())->toBeTrue();
});

test('collection ownership is enforced for view update and deletion', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $collection = $owner->learningCollections()->create(['name' => '非公開']);

    $this->actingAs($otherUser)->get(route('collections.show', $collection))->assertNotFound();
    $this->actingAs($otherUser)->patch(route('collections.update', $collection), ['name' => '改ざん'])->assertNotFound();
    $this->actingAs($otherUser)->delete(route('collections.destroy', $collection))->assertNotFound();
});

test('learning sets can belong to several collections without duplicate memberships', function (): void {
    $user = User::factory()->create();
    $learningSet = collectionLearningSet($user);
    $first = $user->learningCollections()->create(['name' => 'LinuC']);
    $second = $user->learningCollections()->create(['name' => 'サーバー']);

    $this->actingAs($user)->put(route('learning-sets.collections.update', $learningSet), [
        'collection_ids' => [$first->id, $first->id, $second->id],
    ])->assertSessionHasErrors('collection_ids.1');
    $this->actingAs($user)->put(route('learning-sets.collections.update', $learningSet), [
        'collection_ids' => [$first->id, $second->id],
    ])->assertRedirect(route('dashboard'));

    expect($learningSet->collections()->count())->toBe(2);
    $this->assertDatabaseHas('learning_collection_learning_set', ['learning_collection_id' => $first->id, 'learning_set_id' => $learningSet->id, 'assigned_by' => 'manual']);
});

test('detaching a learning set removes only its collection membership', function (): void {
    $user = User::factory()->create();
    $learningSet = collectionLearningSet($user);
    $collection = $user->learningCollections()->create(['name' => 'LinuC']);
    $collection->learningSets()->attach($learningSet, ['assigned_by' => 'manual']);

    $this->actingAs($user)->delete(route('collections.learning-sets.destroy', [$collection, $learningSet]))
        ->assertRedirect(route('collections.show', $collection));

    expect($collection->learningSets()->exists())->toBeFalse();
    $this->assertModelExists($learningSet);
    $this->assertModelExists($learningSet->questions()->firstOrFail());
});

test('cross tenant collection memberships are rejected', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $ownedSet = collectionLearningSet($owner);
    $foreignSet = collectionLearningSet($otherUser);
    $ownedCollection = $owner->learningCollections()->create(['name' => '所有']);
    $foreignCollection = $otherUser->learningCollections()->create(['name' => '他人']);

    $this->actingAs($owner)->put(route('learning-sets.collections.update', $ownedSet), ['collection_ids' => [$foreignCollection->id]])->assertNotFound();
    $this->actingAs($owner)->put(route('learning-sets.collections.update', $foreignSet), ['collection_ids' => [$ownedCollection->id]])->assertNotFound();
    $this->actingAs($owner)->delete(route('collections.learning-sets.destroy', [$foreignCollection, $ownedSet]))->assertNotFound();
});

test('auto assignment creates and reuses a rule collection after rename', function (): void {
    Http::preventStrayRequests();
    $user = User::factory()->create();
    $firstSet = collectionLearningSet($user, 'Linuxのシェル', 'systemd');
    $service = app(LearningCollectionAutoAssignService::class);

    expect($service->assign($firstSet))->toBeTrue();
    $collection = $user->learningCollections()->where('auto_rule', 'linuc')->firstOrFail();
    $collection->update(['name' => 'LinuCレベル1対策']);
    $secondSet = collectionLearningSet($user, 'Linux権限', 'chmod');

    expect($service->assign($secondSet))->toBeTrue()
        ->and($user->learningCollections()->where('auto_rule', 'linuc')->count())->toBe(1)
        ->and($collection->fresh()->learningSets()->count())->toBe(2);
    Http::assertNothingSent();
});

test('auto assignment prioritizes dynamic subjects over legacy title and topic keywords', function (string $subject, string $expectedRule, string $expectedName): void {
    $user = User::factory()->create();
    $learningSet = collectionLearningSet($user, 'Webサービスとシステムの契約', 'LaravelとWeb', $subject);
    $secondLearningSet = collectionLearningSet($user, '別の教材', '別の単元', $subject);
    $service = app(LearningCollectionAutoAssignService::class);

    expect($service->assign($learningSet))->toBeTrue()
        ->and($service->assign($secondLearningSet))->toBeTrue()
        ->and($user->learningCollections()->where('auto_rule', $expectedRule)->count())->toBe(1);

    $this->assertDatabaseHas('learning_collections', [
        'user_id' => $user->id,
        'name' => $expectedName,
        'auto_rule' => $expectedRule,
    ]);
    $this->assertDatabaseMissing('learning_collections', [
        'user_id' => $user->id,
        'auto_rule' => 'web',
        'name' => 'Web開発',
    ]);
})->with([
    'real estate' => ['宅建', 'subject:宅建', '宅建'],
    'mathematics' => ['数学', 'subject:数学', '数学'],
    'bookkeeping' => ['簿記', 'subject:簿記', '簿記'],
]);

test('known IT subjects reuse legacy automatic collections without duplication', function (string $subject, string $expectedRule, string $expectedName): void {
    $user = User::factory()->create();
    $existingCollection = $user->learningCollections()->create([
        'name' => $expectedName,
        'description' => '既存説明',
        'auto_rule' => $expectedRule,
    ]);
    $learningSet = collectionLearningSet($user, '別分野を含むタイトル', '一般', $subject);

    expect(app(LearningCollectionAutoAssignService::class)->assign($learningSet))->toBeTrue()
        ->and($user->learningCollections()->where('auto_rule', $expectedRule)->count())->toBe(1)
        ->and($existingCollection->learningSets()->whereKey($learningSet)->exists())->toBeTrue();
})->with([
    'Web development' => ['Web開発', 'web', 'Web開発'],
    'LinuC' => ['LinuC', 'linuc', 'LinuC'],
]);

test('auto organize replaces only an incorrect automatic relation using the current subject', function (): void {
    $user = User::factory()->create();
    $learningSet = collectionLearningSet($user, '不動産取引における媒介や契約の種類', '媒介契約', '宅建');
    $wrongAutomaticCollection = $user->learningCollections()->create(['name' => 'Web開発', 'auto_rule' => 'web']);
    $manualCollection = $user->learningCollections()->create(['name' => '手動のWeb開発']);
    $wrongAutomaticCollection->learningSets()->attach($learningSet, ['assigned_by' => 'auto']);
    $manualCollection->learningSets()->attach($learningSet, ['assigned_by' => 'manual']);

    $this->actingAs($user)->post(route('collections.auto-organize'))
        ->assertSessionHas('status', '1件の学習セットをコレクションへ整理しました。');

    $correctCollection = $user->learningCollections()->where('auto_rule', 'subject:宅建')->firstOrFail();
    $this->assertDatabaseMissing('learning_collection_learning_set', [
        'learning_collection_id' => $wrongAutomaticCollection->id,
        'learning_set_id' => $learningSet->id,
        'assigned_by' => 'auto',
    ]);
    $this->assertDatabaseHas('learning_collection_learning_set', [
        'learning_collection_id' => $correctCollection->id,
        'learning_set_id' => $learningSet->id,
        'assigned_by' => 'auto',
    ]);
    $this->assertDatabaseHas('learning_collection_learning_set', [
        'learning_collection_id' => $manualCollection->id,
        'learning_set_id' => $learningSet->id,
        'assigned_by' => 'manual',
    ]);
});

test('subjectless learning sets use only the legacy classifier and unclassified sets create nothing', function (): void {
    $user = User::factory()->create();
    $legacySet = collectionLearningSet($user, 'Laravel入門', 'PHP', null);
    $unclassifiedSet = collectionLearningSet($user, '一般教養', '未知のテーマ', null);
    $service = app(LearningCollectionAutoAssignService::class);

    expect($service->assign($legacySet))->toBeTrue()
        ->and($service->assign($unclassifiedSet))->toBeFalse();

    $this->assertDatabaseHas('learning_collections', ['user_id' => $user->id, 'auto_rule' => 'web']);
    expect($user->learningCollections()->count())->toBe(1);
});

test('auto assignment does not recreate a deleted auto collection', function (): void {
    $user = User::factory()->create();
    $collection = $user->learningCollections()->create(['name' => 'LinuC', 'auto_rule' => 'linuc']);
    $collection->delete();
    $learningSet = collectionLearningSet($user);

    expect(app(LearningCollectionAutoAssignService::class)->assign($learningSet))->toBeFalse()
        ->and(LearningCollection::withTrashed()->whereBelongsTo($user)->where('auto_rule', 'linuc')->count())->toBe(1);
});

test('auto assignment preserves a manual pivot and avoids duplicates', function (): void {
    $user = User::factory()->create();
    $collection = $user->learningCollections()->create(['name' => 'LinuC', 'auto_rule' => 'linuc']);
    $learningSet = collectionLearningSet($user);
    $collection->learningSets()->attach($learningSet, ['assigned_by' => 'manual']);

    expect(app(LearningCollectionAutoAssignService::class)->assign($learningSet))->toBeFalse();
    $this->assertDatabaseHas('learning_collection_learning_set', [
        'learning_collection_id' => $collection->id,
        'learning_set_id' => $learningSet->id,
        'assigned_by' => 'manual',
    ]);
    expect($collection->learningSets()->count())->toBe(1);
});

test('existing learning sets are organized only by an explicit post action', function (): void {
    $user = User::factory()->create();
    collectionLearningSet($user, 'AWSとS3', 'クラウド');
    collectionLearningSet($user, '未知の教材', '一般教養');

    $this->actingAs($user)->get(route('collections.index'));
    expect($user->learningCollections()->count())->toBe(0);
    $this->actingAs($user)->post(route('collections.auto-organize'))
        ->assertSessionHas('status', '1件の学習セットをコレクションへ整理しました。');

    $this->assertDatabaseHas('learning_collections', ['user_id' => $user->id, 'name' => 'クラウド / AWS', 'auto_rule' => 'cloud']);
});

test('adding the first question performs automatic organization without an api request', function (): void {
    Http::preventStrayRequests();
    $user = User::factory()->create();
    $learningSet = $user->learningSets()->create(['title' => 'Laravel基礎', 'topic' => 'PHP', 'source_type' => 'manual']);

    $this->actingAs($user)->post(route('learning-sets.questions.store', $learningSet), [
        'question' => 'PHPとは？', 'option_a' => '言語', 'option_b' => 'OS', 'option_c' => 'DB', 'option_d' => '画像', 'correct_option' => 'A',
    ])->assertRedirect(route('learning-sets.quiz', $learningSet));

    $this->assertDatabaseHas('learning_collections', ['user_id' => $user->id, 'auto_rule' => 'web']);
    $this->assertDatabaseHas('learning_collection_learning_set', ['learning_set_id' => $learningSet->id, 'assigned_by' => 'auto']);
    Http::assertNothingSent();
});
