<?php

use App\Models\LearningTerm;
use App\Services\DictionaryNormalizer;
use App\Services\LearningSubjectResolver;
use App\Services\LearningTermMatcher;
use App\Services\LearningTopicClassifier;
use App\Services\TermExplanationFormatter;

function learningTermMatcher(): LearningTermMatcher
{
    $normalizer = new DictionaryNormalizer;

    return new LearningTermMatcher(
        $normalizer,
        new LearningSubjectResolver(new LearningTopicClassifier, $normalizer),
        new TermExplanationFormatter,
    );
}

function matcherTerm(string $term, ?string $description = '説明'): LearningTerm
{
    return new LearningTerm(['term' => $term, 'description' => $description]);
}

test('matches the longest saved term before its shorter prefix', function (): void {
    $segments = learningTermMatcher()->segments('systemdを確認します。', collect([
        matcherTerm('system', '短い説明'),
        matcherTerm('systemd', '長い説明'),
    ]));

    expect($segments)->toBe([
        ['type' => 'term', 'text' => 'systemd', 'term' => 'systemd', 'description' => '長い説明'],
        ['type' => 'text', 'text' => 'を確認します。'],
    ]);
});

test('matches ASCII terms case insensitively without matching word fragments or repeating popovers', function (): void {
    $segments = learningTermMatcher()->segments('Linuxとlinux、concatenateを確認します。', collect([
        matcherTerm('linux', 'OS'),
        matcherTerm('cat', '表示コマンド'),
    ]));

    expect(collect($segments)->where('type', 'term')->pluck('text')->all())->toBe(['Linux']);
});

test('matches Japanese terms once while excluding one character terms', function (): void {
    $segments = learningTermMatcher()->segments('制御構造は制御構造です。', collect([
        matcherTerm('制御構造', '条件分岐など'),
        matcherTerm('構', '一文字'),
    ]));

    expect(collect($segments)->where('type', 'term')->pluck('text')->all())->toBe(['制御構造']);
});

test('excludes the current question answer without affecting other terms', function (): void {
    $segments = learningTermMatcher()->segments('自己発見取引と媒介契約を確認します。', collect([
        matcherTerm('自己発見取引', '正解の説明'),
        matcherTerm('媒介契約', '契約の説明'),
    ]), ['自己発見取引']);

    expect(collect($segments)->where('type', 'term')->pluck('text')->all())->toBe(['媒介契約']);
});
