<?php

use App\Services\TermExplanationFormatter;

test('removes only a matching term prefix from a shared explanation', function (string $term, string $explanation, string $expected): void {
    expect((new TermExplanationFormatter)->format($term, $explanation))->toBe($expected);
})->with([
    'Japaneseとは with comma' => ['Python', 'Pythonとは、読みやすい文法が特徴です。', '読みやすい文法が特徴です。'],
    'Japaneseは with comma' => ['Python', 'Pythonは、読みやすい文法が特徴です。', '読みやすい文法が特徴です。'],
    'without separator' => ['systemd', 'systemdとはLinuxのサービス管理を行う仕組みです。', 'Linuxのサービス管理を行う仕組みです。'],
    'Japanese term' => ['媒介契約', '媒介契約とは、不動産取引の仲介を依頼する契約です。', '不動産取引の仲介を依頼する契約です。'],
    'case insensitive' => ['Python', 'python は: 読みやすい言語です。', '読みやすい言語です。'],
]);

test('does not alter terms outside the beginning or explanations beginning with another word', function (string $explanation): void {
    expect((new TermExplanationFormatter)->format('Python', $explanation))->toBe($explanation);
})->with([
    'term later in text' => '読みやすさが特徴で、Pythonは幅広く利用されます。',
    'different leading word' => 'プログラミングではPythonが利用されます。',
]);

test('never turns an explanation consisting only of its prefix into an empty value', function (): void {
    expect((new TermExplanationFormatter)->format('Python', 'Pythonとは'))->toBe('Pythonとは');
});
