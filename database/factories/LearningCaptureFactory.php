<?php

namespace Database\Factories;

use App\Models\LearningCapture;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningCapture>
 */
class LearningCaptureFactory extends Factory
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
            'page_title' => fake()->sentence(),
            'source_url' => fake()->url(),
            'normalized_source_url' => fake()->url(),
            'source_host' => 'example.test',
            'status' => 'new',
            'captured_at' => now(),
        ];
    }
}
