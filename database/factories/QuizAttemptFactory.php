<?php

namespace Database\Factories;

use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizAttempt>
 */
class QuizAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'current_question_index' => 0,
            'total_questions' => 1,
            'status' => 'in_progress',
            'started_at' => now(),
        ];
    }
}
