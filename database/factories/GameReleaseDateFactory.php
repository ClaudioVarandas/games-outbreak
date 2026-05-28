<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameReleaseDate;
use App\Models\Platform;
use App\Models\ReleaseDateStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class GameReleaseDateFactory extends Factory
{
    protected $model = GameReleaseDate::class;

    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'platform_id' => Platform::factory(),
            'status_id' => ReleaseDateStatus::factory(),
            'date' => now(),
            'year' => (int) now()->format('Y'),
            'month' => (int) now()->format('n'),
            'day' => (int) now()->format('j'),
            'human_readable' => now()->format('j M Y'),
            'is_manual' => false,
        ];
    }
}
