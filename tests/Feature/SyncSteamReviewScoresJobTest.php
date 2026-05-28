<?php

use App\Jobs\SyncSteamReviewScores;
use App\Models\ExternalGameSource;
use App\Models\Game;
use App\Models\GameExternalSource;
use App\Services\IgdbService;
use App\Services\SteamStoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function steamLinkedGame(int $appId): Game
{
    $steamSource = ExternalGameSource::create([
        'igdb_id' => 1,
        'name' => 'Steam',
        'slug' => 'steam',
    ]);

    $game = Game::factory()->create();

    GameExternalSource::create([
        'game_id' => $game->id,
        'external_game_source_id' => $steamSource->id,
        'external_uid' => (string) $appId,
        'sync_status' => 'pending',
    ]);

    return $game;
}

it('persists Metacritic and Steam review scores for a Steam-linked game', function () {
    Http::fake([
        'store.steampowered.com/api/appdetails*' => Http::response([
            '620' => ['success' => true, 'data' => ['metacritic' => ['score' => 95, 'url' => 'https://m.example']]],
        ], 200),
        'store.steampowered.com/appreviews/*' => Http::response([
            'query_summary' => [
                'review_score_desc' => 'Overwhelmingly Positive',
                'total_positive' => 980,
                'total_negative' => 20,
                'total_reviews' => 1000,
            ],
        ], 200),
    ]);

    $game = steamLinkedGame(620);

    (new SyncSteamReviewScores($game->id))->handle(
        app(SteamStoreService::class),
        app(IgdbService::class),
    );

    $game->refresh();
    expect($game->metacritic_score)->toBe(95)
        ->and($game->steam_review_percent)->toBe(98)
        ->and($game->steam_review_desc)->toBe('Overwhelmingly Positive');
});

it('is a safe no-op when the game has no Steam link', function () {
    Http::fake();

    $game = Game::factory()->create(['last_steam_review_sync_at' => null]);

    (new SyncSteamReviewScores($game->id))->handle(
        app(SteamStoreService::class),
        app(IgdbService::class),
    );

    $game->refresh();
    expect($game->metacritic_score)->toBeNull()
        ->and($game->last_steam_review_sync_at)->toBeNull();

    Http::assertNothingSent();
});
