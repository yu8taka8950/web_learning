<?php

use App\Services\LearningTopicClassifier;

test('it classifies dashboard categories', function (string $subject, string $expected): void {
    expect((new LearningTopicClassifier)->category($subject))->toBe($expected);
})->with([
    'Linux' => ['Linuxのシェル', 'LINUX'],
    'cloud' => ['AWS EC2', 'CLOUD'],
    'programming' => ['LaravelとPHP', 'PROGRAMMING'],
    'network' => ['Apache server', 'SERVER / NETWORK'],
    'web' => ['HTMLとCSS', 'WEB'],
    'database' => ['MariaDB query', 'DATABASE'],
    'unknown' => ['文章の読み方', 'LEARNING'],
]);

test('it returns a high confidence auto collection rule', function (string $subject, ?string $expected): void {
    expect((new LearningTopicClassifier)->autoRule($subject))->toBe($expected);
})->with([
    'Linux' => ['Linux基礎', 'linuc'],
    'systemd' => ['systemdのサービス管理', 'linuc'],
    'shell in Japanese' => ['シェルコマンド', 'linuc'],
    'Laravel' => ['Laravel入門', 'web'],
    'PHP' => ['PHPの変数', 'web'],
    'AWS' => ['AWS設計', 'cloud'],
    'S3 token' => ['S3ストレージ', 'cloud'],
    'MariaDB' => ['MariaDB運用', 'database'],
    'unknown' => ['文章の読み方', null],
]);

test('it prioritizes the Linux rule when several themes match', function (): void {
    expect((new LearningTopicClassifier)->autoRule('Linux上のApache Webサーバー設定'))->toBe('linuc');
});
