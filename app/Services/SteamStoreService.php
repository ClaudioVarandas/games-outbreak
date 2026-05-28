<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SteamStoreService
{
    private const APP_DETAILS_URL = 'https://store.steampowered.com/api/appdetails';

    private const APP_REVIEWS_URL = 'https://store.steampowered.com/appreviews';

    private const RATE_LIMIT_DELAY_MS = 250000; // 250ms = ~4 req/sec

    private const NEAR_RELEASE_WINDOW_DAYS = 7;

    private const JUST_RELEASED_DAYS = 30;

    private const JUST_RELEASED_STALE_DAYS = 3;

    private const ESTABLISHED_STALE_DAYS = 30;

    /**
     * Fetch the Metacritic score for a Steam app from the storefront.
     *
     * @return array{score: int, url: string}|null
     */
    public function fetchMetacritic(int $appId): ?array
    {
        usleep(self::RATE_LIMIT_DELAY_MS);

        try {
            $response = Http::timeout(15)->get(self::APP_DETAILS_URL, [
                'appids' => $appId,
                'filters' => 'metacritic',
            ]);

            if ($response->failed()) {
                Log::warning('Steam appdetails request failed', [
                    'appid' => $appId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $metacritic = $response->json("{$appId}.data.metacritic");

            if (! is_array($metacritic) || ! isset($metacritic['score'])) {
                return null;
            }

            return [
                'score' => (int) $metacritic['score'],
                'url' => (string) ($metacritic['url'] ?? ''),
            ];
        } catch (\Exception $e) {
            Log::error('Steam appdetails exception', [
                'appid' => $appId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fetch the aggregate review summary for a Steam app.
     *
     * @return array{desc: string, percent: int, total: int, positive: int, negative: int}|null
     */
    public function fetchReviewSummary(int $appId): ?array
    {
        usleep(self::RATE_LIMIT_DELAY_MS);

        try {
            $response = Http::timeout(15)->get(self::APP_REVIEWS_URL."/{$appId}", [
                'json' => 1,
                'language' => 'all',
                'purchase_type' => 'all',
                'num_per_page' => 0,
            ]);

            if ($response->failed()) {
                Log::warning('Steam appreviews request failed', [
                    'appid' => $appId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $summary = $response->json('query_summary');

            if (! is_array($summary) || empty($summary['total_reviews'])) {
                return null;
            }

            $positive = (int) ($summary['total_positive'] ?? 0);
            $negative = (int) ($summary['total_negative'] ?? 0);
            $total = (int) $summary['total_reviews'];

            return [
                'desc' => (string) ($summary['review_score_desc'] ?? ''),
                'percent' => $total > 0 ? (int) round($positive / $total * 100) : 0,
                'total' => $total,
                'positive' => $positive,
                'negative' => $negative,
            ];
        } catch (\Exception $e) {
            Log::error('Steam appreviews exception', [
                'appid' => $appId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fetch and persist Metacritic + Steam review scores for a game.
     */
    public function syncScores(Game $game, int $steamAppId): bool
    {
        $metacritic = $this->fetchMetacritic($steamAppId);
        $reviews = $this->fetchReviewSummary($steamAppId);

        $game->update([
            'metacritic_score' => $metacritic['score'] ?? $game->metacritic_score,
            'metacritic_url' => $metacritic['url'] ?? $game->metacritic_url,
            'steam_review_percent' => $reviews['percent'] ?? $game->steam_review_percent,
            'steam_review_desc' => $reviews['desc'] ?? $game->steam_review_desc,
            'steam_review_total' => $reviews['total'] ?? $game->steam_review_total,
            'steam_review_positive' => $reviews['positive'] ?? $game->steam_review_positive,
            'steam_review_negative' => $reviews['negative'] ?? $game->steam_review_negative,
            'last_steam_review_sync_at' => now(),
        ]);

        Log::info('Steam store: synced review scores', [
            'game_id' => $game->id,
            'steam_app_id' => $steamAppId,
            'metacritic' => $metacritic['score'] ?? null,
            'steam_review_percent' => $reviews['percent'] ?? null,
        ]);

        return $metacritic !== null || $reviews !== null;
    }

    /**
     * Release-aware staleness check for a game's review scores.
     *
     * Far-future releases are skipped (no scores exist yet); games near
     * release are synced daily to catch the review-embargo lift.
     */
    public function reviewScoresAreStale(Game $game): bool
    {
        $releaseDate = $game->first_release_date;
        $lastSync = $game->last_steam_review_sync_at;
        $now = now();

        // Far future: no scores exist yet, don't waste Steam calls.
        if ($releaseDate && $releaseDate->gt($now->copy()->addDays(self::NEAR_RELEASE_WINDOW_DAYS))) {
            return false;
        }

        // Never synced (and not far-future) = always stale.
        if (! $lastSync) {
            return true;
        }

        $daysSinceSync = $lastSync->diffInDays($now);

        // Near release (within the window before/after): catch embargo lift daily.
        if ($releaseDate && $releaseDate->diffInDays($now) <= self::NEAR_RELEASE_WINDOW_DAYS) {
            return $daysSinceSync >= 1;
        }

        // Just released: scores/counts climb fast.
        if ($releaseDate && $releaseDate->lte($now) && $releaseDate->diffInDays($now) <= self::JUST_RELEASED_DAYS) {
            return $daysSinceSync >= self::JUST_RELEASED_STALE_DAYS;
        }

        // Established: scores are stable.
        return $daysSinceSync >= self::ESTABLISHED_STALE_DAYS;
    }
}
