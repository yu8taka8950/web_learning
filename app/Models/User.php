<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;

#[Fillable(['name', 'email', 'google_id', 'password'])]
#[Hidden(['google_id', 'password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function learningSets(): HasMany
    {
        return $this->hasMany(LearningSet::class);
    }

    public function learningCollections(): HasMany
    {
        return $this->hasMany(LearningCollection::class);
    }

    public function learningTermBoxes(): HasMany
    {
        return $this->hasMany(LearningTermBox::class);
    }

    public function extensionQuizDrafts(): HasMany
    {
        return $this->hasMany(ExtensionQuizDraft::class);
    }

    public function learningCaptures(): HasMany
    {
        return $this->hasMany(LearningCapture::class);
    }

    public function extensionAccessTokens(): HasMany
    {
        return $this->hasMany(ExtensionAccessToken::class);
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function reviewAttempts(): HasMany
    {
        return $this->hasMany(ReviewAttempt::class);
    }
}
