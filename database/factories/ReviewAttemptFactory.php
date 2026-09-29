<?php

namespace Database\Factories;

use App\Models\ReviewAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewAttempt>
 */
class ReviewAttemptFactory extends Factory
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
            'question_ids' => [],
            'current_question_index' => 0,
            'total_questions' => 0,
            'status' => 'in_progress',
            'started_at' => now(),
            'completed_at' => null,
        ];
    }
}
