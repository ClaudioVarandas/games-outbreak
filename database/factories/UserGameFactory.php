<?php

namespace Database\Factories;

use App\Enums\UserGameStatusEnum;
use App\Models\Game;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserGame>
 */
class UserGameFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'game_id' => Game::factory(),
            'status' => fake()->randomElement(UserGameStatusEnum::cases()),
            'is_wishlisted' => false,
            'time_played' => null,
            'rating' => null,
            'sort_order' => 0,
            'added_at' => now(),
            'status_changed_at' => now(),
            'wishlisted_at' => null,
        ];
    }

    public function playing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserGameStatusEnum::Playing,
            'status_changed_at' => now(),
        ]);
    }

    public function played(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserGameStatusEnum::Played,
            'status_changed_at' => now(),
        ]);
    }

    public function backlog(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserGameStatusEnum::Backlog,
            'status_changed_at' => now(),
        ]);
    }

    public function wishlisted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_wishlisted' => true,
            'wishlisted_at' => now(),
        ]);
    }

    public function withTimePlayed(float $hours = 10.0): static
    {
        return $this->state(fn (array $attributes) => [
            'time_played' => $hours,
        ]);
    }

    public function withRating(int $rating = 85): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => $rating,
        ]);
    }
}
