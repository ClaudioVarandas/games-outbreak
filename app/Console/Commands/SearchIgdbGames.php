<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\GameTypeEnum;
use App\Services\IgdbService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SearchIgdbGames extends Command
{
    protected $signature = 'games:igdb-search
                            {name : Game name to search for}
                            {--limit=5 : Maximum number of candidates}
                            {--year= : Expected release year, candidates matching it are ranked first}';

    protected $description = 'Search IGDB games by name and print matching candidates as JSON (used by the list-import skill)';

    public function handle(IgdbService $igdbService): int
    {
        $name = (string) $this->argument('name');
        $limit = max(1, (int) $this->option('limit'));
        $expectedYear = $this->option('year') !== null ? (int) $this->option('year') : null;

        $candidates = collect($igdbService->searchGames($name, $limit))
            ->map(fn (array $game): array => $this->formatCandidate($game));

        if ($expectedYear !== null) {
            $candidates = $candidates
                ->sortBy(fn (array $candidate): int => $candidate['release_year'] === $expectedYear ? 0 : 1)
                ->values();
        }

        $this->line((string) json_encode([
            'query' => $name,
            'candidates' => $candidates->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $game
     * @return array<string, mixed>
     */
    private function formatCandidate(array $game): array
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

        return [
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
    }
}
