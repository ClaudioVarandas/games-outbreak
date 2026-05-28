<?php

use App\Models\Game;
use App\Services\IgdbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('persists the IGDB aggregated critic rating on refresh', function () {
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'token'], 200),
        'api.igdb.com/v4/games' => Http::response([[
            'id' => 12345,
            'name' => 'Rated Game',
            'summary' => 'A well-reviewed game',
            'aggregated_rating' => 85.4,
            'aggregated_rating_count' => 10,
            'platforms' => [],
            'genres' => [],
            'game_modes' => [],
            'external_games' => [],
            'websites' => [],
            'game_type' => 0,
            'release_dates' => null,
        ]], 200),
    ]);

    $game = Game::factory()->create([
        'igdb_id' => 12345,
        'igdb_aggregated_rating' => null,
        'igdb_aggregated_rating_count' => null,
    ]);

    expect($game->refreshFromIgdb(app(IgdbService::class)))->toBeTrue();

    $game->refresh();
    expect($game->igdb_aggregated_rating)->toBe(85)
        ->and($game->igdb_aggregated_rating_count)->toBe(10);
});

it('detects a rating-only change so the update is not skipped', function () {
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'token'], 200),
        'api.igdb.com/v4/games' => Http::response([[
            'id' => 777,
            'name' => 'Stable Game',
            'summary' => 'Stable summary',
            'cover' => ['image_id' => 'co123'],
            'aggregated_rating' => 92,
            'aggregated_rating_count' => 9,
            'platforms' => [],
            'genres' => [],
            'game_modes' => [],
            'external_games' => [],
            'websites' => [],
            'game_type' => 0,
            'release_dates' => null,
        ]], 200),
    ]);

    // Everything except the rating matches the incoming IGDB payload, so only the
    // rating differs — this proves generateDataHash accounts for the rating.
    $game = Game::factory()->create([
        'igdb_id' => 777,
        'name' => 'Stable Game',
        'summary' => 'Stable summary',
        'first_release_date' => null,
        'cover_image_id' => 'co123',
        'game_type' => 0,
        'screenshots' => null,
        'trailers' => null,
        'igdb_aggregated_rating' => 80,
        'igdb_aggregated_rating_count' => 5,
    ]);

    $game->refreshFromIgdb(app(IgdbService::class));

    $game->refresh();
    expect($game->igdb_aggregated_rating)->toBe(92)
        ->and($game->igdb_aggregated_rating_count)->toBe(9);
});
