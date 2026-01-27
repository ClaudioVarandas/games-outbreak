<?php

namespace Database\Factories;

use App\Models\Genre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Genre>
 */
class GenreFactory extends Factory
{
    protected $model = Genre::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'igdb_id' => null,
            'name' => ucwords($name),
            'slug' => str()->slug($name),
            'is_system' => false,
            'is_visible' => true,
            'is_pending_review' => false,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_visible' => false,
        ]);
    }

    public function pendingReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pending_review' => true,
        ]);
    }
}
