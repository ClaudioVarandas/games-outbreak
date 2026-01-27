<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    public function run(): void
    {
        $genres = [
            ['name' => 'Metroidvania', 'slug' => 'metroidvania', 'sort_order' => 1],
            ['name' => 'Roguelike', 'slug' => 'roguelike', 'sort_order' => 2],
            ['name' => 'Platformer', 'slug' => 'platformer', 'sort_order' => 3],
            ['name' => 'Adventure', 'slug' => 'adventure', 'sort_order' => 4],
            ['name' => 'Action', 'slug' => 'action', 'sort_order' => 5],
            ['name' => 'RPG', 'slug' => 'rpg', 'sort_order' => 6],
            ['name' => 'Simulation', 'slug' => 'simulation', 'sort_order' => 7],
            ['name' => 'Strategy', 'slug' => 'strategy', 'sort_order' => 8],
            ['name' => 'Horror', 'slug' => 'horror', 'sort_order' => 9],
            ['name' => 'Beat-em-up', 'slug' => 'beat-em-up', 'sort_order' => 10],
            ['name' => 'Shooter', 'slug' => 'shooter', 'sort_order' => 11],
            ['name' => 'Souls-like', 'slug' => 'souls-like', 'sort_order' => 12],
            ['name' => 'Puzzle', 'slug' => 'puzzle', 'sort_order' => 13],
            ['name' => 'Racing', 'slug' => 'racing', 'sort_order' => 14],
            ['name' => 'Sports', 'slug' => 'sports', 'sort_order' => 15],
            ['name' => 'Fighting', 'slug' => 'fighting', 'sort_order' => 16],
            ['name' => 'Visual Novel', 'slug' => 'visual-novel', 'sort_order' => 17],
            ['name' => 'City Builder', 'slug' => 'city-builder', 'sort_order' => 18],
            ['name' => 'Tower Defense', 'slug' => 'tower-defense', 'sort_order' => 19],
            ['name' => 'Survival', 'slug' => 'survival', 'sort_order' => 20],
            ['name' => 'Farming Sim', 'slug' => 'farming-sim', 'sort_order' => 21],
            ['name' => 'Other', 'slug' => 'other', 'is_system' => true, 'sort_order' => 999999],
        ];

        foreach ($genres as $genreData) {
            Genre::firstOrCreate(
                ['slug' => $genreData['slug']],
                array_merge($genreData, [
                    'is_visible' => true,
                    'is_pending_review' => false,
                ])
            );
        }
    }
}
