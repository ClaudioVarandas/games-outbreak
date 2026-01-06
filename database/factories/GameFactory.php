<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Game>
 */
class GameFactory extends Factory
{
    protected $model = Game::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'igdb_id' => fake()->unique()->numberBetween(1000, 999999),
            'name' => fake()->words(3, true),
            'summary' => fake()->paragraph(),
            'first_release_date' => fake()->dateTimeBetween('-1 year', '+1 year'),
            'cover_image_id' => 'co' . fake()->lexify('????'),
            'hero_image_id' => null,
            'logo_image_id' => null,
            'game_type' => 0,
            'steam_data' => null,
            'screenshots' => null,
            'trailers' => null,
            'similar_games' => null,
        ];
    }

    /**
     * Indicate that the game has Steam data.
     */
    public function withSteamData(): static
    {
        return $this->state(fn (array $attributes) => [
            'steam_data' => [
                'appid' => fake()->numberBetween(10000, 999999),
                'header_image' => 'https://cdn.akamai.steamstatic.com/steam/apps/' . fake()->numberBetween(10000, 999999) . '/header.jpg',
                'recommendations' => ['total' => fake()->numberBetween(100, 10000)],
            ],
        ]);
    }

    /**
     * Indicate that the game is upcoming (future release date).
     */
    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'first_release_date' => fake()->dateTimeBetween('+1 day', '+6 months'),
        ]);
    }

    /**
     * Indicate that the game has similar games.
     */
    public function withSimilarGames(array $similarGameIds = []): static
    {
        if (empty($similarGameIds)) {
            $similarGameIds = [
                ['id' => fake()->numberBetween(1000, 999999), 'name' => fake()->words(3, true)],
                ['id' => fake()->numberBetween(1000, 999999), 'name' => fake()->words(3, true)],
            ];
        }

        return $this->state(fn (array $attributes) => [
            'similar_games' => $similarGameIds,
        ]);
    }
}
