<?php

namespace App\Services;

use Illuminate\Support\Str;

class LearningTopicClassifier
{
    /** @var array<string, array<int, string>> */
    private const AUTO_RULE_KEYWORDS = [
        'linuc' => [
            'linux', 'linuc', 'shell', 'シェル', 'bash', 'systemd', 'systemctl', 'daemon', 'chmod', 'chown',
            'umask', 'suid', 'sgid', 'sticky', 'fstab', 'mount', 'filesystem', 'ファイルシステム', 'process',
            'プロセス', 'rpm', 'dnf', 'package', 'パッケージ', 'ssh', 'apache', 'bind', 'dns', 'network',
            'ネットワーク', 'server', 'サーバー', 'grep', 'sed', 'awk', 'vi', 'vim', 'ユーザー管理', '権限', 'パーミッション',
        ],
        'cloud' => ['aws', 'ec2', 's3', 'cloudfront', 'cdn', 'cloud', 'クラウド', 'vps'],
        'database' => ['sql', 'mysql', 'mariadb', 'database', 'データベース', 'db', 'table', 'テーブル', 'query', 'クエリ'],
        'web' => ['laravel', 'php', 'blade', 'html', 'css', 'javascript', 'js', 'vite', 'web', 'http', 'frontend', 'backend', 'フロントエンド', 'バックエンド'],
    ];

    public function category(string $subject): string
    {
        $normalized = Str::lower($subject);

        return match (true) {
            $this->containsAny($normalized, ['linux', 'linuc', 'systemd', 'systemctl', 'shell', 'シェル', 'bash']) => 'LINUX',
            $this->containsAny($normalized, self::AUTO_RULE_KEYWORDS['cloud']) => 'CLOUD',
            $this->containsAny($normalized, ['php', 'laravel', 'プログラミング', 'javascript', 'python', '関数', '変数', 'blade', 'vite']) => 'PROGRAMMING',
            $this->containsAny($normalized, ['network', 'ネットワーク', 'dns', 'apache', 'server', 'サーバー', 'ssh', 'bind']) => 'SERVER / NETWORK',
            $this->containsAny($normalized, ['web', 'html', 'css', 'ブラウザ', 'http', 'frontend', 'backend', 'フロントエンド', 'バックエンド']) => 'WEB',
            $this->containsAny($normalized, self::AUTO_RULE_KEYWORDS['database']) => 'DATABASE',
            default => 'LEARNING',
        };
    }

    public function autoRule(string $subject): ?string
    {
        $normalized = Str::lower($subject);

        foreach (['linuc', 'cloud', 'database', 'web'] as $rule) {
            if ($this->containsAny($normalized, self::AUTO_RULE_KEYWORDS[$rule])) {
                return $rule;
            }
        }

        return null;
    }

    /** @param array<int, string> $keywords */
    private function containsAny(string $subject, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (mb_strlen($keyword) <= 3 && preg_match('/^[a-z0-9]+$/', $keyword) === 1) {
                if (preg_match('/(?<![a-z0-9])'.preg_quote($keyword, '/').'(?![a-z0-9])/u', $subject) === 1) {
                    return true;
                }

                continue;
            }

            if (Str::contains($subject, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
