<?php

namespace App\Services;

use App\Models\Question;
use App\Models\ReviewAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReviewAttemptService
{
    private const QUESTION_LIMIT = 20;

    public function __construct(private ReviewScheduleService $reviewScheduleService) {}

    public function startOrResume(User $user): ?ReviewAttempt
    {
        return DB::transaction(function () use ($user): ?ReviewAttempt {
            User::query()->whereKey($user)->lockForUpdate()->firstOrFail();

            $attempt = ReviewAttempt::query()
                ->whereBelongsTo($user)
                ->where('status', 'in_progress')
                ->latest('id')
                ->first();

            if ($attempt) {
                $activeQuestionCount = Question::query()
                    ->whereKey($attempt->question_ids)
                    ->whereHas('learningSet', fn (Builder $query) => $query->whereBelongsTo($user))
                    ->count();

                if ($activeQuestionCount === $attempt->total_questions) {
                    return $attempt;
                }

                $attempt->update(['status' => 'cancelled']);
            }

            $questionIds = Question::query()
                ->dueForUser($user)
                ->orderBy('next_review_at')
                ->orderBy('id')
                ->limit(self::QUESTION_LIMIT)
                ->pluck('id')
                ->map(fn (int $questionId): int => $questionId)
                ->all();

            if ($questionIds === []) {
                return null;
            }

            return $user->reviewAttempts()->create([
                'question_ids' => $questionIds,
                'current_question_index' => 0,
                'total_questions' => count($questionIds),
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
        });
    }

    public function firstUnansweredIndex(ReviewAttempt $attempt): int
    {
        $answeredIndexes = $attempt->answers()
            ->pluck('question_index')
            ->mapWithKeys(fn (int $questionIndex): array => [$questionIndex => true]);

        for ($questionIndex = 0; $questionIndex < $attempt->total_questions; $questionIndex++) {
            if (! $answeredIndexes->has($questionIndex)) {
                return $questionIndex;
            }
        }

        return $attempt->total_questions;
    }

    public function answer(ReviewAttempt $attempt, int $questionIndex, ?string $selectedOption): ReviewAttempt
    {
        return DB::transaction(function () use ($attempt, $questionIndex, $selectedOption): ReviewAttempt {
            $lockedAttempt = ReviewAttempt::query()->whereKey($attempt)->lockForUpdate()->firstOrFail();
            $existingAnswer = $lockedAttempt->answers()->where('question_index', $questionIndex)->first();

            if ($existingAnswer) {
                return $lockedAttempt;
            }

            abort_unless($lockedAttempt->status === 'in_progress', 409);
            abort_unless($questionIndex === $this->firstUnansweredIndex($lockedAttempt), 409);

            $questionId = $lockedAttempt->question_ids[$questionIndex] ?? null;
            abort_unless(is_int($questionId), 404);

            $question = Question::query()
                ->whereKey($questionId)
                ->whereHas('learningSet', fn (Builder $query) => $query->where('user_id', $lockedAttempt->user_id))
                ->lockForUpdate()
                ->firstOrFail();
            $isCorrect = $selectedOption !== null && $selectedOption === $question->correct_option;
            $reviewedAt = CarbonImmutable::now();
            $schedule = $this->reviewScheduleService->scheduleAfterAnswer($question->review_stage, $isCorrect, $reviewedAt);

            $lockedAttempt->answers()->create([
                'question_id' => $question->id,
                'question_index' => $questionIndex,
                'selected_option' => $selectedOption,
                'is_correct' => $isCorrect,
                'answered_at' => $reviewedAt,
            ]);
            $question->update([
                ...$schedule,
                'last_reviewed_at' => $reviewedAt,
                'review_count' => $question->review_count + 1,
                'correct_review_count' => $question->correct_review_count + ($isCorrect ? 1 : 0),
            ]);

            $nextIndex = $this->firstUnansweredIndex($lockedAttempt);
            $completed = $nextIndex >= $lockedAttempt->total_questions;
            $lockedAttempt->update([
                'current_question_index' => $nextIndex,
                'status' => $completed ? 'completed' : 'in_progress',
                'completed_at' => $completed ? $reviewedAt : null,
            ]);

            return $lockedAttempt->refresh();
        });
    }
}
