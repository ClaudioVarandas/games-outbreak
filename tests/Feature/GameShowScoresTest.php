<?php

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake(); // show() may dispatch refresh/score jobs; don't run them inline.
});

it('renders the score blocks when scores are present', function () {
    $game = Game::factory()->create([
        'metacritic_score' => 91,
        'metacritic_url' => 'https://www.metacritic.com/game/example',
        'steam_review_percent' => 96,
        'steam_review_desc' => 'Overwhelmingly Positive',
        'steam_review_total' => 12345,
        'igdb_aggregated_rating' => 88,
        'igdb_aggregated_rating_count' => 12,
    ]);

    $this->get(route('game.show', $game))
        ->assertOk()
        ->assertSee('Scores')
        ->assertSee('Metacritic')
        ->assertSee('91')
        ->assertSee('Overwhelmingly Positive')
        ->assertSee('96%')
        ->assertSee('IGDB Critics')
        ->assertSee('88');
});

it('hides the score block when no scores are present', function () {
    $game = Game::factory()->create([
        'metacritic_score' => null,
        'steam_review_percent' => null,
        'igdb_aggregated_rating' => null,
    ]);

    $this->get(route('game.show', $game))
        ->assertOk()
        ->assertDontSee('IGDB Critics')
        ->assertDontSee('Metacritic');
});
