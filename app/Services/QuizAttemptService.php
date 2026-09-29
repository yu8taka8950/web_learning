<?php

namespace App\Services;

use App\Models\ExtensionQuizDraft;
use App\Models\LearningSet;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QuizAttemptService
{
    public function forDraft(User $user, ExtensionQuizDraft $draft): QuizAttempt
    {
        return $user->quizAttempts()->firstOrCreate([
            'extension_quiz_draft_id' => $draft->id,
            'status' => 'in_progress',
        ], [
            'learning_set_id' => null,
            'current_question_index' => 0,
            'total_questions' => count($draft->generated_questions),
            'started_at' => now(),
        ]);
    }

    public function forLearningSet(User $user, LearningSet $learningSet): QuizAttempt
    {
        return $user->quizAttempts()->firstOrCreate([
            'learning_set_id' => $learningSet->id,
            'status' => 'in_progress',
        ], [
            'extension_quiz_draft_id' => null,
            'current_question_index' => 0,
            'total_questions' => $learningSet->questions()->count(),
            'started_at' => now(),
        ]);
    }

    public function firstUnansweredIndex(QuizAttempt $attempt): int
    {
        $answered = $attempt->answers()->pluck('question_index')->mapWithKeys(fn (int $index): array => [$index => true]);

        for ($index = 0; $index < $attempt->total_questions; $index++) {
            if (! $answered->has($index)) {
                return $index;
            }
        }

        return $attempt->total_questions;
    }

    public function answer(QuizAttempt $attempt, int $questionIndex, ?string $selectedOption, ?int $questionId, string $correctOption): QuizAttempt
    {
        return DB::transaction(function () use ($attempt, $questionIndex, $selectedOption, $questionId, $correctOption): QuizAttempt {
            $lockedAttempt = QuizAttempt::query()->whereKey($attempt)->lockForUpdate()->firstOrFail();
            $existing = $lockedAttempt->answers()->where('question_index', $questionIndex)->first();

            if ($existing) {
                return $lockedAttempt;
            }

            abort_unless($lockedAttempt->status === 'in_progress', 409);

            abort_unless($questionIndex === $this->firstUnansweredIndex($lockedAttempt), 409);

            $lockedAttempt->answers()->create([
                'question_id' => $questionId,
                'question_index' => $questionIndex,
                'selected_option' => $selectedOption,
                'is_correct' => $selectedOption !== null && $selectedOption === $correctOption,
                'answered_at' => now(),
            ]);

            $nextIndex = $this->firstUnansweredIndex($lockedAttempt);
            $completed = $nextIndex >= $lockedAttempt->total_questions;
            $lockedAttempt->update([
                'current_question_index' => $nextIndex,
                'status' => $completed ? 'completed' : 'in_progress',
                'completed_at' => $completed ? now() : null,
            ]);

            return $lockedAttempt->refresh();
        });
    }
}
