<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\VideoCategory;
use Illuminate\Database\Seeder;

class VideoCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'trailers', 'name' => 'Trailers', 'color' => '#ff8a2a', 'icon' => 'film'],
            ['slug' => 'gameplay', 'name' => 'Gameplay', 'color' => '#63f3ff', 'icon' => 'play'],
            ['slug' => 'reviews', 'name' => 'Reviews', 'color' => '#c4b5fd', 'icon' => 'star'],
            ['slug' => 'tech', 'name' => 'Tech', 'color' => '#86efac', 'icon' => 'cpu-chip'],
        ];

        foreach ($categories as $category) {
            VideoCategory::firstOrCreate(
                ['slug' => $category['slug']],
                array_merge($category, ['is_active' => true]),
            );
        }
    }
}
