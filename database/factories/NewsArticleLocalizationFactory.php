<?php

namespace Database\Factories;

use App\Enums\NewsLocaleEnum;
use App\Models\NewsArticle;
use App\Models\NewsArticleLocalization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsArticleLocalization>
 */
class NewsArticleLocalizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'news_article_id' => NewsArticle::factory(),
            'locale' => NewsLocaleEnum::PtPt,
            'title' => fake()->sentence(8),
            'summary_short' => fake()->sentence(20),
            'summary_medium' => fake()->paragraph(3),
            'body' => [
                'type' => 'doc',
                'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => fake()->paragraph()]]]],
            ],
            'seo_title' => fake()->sentence(6),
            'seo_description' => fake()->sentence(20),
        ];
    }

    public function ptBr(): static
    {
        return $this->state(['locale' => NewsLocaleEnum::PtBr]);
    }
}
