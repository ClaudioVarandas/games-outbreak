<?php

use App\Models\Game;
use App\Models\GameList;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders a row trailer thumbnail on the yearly releases list view when a game has a video_url', function () {
    $list = GameList::factory()->yearly()->system()->create([
        'slug' => 'game-releases-2026',
        'is_public' => true,
        'is_active' => true,
        'start_at' => now()->setDate(2026, 1, 1),
        'end_at' => now()->setDate(2026, 12, 31),
    ]);

    $game = Game::factory()->create(['name' => "Marvel's Wolverine"]);
    $list->games()->attach($game->id, [
        'order' => 1,
        'release_date' => now()->setDate(2026, 9, 1),
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    $this->get('/releases/2026/9')
        ->assertOk()
        ->assertSee('data-video-id="dQw4w9WgXcQ"', false)
        ->assertSee('img.youtube.com/vi/dQw4w9WgXcQ/mqdefault.jpg', false);
});
