<?php

namespace Database\Factories;

use App\Enums\NewsImportStatusEnum;
use App\Models\NewsImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsImport>
 */
class NewsImportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'url' => fake()->url(),
            'status' => NewsImportStatusEnum::Pending,
            'user_id' => User::factory(),
        ];
    }

    public function ready(): static
    {
        return $this->state([
            'status' => NewsImportStatusEnum::Ready,
            'raw_title' => fake()->sentence(6),
            'raw_body' => fake()->paragraphs(3, true),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => NewsImportStatusEnum::Failed,
            'failure_reason' => 'Connection timeout',
        ]);
    }

    public function extracted(): static
    {
        return $this->state([
            'status' => NewsImportStatusEnum::Extracted,
            'raw_title' => fake()->sentence(6),
            'raw_body' => fake()->paragraphs(3, true),
        ]);
    }
}
