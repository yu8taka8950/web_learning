<?php

use App\Services\DictionaryNormalizer;
use App\Services\LearningTermSaveService;

function learningTermSaveService(): LearningTermSaveService
{
    $normalizer = new DictionaryNormalizer;

    return new LearningTermSaveService($normalizer);
}

test('normalizes selected terms with NFKC whitespace and trimming', function (): void {
    $service = learningTermSaveService();

    expect($service->normalizeTerm("  ｓｙｓｔｅｍｄ\n\t管理  "))->toBe('systemd 管理');
});

test('extracts the sentence containing the selected term for Japanese English and newline text', function (string $text, string $term, string $expected): void {
    $service = learningTermSaveService();

    expect($service->descriptionFor($text, $term))->toBe($expected);
})->with([
    'Japanese punctuation' => ['最初の文です。systemdはサービスを管理します。次の文です。', 'systemd', 'systemdはサービスを管理します。'],
    'English period' => ['First sentence. systemd manages services. Last sentence.', 'systemd', 'systemd manages services.'],
    'newline' => ["最初の行\nsystemdを使います\n最後の行", 'systemd', 'systemdを使います'],
]);

test('limits saved descriptions to five hundred characters', function (): void {
    $service = learningTermSaveService();
    $text = 'systemd'.str_repeat('あ', 600);

    expect(mb_strlen($service->descriptionFor($text, 'systemd')))->toBe(500);
});
