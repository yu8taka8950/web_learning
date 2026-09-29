<?php

namespace Database\Factories;

use App\Models\TermExplanation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TermExplanation>
 */
class TermExplanationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $term = fake()->unique()->word();

        return [
            'normalized_term' => mb_strtolower($term),
            'display_term' => $term,
            'subject_key' => 'general',
            'subject_label' => '一般',
            'topic_label' => null,
            'explanation' => fake()->sentence(),
            'provider' => 'gemini',
            'model' => 'gemini-test',
        ];
    }
}
