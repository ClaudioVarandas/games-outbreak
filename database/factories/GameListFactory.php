<?php

namespace Database\Factories;

use App\Enums\ListTypeEnum;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GameList>
 */
class GameListFactory extends Factory
{
    protected $model = GameList::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'description' => fake()->sentence(),
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1000, 9999),
            'is_public' => false,
            'is_system' => false,
            'is_active' => true,
            'list_type' => ListTypeEnum::REGULAR,
            'start_at' => null,
            'end_at' => null,
        ];
    }

    /**
     * Indicate that the list is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    /**
     * Indicate that the list is a system list.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
            'user_id' => null,
            'is_active' => true,
            'start_at' => now()->subDays(1),
            'end_at' => now()->addDays(30),
        ]);
    }

    /**
     * Indicate that the list is a backlog.
     */
    public function backlog(): static
    {
        return $this->state(fn (array $attributes) => [
            'list_type' => ListTypeEnum::BACKLOG,
            'name' => 'Backlog',
        ]);
    }

    /**
     * Indicate that the list is a wishlist.
     */
    public function wishlist(): static
    {
        return $this->state(fn (array $attributes) => [
            'list_type' => ListTypeEnum::WISHLIST,
            'name' => 'Wishlist',
        ]);
    }

    /**
     * Indicate that the list is active (for system lists).
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'start_at' => now()->subDays(1),
            'end_at' => now()->addDays(30),
        ]);
    }
}
