<?php

namespace Database\Factories;

use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizAttemptAnswer>
 */
class QuizAttemptAnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_attempt_id' => QuizAttempt::factory(),
            'question_index' => 0,
            'selected_option' => 'A',
            'is_correct' => true,
            'answered_at' => now(),
        ];
    }
}
