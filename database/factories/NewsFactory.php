<?php

namespace Database\Factories;

use App\Enums\NewsStatusEnum;
use App\Models\News;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\News>
 */
class NewsFactory extends Factory
{
    protected $model = News::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 9999),
            'image_path' => null,
            'summary' => fake()->text(200),
            'content' => $this->generateTiptapContent(),
            'status' => NewsStatusEnum::Draft,
            'source_url' => null,
            'source_name' => null,
            'tags' => fake()->randomElements(['gaming', 'news', 'review', 'announcement', 'update'], 2),
            'user_id' => User::factory(),
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => NewsStatusEnum::Published,
            'published_at' => now()->subHours(fake()->numberBetween(1, 72)),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => NewsStatusEnum::Archived,
            'published_at' => now()->subDays(fake()->numberBetween(30, 90)),
        ]);
    }

    public function withSource(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_url' => fake()->url(),
            'source_name' => fake()->company(),
        ]);
    }

    public function withImage(): static
    {
        return $this->state(fn (array $attributes) => [
            'image_path' => 'https://picsum.photos/seed/'.fake()->uuid().'/1200/630',
        ]);
    }

    protected function generateTiptapContent(): array
    {
        return [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => fake()->paragraph(4),
                        ],
                    ],
                ],
                [
                    'type' => 'paragraph',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => fake()->paragraph(3),
                        ],
                    ],
                ],
                [
                    'type' => 'paragraph',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => fake()->paragraph(5),
                        ],
                    ],
                ],
            ],
        ];
    }
}
