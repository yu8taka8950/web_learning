<?php

namespace App\Models;

use App\Services\ScreenshotStorageService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'topic', 'subject', 'source_type', 'source_url', 'source_image_path'])]
class LearningSet extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::forceDeleted(function (self $learningSet): void {
            app(ScreenshotStorageService::class)->deleteIfUnreferenced($learningSet->source_image_path);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function learningTerms(): HasMany
    {
        return $this->hasMany(LearningTerm::class);
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(LearningCollection::class)
            ->withPivot('assigned_by')
            ->withTimestamps();
    }
}
