<?php

use App\Services\LearningSetIconService;

test('maps learning set subjects to a restrained Lucide icon vocabulary', function (array $values, string $icon) {
    expect(app(LearningSetIconService::class)->for(...$values))->toBe($icon);
})->with([
    [['Linuxの基本', 'コマンド'], 'terminal'],
    [['Web開発', 'Laravel'], 'code-2'],
    [['SQL入門'], 'database'],
    [['未知のテーマ'], 'book-open'],
]);
