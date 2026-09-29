<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'token',
    'user_id',
    'source_type',
    'source_title',
    'topic',
    'subject',
    'source_url',
    'source_image_path',
    'selected_terms',
    'generated_questions',
    'expires_at',
    'claimed_at',
])]
class ExtensionQuizDraft extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    protected function casts(): array
    {
        return [
            'selected_terms' => 'array',
            'generated_questions' => 'array',
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }
}
