<?php

namespace Database\Factories;

use App\Models\Platform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Platform>
 */
class PlatformFactory extends Factory
{
    protected $model = Platform::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $platforms = [
            ['igdb_id' => 6, 'name' => 'PC (Microsoft Windows)'],
            ['igdb_id' => 167, 'name' => 'PlayStation 5'],
            ['igdb_id' => 169, 'name' => 'Xbox Series X|S'],
            ['igdb_id' => 130, 'name' => 'Nintendo Switch'],
            ['igdb_id' => 48, 'name' => 'PlayStation 4'],
            ['igdb_id' => 49, 'name' => 'Xbox One'],
        ];

        $platform = fake()->randomElement($platforms);

        return [
            'igdb_id' => $platform['igdb_id'],
            'name' => $platform['name'],
            'slug' => null,
        ];
    }
}
