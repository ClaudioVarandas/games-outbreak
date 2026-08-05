<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\GameTypeEnum;
use Carbon\Carbon;

class IgdbCandidate
{
    /**
     * Shared candidate JSON shape for the discovery/import CLI commands
     * (games:igdb-search, games:release-window).
     *
     * @param  array<string, mixed>  $game
     * @return array<string, mixed>
     */
    public static function format(array $game, bool $includeHypes = false): array
    {
        $firstReleaseDate = isset($game['first_release_date'])
            ? Carbon::createFromTimestampUTC((int) $game['first_release_date'])
            : null;

        $steamExternal = collect($game['external_games'] ?? [])
            ->first(function ($externalGame): bool {
                $source = is_array($externalGame) ? ($externalGame['external_game_source'] ?? null) : null;
                $sourceId = is_array($source) ? ($source['id'] ?? null) : $source;

                return (int) $sourceId === 1;
            });

        $candidate = [
            'igdb_id' => $game['id'],
            'name' => $game['name'] ?? null,
            'slug' => $game['slug'] ?? null,
            'game_type' => GameTypeEnum::tryFrom((int) ($game['game_type'] ?? 0))?->label(),
            'first_release_date' => $firstReleaseDate?->format('Y-m-d'),
            'release_year' => $firstReleaseDate?->year,
            'platforms' => collect($game['platforms'] ?? [])
                ->map(fn (array $platform): array => [
                    'igdb_id' => $platform['id'] ?? null,
                    'name' => $platform['name'] ?? null,
                ])
                ->values()
                ->all(),
            'release_dates' => collect($game['release_dates'] ?? [])
                ->map(fn (array $releaseDate): ?string => $releaseDate['human'] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'steam_app_id' => $steamExternal['uid'] ?? null,
            'summary' => isset($game['summary']) ? str($game['summary'])->limit(160)->toString() : null,
        ];

        if ($includeHypes) {
            $candidate['hypes'] = (int) ($game['hypes'] ?? 0);
        }

        return $candidate;
    }
}
