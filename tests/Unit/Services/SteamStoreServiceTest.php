<?php

use App\Models\Game;
use App\Services\SteamStoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SteamStoreService;
});

it('fetches metacritic score and url on success', function () {
    Http::fake([
        'store.steampowered.com/api/appdetails*' => Http::response([
            '620' => ['success' => true, 'data' => ['metacritic' => [
                'score' => 90,
                'url' => 'https://www.metacritic.com/game/portal-2',
            ]]],
        ], 200),
    ]);

    expect($this->service->fetchMetacritic(620))->toBe([
        'score' => 90,
        'url' => 'https://www.metacritic.com/game/portal-2',
    ]);
});

it('returns null metacritic when absent or request fails', function () {
    Http::fake(['store.steampowered.com/api/appdetails*' => Http::response(['620' => ['success' => true, 'data' => []]], 200)]);
    expect($this->service->fetchMetacritic(620))->toBeNull();

    Http::fake(['store.steampowered.com/api/appdetails*' => Http::response([], 500)]);
    expect($this->service->fetchMetacritic(620))->toBeNull();
});

it('fetches review summary and computes percent', function () {
    Http::fake([
        'store.steampowered.com/appreviews/*' => Http::response([
            'success' => 1,
            'query_summary' => [
                'review_score_desc' => 'Very Positive',
                'total_positive' => 900,
                'total_negative' => 100,
                'total_reviews' => 1000,
            ],
        ], 200),
    ]);

    expect($this->service->fetchReviewSummary(620))->toBe([
        'desc' => 'Very Positive',
        'percent' => 90,
        'total' => 1000,
        'positive' => 900,
        'negative' => 100,
    ]);
});

it('returns null review summary when there are no reviews', function () {
    Http::fake([
        'store.steampowered.com/appreviews/*' => Http::response([
            'success' => 1,
            'query_summary' => ['total_reviews' => 0],
        ], 200),
    ]);

    expect($this->service->fetchReviewSummary(620))->toBeNull();
});

it('persists both metacritic and review scores via syncScores', function () {
    Http::fake([
        'store.steampowered.com/api/appdetails*' => Http::response([
            '620' => ['success' => true, 'data' => ['metacritic' => ['score' => 88, 'url' => 'https://m.example']]],
        ], 200),
        'store.steampowered.com/appreviews/*' => Http::response([
            'query_summary' => [
                'review_score_desc' => 'Very Positive',
                'total_positive' => 800,
                'total_negative' => 200,
                'total_reviews' => 1000,
            ],
        ], 200),
    ]);

    $game = Game::factory()->create();

    expect($this->service->syncScores($game, 620))->toBeTrue();

    $game->refresh();
    expect($game->metacritic_score)->toBe(88)
        ->and($game->metacritic_url)->toBe('https://m.example')
        ->and($game->steam_review_percent)->toBe(80)
        ->and($game->steam_review_desc)->toBe('Very Positive')
        ->and($game->steam_review_total)->toBe(1000)
        ->and($game->last_steam_review_sync_at)->not->toBeNull();
});

describe('reviewScoresAreStale', function () {
    it('skips far-future releases', function () {
        $game = Game::factory()->create([
            'first_release_date' => now()->addDays(60),
            'last_steam_review_sync_at' => null,
        ]);
        expect($this->service->reviewScoresAreStale($game))->toBeFalse();
    });

    it('is stale when never synced and not far-future', function () {
        $game = Game::factory()->create([
            'first_release_date' => now()->subDays(60),
            'last_steam_review_sync_at' => null,
        ]);
        expect($this->service->reviewScoresAreStale($game))->toBeTrue();
    });

    it('syncs daily near release', function () {
        $game = Game::factory()->create([
            'first_release_date' => now()->addDays(3),
            'last_steam_review_sync_at' => now()->subDays(2),
        ]);
        expect($this->service->reviewScoresAreStale($game))->toBeTrue();
    });

    it('syncs every 3 days for just-released games', function () {
        $recent = Game::factory()->create([
            'first_release_date' => now()->subDays(20),
            'last_steam_review_sync_at' => now()->subDay(),
        ]);
        expect($this->service->reviewScoresAreStale($recent))->toBeFalse();

        $stale = Game::factory()->create([
            'first_release_date' => now()->subDays(20),
            'last_steam_review_sync_at' => now()->subDays(4),
        ]);
        expect($this->service->reviewScoresAreStale($stale))->toBeTrue();
    });

    it('syncs every 30 days for established games', function () {
        $fresh = Game::factory()->create([
            'first_release_date' => now()->subYears(2),
            'last_steam_review_sync_at' => now()->subDays(10),
        ]);
        expect($this->service->reviewScoresAreStale($fresh))->toBeFalse();

        $stale = Game::factory()->create([
            'first_release_date' => now()->subYears(2),
            'last_steam_review_sync_at' => now()->subDays(31),
        ]);
        expect($this->service->reviewScoresAreStale($stale))->toBeTrue();
    });
});
