<?php

namespace Database\Factories;

use App\Models\LearningTermBox;
use App\Models\User;
use App\Services\DictionaryNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningTermBox>
 */
class LearningTermBoxFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'name_key' => app(DictionaryNormalizer::class)->key($name),
            'kind' => LearningTermBox::KIND_MANUAL,
            'auto_rule' => null,
        ];
    }
}
