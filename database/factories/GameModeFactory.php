<?php

namespace Database\Factories;

use App\Models\GameMode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GameMode>
 */
class GameModeFactory extends Factory
{
    protected $model = GameMode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $modes = [
            ['igdb_id' => 1, 'name' => 'Single player'],
            ['igdb_id' => 2, 'name' => 'Multiplayer'],
            ['igdb_id' => 3, 'name' => 'Co-operative'],
            ['igdb_id' => 4, 'name' => 'Split screen'],
            ['igdb_id' => 5, 'name' => 'Massively Multiplayer Online (MMO)'],
            ['igdb_id' => 6, 'name' => 'Battle Royale'],
        ];

        $mode = fake()->randomElement($modes);

        return [
            'igdb_id' => $mode['igdb_id'],
            'name' => $mode['name'],
        ];
    }
}
