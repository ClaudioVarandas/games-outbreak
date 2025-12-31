<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReleaseDateStatus>
 */
class ReleaseDateStatusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'igdb_id' => fake()->unique()->numberBetween(1, 100),
            'name' => fake()->randomElement(['Released', 'Alpha', 'Beta', 'Early Access', 'Offline', 'Cancelled', 'Rumored', 'Delisted']),
            'abbreviation' => fake()->lexify('???'),
            'description' => fake()->sentence(),
        ];
    }
}
