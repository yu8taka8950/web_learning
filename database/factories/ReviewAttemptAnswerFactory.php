<?php

namespace Database\Factories;

use App\Models\ReviewAttempt;
use App\Models\ReviewAttemptAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewAttemptAnswer>
 */
class ReviewAttemptAnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'review_attempt_id' => ReviewAttempt::factory(),
            'question_id' => function (array $attributes): int {
                $attempt = ReviewAttempt::query()->findOrFail($attributes['review_attempt_id']);
                $learningSet = $attempt->user->learningSets()->create([
                    'title' => fake()->words(3, true),
                    'source_type' => 'manual',
                ]);

                return $learningSet->questions()->create([
                    'question' => fake()->sentence(),
                    'option_a' => '正解',
                    'option_b' => '誤答B',
                    'option_c' => '誤答C',
                    'option_d' => '誤答D',
                    'correct_option' => 'A',
                ])->id;
            },
            'question_index' => 0,
            'selected_option' => 'A',
            'is_correct' => true,
            'answered_at' => now(),
        ];
    }
}
