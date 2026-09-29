<?php

namespace App\Services;

class LearningSetIconService
{
    /**
     * @var array<string, string>
     */
    private const ICONS = [
        'linux' => 'terminal', 'unix' => 'terminal', 'linuc' => 'terminal',
        'web' => 'code-2', 'html' => 'code-2', 'css' => 'code-2', 'javascript' => 'code-2', 'php' => 'code-2', 'laravel' => 'code-2',
        'database' => 'database', 'db' => 'database', 'sql' => 'database', 'mysql' => 'database', 'mariadb' => 'database',
        'security' => 'shield-check', 'セキュリティ' => 'shield-check',
        'network' => 'network', 'ネットワーク' => 'network',
        'aws' => 'cloud', 'cloud' => 'cloud', 'クラウド' => 'cloud',
        'server' => 'server', 'infra' => 'server', 'インフラ' => 'server',
        'programming' => 'braces', 'プログラミング' => 'braces', '資格' => 'badge-check', 'exam' => 'badge-check',
    ];

    public function for(string ...$values): string
    {
        $text = mb_strtolower(implode(' ', $values));

        foreach (self::ICONS as $keyword => $icon) {
            if (str_contains($text, $keyword)) {
                return $icon;
            }
        }

        return 'book-open';
    }
}
