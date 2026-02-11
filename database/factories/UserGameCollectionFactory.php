<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserGameCollection>
 */
class UserGameCollectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true)."'s Games",
            'description' => fake()->optional()->sentence(),
            'cover_image_path' => null,
            'privacy_playing' => true,
            'privacy_played' => true,
            'privacy_backlog' => true,
            'privacy_wishlist' => true,
        ];
    }

    public function allPrivate(): static
    {
        return $this->state(fn (array $attributes) => [
            'privacy_playing' => false,
            'privacy_played' => false,
            'privacy_backlog' => false,
            'privacy_wishlist' => false,
        ]);
    }
}
