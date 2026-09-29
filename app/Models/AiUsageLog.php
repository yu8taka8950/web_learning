<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    private const ERROR_MESSAGE_MAXIMUM_LENGTH = 1000;

    private const FEATURE_LABELS = [
        'web_term_detection' => 'Web用語検出',
        'quiz_generation' => 'AI問題生成',
        'screenshot_analysis' => 'スクリーンショット解析',
        'learning_chat' => 'AI質問',
        'term_explanation' => '用語解説',
    ];

    protected $fillable = [
        'user_id',
        'provider',
        'model',
        'feature',
        'input_characters',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'success',
        'http_status',
        'error_message',
    ];

    protected $casts = [
        'success' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            $log->error_message = self::sanitizeErrorMessage($log->error_message);
        });
    }

    public static function sanitizeErrorMessage(?string $message): ?string
    {
        if (! is_string($message) || $message === '') {
            return null;
        }

        $sanitized = preg_replace([
            '/(authorization\s*:\s*bearer\s+)[^\s,]+/i',
            '/(api[_ -]?key\s*[=:]\s*)[^\s,]+/i',
            '/(session(?:_id)?\s*[=:]\s*)[^\s,]+/i',
            '/(cookie\s*:\s*)[^\r\n]+/i',
            '/[A-Za-z0-9+\/]{256,}={0,2}/',
        ], ['$1[REDACTED]', '$1[REDACTED]', '$1[REDACTED]', '$1[REDACTED]', '[REDACTED_BINARY]'], $message);

        return mb_substr((string) $sanitized, 0, self::ERROR_MESSAGE_MAXIMUM_LENGTH);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function featureLabel(): string
    {
        return self::FEATURE_LABELS[$this->feature] ?? $this->feature;
    }
}
