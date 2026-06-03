<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ListTypeEnum;
use App\Enums\PlatformEnum;
use App\Enums\PlatformGroupEnum;
use App\Models\Game;
use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\Support\Str;

class GameListSyncService
{
    public function findYearlyList(int $year): ?GameList
    {
        return GameList::yearly()
            ->where('is_system', true)
            ->whereYear('start_at', $year)
            ->first();
    }

    /**
     * Find the system yearly list for the year, creating it if absent.
     *
     * Note: this is a check-then-create within a single process; it is intended for
     * serial admin use (CLI commands / a single sync run). It is not safe against
     * concurrent callers without a surrounding transaction or unique constraint.
     */
    public function firstOrCreateYearlyList(int $year): GameList
    {
        if ($existing = $this->findYearlyList($year)) {
            return $existing;
        }

        $name = "Game Releases {$year}";

        return GameList::create([
            'user_id' => 1,
            'name' => $name,
            'description' => "Curated game releases for {$year}",
            'slug' => $this->uniqueYearlySlug($name),
            'is_public' => true,
            'is_system' => true,
            'is_active' => true,
            'list_type' => ListTypeEnum::YEARLY->value,
            'start_at' => Carbon::create($year, 1, 1)->startOfDay(),
            'end_at' => Carbon::create($year, 12, 31)->endOfDay(),
        ]);
    }

    /**
     * Decode pivot platform ids, falling back to the game's active platforms when empty.
     *
     * @return list<int>
     */
    public function resolvePlatforms(Game $game, mixed $pivotPlatforms): array
    {
        $platforms = $pivotPlatforms;

        if (is_string($platforms)) {
            $platforms = json_decode($platforms, true) ?? [];
        }

        if (! is_array($platforms) || empty($platforms)) {
            $game->loadMissing('platforms');
            $platforms = $game->platforms
                ->filter(fn ($p) => PlatformEnum::getActivePlatforms()->has($p->igdb_id))
                ->map(fn ($p) => $p->igdb_id)
                ->values()
                ->toArray();
        }

        return array_map('intval', $platforms);
    }

    /**
     * Attach a game to a list, computing order + platform_group and encoding json fields.
     *
     * @param  array{release_date?: mixed, platforms?: list<int>, is_tba?: bool, is_early_access?: bool, is_indie?: bool, is_highlight?: bool, genre_ids?: list<int>, primary_genre_id?: int|null, video_url?: string|null, release_year?: int|null}  $attrs
     */
    public function insertGame(GameList $list, Game $game, array $attrs): void
    {
        $platforms = array_map('intval', array_values($attrs['platforms'] ?? []));
        $maxOrder = $list->games()->max('order') ?? 0;

        $list->games()->attach($game->id, [
            'order' => $maxOrder + 1,
            'release_date' => $attrs['release_date'] ?? null,
            'platforms' => json_encode($platforms),
            'platform_group' => PlatformGroupEnum::suggestFromPlatforms($platforms)->value,
            'is_tba' => $attrs['is_tba'] ?? false,
            'is_early_access' => $attrs['is_early_access'] ?? false,
            'is_indie' => $attrs['is_indie'] ?? false,
            'is_highlight' => $attrs['is_highlight'] ?? false,
            'genre_ids' => json_encode(array_values($attrs['genre_ids'] ?? [])),
            'primary_genre_id' => $attrs['primary_genre_id'] ?? null,
            'video_url' => $attrs['video_url'] ?? null,
            'release_year' => $attrs['release_year'] ?? null,
        ]);
    }

    /**
     * Set only the empty fields on an existing pivot row. Returns the names of fields filled.
     *
     * Returns an empty array and writes nothing when the game is not in the list — callers
     * that may be inserting a new row must check membership and call insertGame() instead.
     *
     * @param  array{release_date?: mixed, platforms?: list<int>, video_url?: string|null}  $candidate
     * @return list<string>
     */
    public function fillMissing(GameList $list, Game $game, array $candidate): array
    {
        $pivot = $list->games()->where('games.id', $game->id)->first()?->pivot;

        if (! $pivot) {
            return [];
        }

        $update = [];
        $filled = [];

        if (empty($pivot->video_url) && ! empty($candidate['video_url'])) {
            $update['video_url'] = $candidate['video_url'];
            $filled[] = 'video_url';
        }

        if (empty($pivot->release_date) && ! empty($candidate['release_date'])) {
            $update['release_date'] = $candidate['release_date'];
            $filled[] = 'release_date';
        }

        $existingPlatforms = $pivot->platforms;
        if (is_string($existingPlatforms)) {
            $existingPlatforms = json_decode($existingPlatforms, true) ?? [];
        }
        if (empty($existingPlatforms) && ! empty($candidate['platforms'])) {
            $update['platforms'] = json_encode(array_values($candidate['platforms']));
            $filled[] = 'platforms';
        }

        if ($update) {
            $list->games()->updateExistingPivot($game->id, $update);
        }

        return $filled;
    }

    private function uniqueYearlySlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 1;

        while (GameList::where('slug', $slug)->where('list_type', ListTypeEnum::YEARLY->value)->exists()) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
