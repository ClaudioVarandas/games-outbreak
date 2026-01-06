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

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $genres = [
            ['igdb_id' => 12, 'name' => 'Role-playing (RPG)'],
            ['igdb_id' => 5, 'name' => 'Shooter'],
            ['igdb_id' => 31, 'name' => 'Adventure'],
            ['igdb_id' => 32, 'name' => 'Indie'],
            ['igdb_id' => 15, 'name' => 'Strategy'],
            ['igdb_id' => 4, 'name' => 'Fighting'],
            ['igdb_id' => 8, 'name' => 'Platform'],
            ['igdb_id' => 9, 'name' => 'Puzzle'],
        ];

        $genre = fake()->randomElement($genres);

        return [
            'igdb_id' => $genre['igdb_id'],
            'name' => $genre['name'],
        ];
    }
}
