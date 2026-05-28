<?php

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Pennant\Feature;

uses(RefreshDatabase::class);

beforeEach(fn () => Queue::fake());

it('renders the content-first hero', function () {
    $game = Game::factory()->create([
        'name' => 'Hero Test Game',
        'first_release_date' => now()->subYear(),
        'metacritic_score' => 80,
        'steam_review_percent' => 90,
        'steam_review_desc' => 'Very Positive',
    ]);

    $this->get(route('game.show', $game))
        ->assertOk()
        ->assertSee('Hero Test Game')
        ->assertSee('Available now')
        ->assertSee('Scores')
        ->assertSee('Metacritic');
});

it('always shows the score cards, even with no scores', function () {
    $game = Game::factory()->create([
        'metacritic_score' => null,
        'steam_review_percent' => null,
        'igdb_aggregated_rating' => null,
    ]);

    $this->get(route('game.show', $game))
        ->assertOk()
        ->assertSee('Steam')
        ->assertSee('Metacritic')
        ->assertDontSee('IGDB Critics');
});

it('only shows anchors for sections that exist', function () {
    // Upcoming game with no scores and no screenshots.
    $game = Game::factory()->create([
        'first_release_date' => now()->addMonths(3),
        'metacritic_score' => null,
        'steam_review_percent' => null,
        'igdb_aggregated_rating' => null,
        'screenshots' => null,
    ]);

    $response = $this->get(route('game.show', $game))->assertOk();
    // Anchor pills (with smooth-scroll handler) — absent for empty sections, present for always-on ones.
    $response->assertDontSee("getElementById('scores')", false)
        ->assertDontSee("getElementById('screenshots')", false)
        ->assertSee("getElementById('release-dates')", false)
        ->assertSee("getElementById('similar-games')", false);
});

it('hides collection buttons when the flag is off', function () {
    $game = Game::factory()->create();

    $this->get(route('game.show', $game))
        ->assertOk()
        ->assertDontSee('gameCollectionActions(', false);
});

it('shows collection buttons when the flag is on', function () {
    Feature::define('game_user_actions', fn () => true);
    $game = Game::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('game.show', $game))
        ->assertOk()
        ->assertSee('gameCollectionActions(', false);
});
