<?php

namespace App\Models;

use Database\Factories\ReviewAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'question_ids', 'current_question_index', 'total_questions', 'status', 'started_at', 'completed_at'])]
class ReviewAttempt extends Model
{
    /** @use HasFactory<ReviewAttemptFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ReviewAttemptAnswer::class);
    }

    protected function casts(): array
    {
        return [
            'question_ids' => 'array',
            'current_question_index' => 'integer',
            'total_questions' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
