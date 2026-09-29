<?php

use App\Services\DictionaryNormalizer;

test('normalizes dictionary labels with NFKC whitespace and case folding', function (): void {
    $normalizer = new DictionaryNormalizer;

    expect($normalizer->display("  ＬｉｎｕＣ\n 基礎  "))->toBe('LinuC 基礎')
        ->and($normalizer->key("  ＬｉｎｕＣ\n 基礎  "))->toBe('linuc 基礎');
});
