<?php

namespace App\Models;

use Database\Factories\ReviewAttemptAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['question_id', 'question_index', 'selected_option', 'is_correct', 'answered_at'])]
class ReviewAttemptAnswer extends Model
{
    /** @use HasFactory<ReviewAttemptAnswerFactory> */
    use HasFactory;

    public function reviewAttempt(): BelongsTo
    {
        return $this->belongsTo(ReviewAttempt::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }
}
