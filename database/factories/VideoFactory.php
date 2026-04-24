<?php

namespace Database\Factories;

use App\Enums\VideoImportStatusEnum;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    public function definition(): array
    {
        $youtubeId = Str::random(11);

        return [
            'url' => "https://www.youtube.com/watch?v={$youtubeId}",
            'youtube_id' => $youtubeId,
            'status' => VideoImportStatusEnum::Pending,
            'is_featured' => false,
            'is_active' => true,
            'user_id' => User::factory(),
        ];
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'status' => VideoImportStatusEnum::Ready,
            'title' => fake()->sentence(6),
            'channel_name' => fake()->company(),
            'channel_id' => 'UC'.Str::random(22),
            'duration_seconds' => fake()->numberBetween(60, 7200),
            'thumbnail_url' => 'https://i.ytimg.com/vi/'.Str::random(11).'/maxresdefault.jpg',
            'description' => fake()->paragraph(),
            'published_at' => fake()->dateTimeBetween('-1 year'),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => VideoImportStatusEnum::Failed,
            'failure_reason' => 'YouTube API returned 404',
        ]);
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function forCategory(string $slug = 'trailers'): static
    {
        return $this->state(function () use ($slug) {
            $defaults = [
                'trailers' => ['name' => 'Trailers', 'color' => '#ff8a2a', 'icon' => 'film'],
                'gameplay' => ['name' => 'Gameplay', 'color' => '#63f3ff', 'icon' => 'play'],
                'reviews' => ['name' => 'Reviews', 'color' => '#c4b5fd', 'icon' => 'star'],
                'tech' => ['name' => 'Tech', 'color' => '#86efac', 'icon' => 'cpu-chip'],
            ];

            $attrs = $defaults[$slug] ?? ['name' => ucfirst($slug), 'color' => '#b581ff', 'icon' => null];

            $category = VideoCategory::firstOrCreate(
                ['slug' => $slug],
                array_merge($attrs, ['is_active' => true]),
            );

            return ['video_category_id' => $category->id];
        });
    }
}
