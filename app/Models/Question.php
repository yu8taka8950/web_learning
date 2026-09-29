<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'question',
    'input_question',
    'option_a',
    'option_b',
    'option_c',
    'option_d',
    'correct_option',
    'explanation',
    'review_stage',
    'next_review_at',
    'last_reviewed_at',
    'review_count',
    'correct_review_count',
])]
class Question extends Model
{
    protected function casts(): array
    {
        return [
            'review_stage' => 'integer',
            'next_review_at' => 'datetime',
            'last_reviewed_at' => 'datetime',
            'review_count' => 'integer',
            'correct_review_count' => 'integer',
        ];
    }

    #[Scope]
    protected function dueForUser(Builder $query, User $user): void
    {
        $query->whereHas('learningSet', fn (Builder $learningSetQuery) => $learningSetQuery->whereBelongsTo($user))
            ->whereNotNull('next_review_at')
            ->where('next_review_at', '<=', now());
    }

    public function learningSet(): BelongsTo
    {
        return $this->belongsTo(LearningSet::class);
    }
}
