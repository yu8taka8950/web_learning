<?php

use App\Models\Question;
use App\Models\User;

beforeEach(function (): void {
    config()->set('services.gemini.key', null);
});

function learningTermQuestion(User $user, array $attributes = [], array $learningSetAttributes = []): Question
{
    $learningSet = $user->learningSets()->create([
        'title' => 'Linuxサービス管理',
        'source_type' => 'web',
        'source_url' => 'https://example.com/linux',
        ...$learningSetAttributes,
    ]);

    return $learningSet->questions()->create([
        'question' => 'Linuxでsystemdを利用してサービスを管理します。',
        'input_question' => 'systemdの役割を入力してください。',
        'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C', 'option_d' => 'D',
        'correct_option' => 'A',
        'explanation' => 'systemdはLinuxのサービスを管理する仕組みです。次の文です。',
        ...$attributes,
    ]);
}

function saveSelectedTerm(User $user, Question $question, array $attributes = [])
{
    return test()->actingAs($user)->postJson(route('learning-terms.store'), [
        'question_id' => $question->id,
        'selected_text' => 'systemd',
        'source_field' => 'explanation',
        ...$attributes,
    ]);
}

test('guests cannot save selected terms', function (): void {
    $this->postJson(route('learning-terms.store'), [
        'question_id' => 1,
        'selected_text' => 'systemd',
        'source_field' => 'question',
    ])->assertUnauthorized();
});

test('saves a selected term to the owned questions learning set from trusted explanation text', function (): void {
    $user = User::factory()->create();
    $question = learningTermQuestion($user, [], ['subject' => '宅建']);
    $unrelatedLearningSet = $user->learningSets()->create(['title' => '任意の教材', 'source_type' => 'manual']);

    saveSelectedTerm($user, $question, [
        'description' => '任意の説明',
        'learning_set_id' => $unrelatedLearningSet->id,
        'source_url' => 'https://attacker.example',
    ])->assertCreated()->assertJsonPath('created', true)->assertJsonPath('term', 'systemd');

    $this->assertDatabaseHas('learning_terms', [
        'learning_set_id' => $question->learning_set_id,
        'term' => 'systemd',
        'description' => 'systemdはLinuxのサービスを管理する仕組みです。',
        'source_type' => 'web',
        'source_url' => 'https://example.com/linux',
    ]);
    $this->assertDatabaseMissing('learning_terms', ['learning_set_id' => $unrelatedLearningSet->id, 'term' => 'systemd']);
    $this->assertDatabaseCount('learning_term_boxes', 0);
    $this->assertDatabaseCount('learning_term_learning_term_box', 0);
});

test('returns 404 when saving a term from another users question', function (): void {
    $user = User::factory()->create();
    $otherQuestion = learningTermQuestion(User::factory()->create());

    saveSelectedTerm($user, $otherQuestion)->assertNotFound();
});

test('uses the trusted selected source field to create descriptions', function (string $sourceField, array $questionAttributes, string $selectedText, string $expectedDescription): void {
    $user = User::factory()->create();
    $question = learningTermQuestion($user, $questionAttributes);

    saveSelectedTerm($user, $question, ['source_field' => $sourceField, 'selected_text' => $selectedText])
        ->assertCreated();

    $this->assertDatabaseHas('learning_terms', [
        'learning_set_id' => $question->learning_set_id,
        'term' => $selectedText,
        'description' => $expectedDescription,
    ]);
})->with([
    'question' => ['question', ['question' => 'Linuxでsystemdを利用します。次の文です。'], 'systemd', 'Linuxでsystemdを利用します。'],
    'input question' => ['input_question', ['input_question' => 'systemdの役割を説明してください。'], 'systemd', 'systemdの役割を説明してください。'],
    'input question fallback' => ['input_question', ['input_question' => null, 'question' => 'systemdの役割を説明してください。'], 'systemd', 'systemdの役割を説明してください。'],
    'explanation' => ['explanation', ['explanation' => '前の文です。systemdはサービス管理です。後の文です。'], 'systemd', 'systemdはサービス管理です。'],
]);

test('rejects untrusted source fields or terms not present in their trusted text', function (array $attributes): void {
    $user = User::factory()->create();
    $question = learningTermQuestion($user);

    saveSelectedTerm($user, $question, $attributes)->assertUnprocessable();

    $this->assertDatabaseCount('learning_terms', 0);
})->with([
    'invalid source field' => [['source_field' => 'arbitrary']],
    'missing selected term' => [['selected_text' => 'Apache', 'source_field' => 'question']],
]);

test('normalizes saved terms and rejects empty or overlength selected text', function (): void {
    $user = User::factory()->create();
    $question = learningTermQuestion($user, ['question' => 'ｓｙｓｔｅｍｄ を使います。']);

    saveSelectedTerm($user, $question, ['source_field' => 'question', 'selected_text' => "  ｓｙｓｔｅｍｄ\n"])
        ->assertCreated()
        ->assertJsonPath('term', 'systemd');
    $this->assertDatabaseHas('learning_terms', ['learning_set_id' => $question->learning_set_id, 'term' => 'systemd']);

    $anotherQuestion = learningTermQuestion($user);
    saveSelectedTerm($user, $anotherQuestion, ['selected_text' => '　　'])->assertUnprocessable()->assertJsonValidationErrors('selected_text');
    saveSelectedTerm($user, $anotherQuestion, ['selected_text' => str_repeat('a', 101)])->assertUnprocessable()->assertJsonValidationErrors('selected_text');
});

test('does not create duplicate active terms for the user case insensitively', function (): void {
    $user = User::factory()->create();
    $firstQuestion = learningTermQuestion($user);
    $secondQuestion = learningTermQuestion($user);
    $firstQuestion->learningSet->learningTerms()->create(['term' => 'SYSTEMD', 'description' => '既存の説明']);

    saveSelectedTerm($user, $secondQuestion)->assertOk()->assertJsonPath('created', false);

    expect($user->learningSets()->withCount('learningTerms')->get()->sum('learning_terms_count'))->toBe(1);
});

test('does not treat another users matching term as a duplicate', function (): void {
    $user = User::factory()->create();
    $question = learningTermQuestion($user);
    $otherLearningSet = User::factory()->create()->learningSets()->create(['title' => '他人の教材', 'source_type' => 'manual']);
    $otherLearningSet->learningTerms()->create(['term' => 'systemd', 'description' => '他人の説明']);

    saveSelectedTerm($user, $question)->assertCreated();

    $this->assertDatabaseHas('learning_terms', ['learning_set_id' => $question->learning_set_id, 'term' => 'systemd']);
});

test('allows saving when a matching term exists only in a soft deleted learning set', function (): void {
    $user = User::factory()->create();
    $deletedLearningSet = $user->learningSets()->create(['title' => '削除済み教材', 'source_type' => 'manual']);
    $deletedLearningSet->learningTerms()->create(['term' => 'systemd', 'description' => '削除済み説明']);
    $deletedLearningSet->delete();
    $question = learningTermQuestion($user);

    saveSelectedTerm($user, $question)->assertCreated();

    $this->assertDatabaseHas('learning_terms', ['learning_set_id' => $question->learning_set_id, 'term' => 'systemd']);
});

test('saving a selected term restores the matching deleted term in the current learning set', function (): void {
    $user = User::factory()->create();
    $question = learningTermQuestion($user);
    $deletedTerm = $question->learningSet->learningTerms()->create([
        'term' => 'systemd',
        'description' => '以前の説明',
    ]);
    $deletedTerm->delete();

    saveSelectedTerm($user, $question)->assertCreated()->assertJsonPath('created', true);

    expect($deletedTerm->fresh()->trashed())->toBeFalse()
        ->and($deletedTerm->fresh()->description)->toBe('systemdはLinuxのサービスを管理する仕組みです。');
    expect($question->learningSet->learningTerms()->withTrashed()->where('term', 'systemd')->count())->toBe(1);
});

test('escapes saved source descriptions when they are later rendered as popovers', function (): void {
    $user = User::factory()->create();
    $question = learningTermQuestion($user, ['explanation' => '<script>alert(1)</script> systemdを確認します。']);

    saveSelectedTerm($user, $question)->assertCreated();

    $this->actingAs($user)->get(route('learning-sets.quiz', $question->learningSet))
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('\\u003Cscript\\u003Ealert(1)', false);
});

test('learning screens render the accessible save action with trusted selection metadata', function (): void {
    $user = User::factory()->create();
    $question = learningTermQuestion($user);

    $this->actingAs($user)->get(route('learning-sets.quiz', $question->learningSet))
        ->assertSee('用語を保存')
        ->assertSee('data-selection-field="question"', false)
        ->assertSee('data-selection-field="explanation"', false)
        ->assertSee('data-question-id="'.$question->id.'"', false)
        ->assertSee('learning-terms')
        ->assertSee('ChatGPTに聞く ↗')
        ->assertDontSee('aria-label="質問文をコピー"', false);
});
