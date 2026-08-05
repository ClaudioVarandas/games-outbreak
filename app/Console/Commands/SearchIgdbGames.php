<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\IgdbService;
use App\Support\IgdbCandidate;
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
            ->map(fn (array $game): array => IgdbCandidate::format($game));

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
}
