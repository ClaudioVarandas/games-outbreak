<?php

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders a youtube trailer thumbnail when a video url is given', function () {
    $game = Game::factory()->create();

    $this->blade(
        '<x-game-card :game="$game" variant="table-row" :videoUrl="$videoUrl" :displayReleaseDate="$date" />',
        [
            'game' => $game,
            'videoUrl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'date' => now()->setDate(2026, 3, 14),
        ]
    )
        ->assertSee('data-video-id="dQw4w9WgXcQ"', false)
        ->assertSee('img.youtube.com/vi/dQw4w9WgXcQ/mqdefault.jpg', false);
});

it('renders no trailer trigger when the game has no video url', function () {
    $game = Game::factory()->create();

    $this->blade(
        '<x-game-card :game="$game" variant="table-row" :videoUrl="$videoUrl" :displayReleaseDate="$date" />',
        ['game' => $game, 'videoUrl' => null, 'date' => now()->setDate(2026, 3, 14)]
    )->assertDontSee('data-video-id', false);
});

it('does not render collection action buttons in the table-row', function () {
    $game = Game::factory()->create();

    $this->blade(
        '<x-game-card :game="$game" variant="table-row" :displayReleaseDate="$date" />',
        ['game' => $game, 'date' => now()->setDate(2026, 3, 14)]
    )
        ->assertDontSee('Wishlist')
        ->assertDontSee('gameCollectionActions(', false);
});

it('renders a compact date chip with day and month', function () {
    $game = Game::factory()->create();

    $this->blade(
        '<x-game-card :game="$game" variant="table-row" :displayReleaseDate="$date" />',
        ['game' => $game, 'date' => now()->setDate(2026, 3, 14)]
    )
        ->assertSee('14')
        ->assertSee('Mar');
});

it('renders TBA instead of a date chip when the row is marked TBA', function () {
    $game = Game::factory()->create();

    $this->blade(
        '<x-game-card :game="$game" variant="table-row" :isTba="true" />',
        ['game' => $game]
    )->assertSee('TBA');
});
