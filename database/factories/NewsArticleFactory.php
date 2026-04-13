<?php

namespace Database\Factories;

use App\Enums\NewsArticleStatusEnum;
use App\Models\NewsArticle;
use App\Models\NewsImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsArticle>
 */
class NewsArticleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'news_import_id' => NewsImport::factory(),
            'user_id' => User::factory(),
            'status' => NewsArticleStatusEnum::Review,
            'source_name' => fake()->randomElement(['IGN', 'Kotaku', 'Polygon']),
            'source_url' => fake()->url(),
            'original_title' => fake()->sentence(8),
            'original_language' => 'en',
            'featured_image_url' => fake()->imageUrl(),
        ];
    }

    public function published(): static
    {
        return $this->state([
            'status' => NewsArticleStatusEnum::Published,
            'published_at' => now(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state([
            'status' => NewsArticleStatusEnum::Scheduled,
            'scheduled_at' => now()->addHours(2),
        ]);
    }
}
