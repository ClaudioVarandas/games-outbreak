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
        ->assertDontSee('IGDB Critics');
});

it('keeps all three slots with placeholders when some scores are missing', function () {
    $game = Game::factory()->create([
        'metacritic_score' => null,
        'steam_review_percent' => 83,
        'steam_review_desc' => 'Very Positive',
        'steam_review_total' => 3719,
        'igdb_aggregated_rating' => null,
    ]);

    $this->get(route('game.show', $game))
        ->assertOk()
        ->assertSee('Scores')
        ->assertSee('Metacritic')   // label still present
        ->assertSee('No score')     // metacritic placeholder
        ->assertSee('83%')
        ->assertDontSee('IGDB Critics');
});

it('hides the scores section when no scores are present', function () {
    // The hero snapshot always renders; only the full Scores section is conditional.
    $game = Game::factory()->create([
        'metacritic_score' => null,
        'steam_review_percent' => null,
        'igdb_aggregated_rating' => null,
    ]);

    $this->get(route('game.show', $game))
        ->assertOk()
        ->assertDontSee('id="scores"', false);
});
