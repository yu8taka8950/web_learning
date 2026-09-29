<?php

namespace App\Models;

use Database\Factories\QuizAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'learning_set_id', 'extension_quiz_draft_id', 'current_question_index', 'total_questions', 'status', 'started_at', 'completed_at'])]
class QuizAttempt extends Model
{
    /** @use HasFactory<QuizAttemptFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function learningSet(): BelongsTo
    {
        return $this->belongsTo(LearningSet::class);
    }

    public function extensionQuizDraft(): BelongsTo
    {
        return $this->belongsTo(ExtensionQuizDraft::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAttemptAnswer::class);
    }

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
