<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\GameListAttachResult;
use App\Enums\GameListAttachStatusEnum;
use App\Enums\ListTypeEnum;
use App\Enums\PlatformEnum;
use App\Enums\PlatformGroupEnum;
use App\Models\Game;
use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class GameListImportService
{
    public function __construct(private readonly IgdbService $igdbService) {}

    /**
     * The hidden staging list that quarantines imports for a target list until
     * an admin promotes them. Created on first use.
     */
    public function stagingListFor(GameList $target): GameList
    {
        return GameList::firstOrCreate(
            [
                'list_type' => ListTypeEnum::IMPORT,
                'import_target_list_id' => $target->id,
            ],
            [
                'name' => 'Import: '.$target->name,
                'slug' => $target->slug.'-import',
                'user_id' => $target->user_id,
                'is_system' => true,
                'is_public' => false,
                'is_active' => false,
                'start_at' => $target->start_at,
            ]
        );
    }

    /**
     * Attach a game (fetched from IGDB when missing locally) to a list with pivot data.
     *
     * @param array{
     *     release_date?: Carbon|string|null,
     *     platforms?: list<int>,
     *     platform_group?: string|null,
     *     is_tba?: bool,
     *     is_early_access?: bool,
     *     genre_ids?: list<int>,
     *     primary_genre_id?: int|null,
     *     video_url?: string|null,
     *     release_year?: int|null,
     *     import_confidence?: string|null,
     *     import_sources?: list<string>|null,
     *     import_note?: string|null,
     * } $attributes
     *
     * @throws ValidationException When the early-access/TBA/date combination is invalid.
     */
    public function attachGame(GameList $list, int|string $igdbId, array $attributes = []): GameListAttachResult
    {
        $game = Game::where('igdb_id', $igdbId)->first();

        if (! $game && is_numeric($igdbId)) {
            $game = Game::fetchFromIgdbIfMissing((int) $igdbId, $this->igdbService);
        }

        if (! $game) {
            return new GameListAttachResult(GameListAttachStatusEnum::GameNotFound);
        }

        if ($list->games()->where('games.id', $game->id)->exists()) {
            return new GameListAttachResult(GameListAttachStatusEnum::AlreadyOnList, $game);
        }

        $releaseDate = $this->resolveReleaseDate($game, $attributes['release_date'] ?? null);
        $platformIds = $this->resolvePlatformIds($game, $attributes['platforms'] ?? []);

        $platformGroup = $attributes['platform_group'] ?? null;
        if ($list->isYearly() && ! $platformGroup) {
            $platformGroup = PlatformGroupEnum::suggestFromPlatforms($platformIds)->value;
        }

        $isTba = (bool) ($attributes['is_tba'] ?? false);
        $isEarlyAccess = (bool) ($attributes['is_early_access'] ?? false);
        if ($isTba) {
            $releaseDate = null;
        }
        self::guardReleaseState($isEarlyAccess, $isTba, $releaseDate);

        $genreIds = $attributes['genre_ids'] ?? [];
        $primaryGenreId = $attributes['primary_genre_id'] ?? null;
        $releaseYear = $attributes['release_year'] ?? null;

        $list->games()->attach($game->id, [
            'order' => ($list->games()->max('order') ?? 0) + 1,
            'release_date' => $releaseDate,
            'platforms' => json_encode($platformIds),
            'platform_group' => $platformGroup,
            'is_tba' => $isTba,
            'is_early_access' => $isEarlyAccess,
            'genre_ids' => json_encode(array_map('intval', $genreIds)),
            'primary_genre_id' => $primaryGenreId ? (int) $primaryGenreId : null,
            'video_url' => ($attributes['video_url'] ?? null) ?: null,
            'release_year' => $isTba ? ($releaseYear ?: null) : null,
            'import_confidence' => $attributes['import_confidence'] ?? null,
            'import_sources' => isset($attributes['import_sources']) ? json_encode($attributes['import_sources']) : null,
            'import_note' => $attributes['import_note'] ?? null,
        ]);

        return new GameListAttachResult(GameListAttachStatusEnum::Attached, $game);
    }

    /**
     * Promote reviewed games off a staging list into their yearly lists.
     *
     * Routing, dedupe and auto-creation of missing year lists are delegated to
     * EventYearlySyncService::apply(); every game it processed without an error
     * (inserted, filled, or skipped as an exact duplicate) leaves the staging list.
     *
     * @param  list<int>  $gameIds
     * @return array{created_years: list<int>, inserted: int, filled: array<int, list<string>>, skipped: int, errors: array<int, string>, per_year: array<int, int>, detached: int}
     */
    public function promoteFromStaging(GameList $staging, array $gameIds, EventYearlySyncService $syncService): array
    {
        if ($staging->list_type !== ListTypeEnum::IMPORT) {
            throw new \InvalidArgumentException('Only import staging lists can be promoted.');
        }

        $gameIds = $staging->games()->whereIn('games.id', $gameIds)->pluck('games.id')->all();

        $result = $syncService->apply($staging, $gameIds);

        $promotedIds = array_values(array_diff($gameIds, array_keys($result['errors'])));

        if ($promotedIds !== []) {
            $staging->games()->detach($promotedIds);
        }

        $result['detached'] = count($promotedIds);

        return $result;
    }

    /**
     * Reject staged games: detach them from the staging list without touching
     * any real list. Returns the number of games removed.
     *
     * @param  list<int>  $gameIds
     */
    public function rejectFromStaging(GameList $staging, array $gameIds): int
    {
        if ($staging->list_type !== ListTypeEnum::IMPORT) {
            throw new \InvalidArgumentException('Only import staging lists can be rejected from.');
        }

        $gameIds = $staging->games()->whereIn('games.id', $gameIds)->pluck('games.id')->all();

        if ($gameIds === []) {
            return 0;
        }

        return $staging->games()->detach($gameIds);
    }

    /**
     * @throws ValidationException
     */
    public static function guardReleaseState(bool $isEarlyAccess, bool $isTba, mixed $releaseDate): void
    {
        if ($isEarlyAccess && $isTba) {
            throw ValidationException::withMessages([
                'is_early_access' => 'A game cannot be both Early Access and TBA.',
            ]);
        }

        if ($isEarlyAccess && empty($releaseDate)) {
            throw ValidationException::withMessages([
                'release_date' => 'Early Access requires a release date.',
            ]);
        }
    }

    private function resolveReleaseDate(Game $game, Carbon|string|null $releaseDate): ?Carbon
    {
        if ($releaseDate instanceof Carbon) {
            return $releaseDate;
        }

        if ($releaseDate) {
            try {
                return Carbon::parse($releaseDate);
            } catch (\Exception) {
                return $game->first_release_date;
            }
        }

        return $game->first_release_date;
    }

    /**
     * @param  list<int>  $platformIds
     * @return list<int>
     */
    private function resolvePlatformIds(Game $game, array $platformIds): array
    {
        if ($platformIds !== []) {
            return array_values(array_map('intval', $platformIds));
        }

        $game->load('platforms');

        return $game->platforms
            ->filter(fn ($platform) => PlatformEnum::getActivePlatforms()->has($platform->igdb_id))
            ->map(fn ($platform) => $platform->igdb_id)
            ->values()
            ->toArray();
    }
}
