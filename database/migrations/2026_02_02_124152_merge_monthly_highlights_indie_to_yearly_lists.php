<?php

use App\Enums\PlatformGroupEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $yearExpr = $isSqlite ? "strftime('%Y', start_at)" : 'YEAR(start_at)';

        // Step 1: Get all years that have monthly lists
        $monthlyYears = DB::table('game_lists')
            ->where('list_type', 'monthly')
            ->where('is_system', true)
            ->whereNotNull('start_at')
            ->selectRaw("{$yearExpr} as year")
            ->groupBy(DB::raw($yearExpr))
            ->pluck('year');

        // Also include years from highlights and indie-games lists
        $highlightYears = DB::table('game_lists')
            ->where('list_type', 'highlights')
            ->where('is_system', true)
            ->whereNotNull('start_at')
            ->selectRaw("{$yearExpr} as year")
            ->groupBy(DB::raw($yearExpr))
            ->pluck('year');

        $indieYears = DB::table('game_lists')
            ->where('list_type', 'indie-games')
            ->where('is_system', true)
            ->whereNotNull('start_at')
            ->selectRaw("{$yearExpr} as year")
            ->groupBy(DB::raw($yearExpr))
            ->pluck('year');

        $allYears = $monthlyYears->merge($highlightYears)->merge($indieYears)->unique()->sort();

        foreach ($allYears as $year) {
            $startOfYear = "{$year}-01-01 00:00:00";
            $endOfYear = "{$year}-12-31 23:59:59";

            // Create the yearly list
            $yearlyListId = DB::table('game_lists')->insertGetId([
                'user_id' => null,
                'name' => "Game Releases {$year}",
                'slug' => (string) $year,
                'description' => null,
                'list_type' => 'yearly',
                'is_system' => true,
                'is_public' => true,
                'is_active' => true,
                'start_at' => $startOfYear,
                'end_at' => $endOfYear,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Collect all monthly list IDs for this year
            $monthlyListIds = DB::table('game_lists')
                ->where('list_type', 'monthly')
                ->where('is_system', true)
                ->whereYear('start_at', $year)
                ->pluck('id');

            // Collect all highlight list IDs for this year
            $highlightListIds = DB::table('game_lists')
                ->where('list_type', 'highlights')
                ->where('is_system', true)
                ->whereYear('start_at', $year)
                ->pluck('id');

            // Collect all indie list IDs for this year
            $indieListIds = DB::table('game_lists')
                ->where('list_type', 'indie-games')
                ->where('is_system', true)
                ->whereYear('start_at', $year)
                ->pluck('id');

            // Get all game entries from monthly lists
            $monthlyGames = DB::table('game_list_game')
                ->whereIn('game_list_id', $monthlyListIds)
                ->get()
                ->groupBy('game_id');

            // Get all game entries from highlights lists
            $highlightGames = DB::table('game_list_game')
                ->whereIn('game_list_id', $highlightListIds)
                ->get()
                ->keyBy('game_id');

            // Get all game entries from indie lists
            $indieGames = DB::table('game_list_game')
                ->whereIn('game_list_id', $indieListIds)
                ->get()
                ->keyBy('game_id');

            // Merge games: for duplicates, keep latest release_date, union platforms,
            // preserve is_highlight/is_indie if either has it, merge genre_ids
            $mergedGames = [];
            $order = 1;

            foreach ($monthlyGames as $gameId => $entries) {
                $latest = $entries->sortByDesc('release_date')->first();

                // Merge platforms from all entries
                $allPlatforms = [];
                foreach ($entries as $entry) {
                    $platforms = json_decode($entry->platforms ?? '[]', true) ?: [];
                    $allPlatforms = array_unique(array_merge($allPlatforms, $platforms));
                }

                // Check highlight status
                $isHighlight = $entries->contains(fn ($e) => (bool) $e->is_highlight);
                if (! $isHighlight && $highlightGames->has($gameId)) {
                    $isHighlight = true;
                }

                // Check indie status
                $isIndie = $entries->contains(fn ($e) => (bool) $e->is_indie);
                if (! $isIndie && $indieGames->has($gameId)) {
                    $isIndie = true;
                }

                // Merge genre_ids from all entries
                $allGenreIds = [];
                $primaryGenreId = null;
                foreach ($entries as $entry) {
                    $gIds = json_decode($entry->genre_ids ?? '[]', true) ?: [];
                    $allGenreIds = array_unique(array_merge($allGenreIds, $gIds));
                    if ($entry->primary_genre_id) {
                        $primaryGenreId = $entry->primary_genre_id;
                    }
                }

                // Also merge from highlight entry if exists
                if ($highlightGames->has($gameId)) {
                    $hEntry = $highlightGames->get($gameId);
                    $hGenres = json_decode($hEntry->genre_ids ?? '[]', true) ?: [];
                    $allGenreIds = array_unique(array_merge($allGenreIds, $hGenres));
                    if (! $primaryGenreId && $hEntry->primary_genre_id) {
                        $primaryGenreId = $hEntry->primary_genre_id;
                    }
                    $hPlatforms = json_decode($hEntry->platforms ?? '[]', true) ?: [];
                    $allPlatforms = array_unique(array_merge($allPlatforms, $hPlatforms));
                }

                // Also merge from indie entry if exists
                if ($indieGames->has($gameId)) {
                    $iEntry = $indieGames->get($gameId);
                    $iGenres = json_decode($iEntry->genre_ids ?? '[]', true) ?: [];
                    $allGenreIds = array_unique(array_merge($allGenreIds, $iGenres));
                    if (! $primaryGenreId && $iEntry->primary_genre_id) {
                        $primaryGenreId = $iEntry->primary_genre_id;
                    }
                    $iPlatforms = json_decode($iEntry->platforms ?? '[]', true) ?: [];
                    $allPlatforms = array_unique(array_merge($allPlatforms, $iPlatforms));
                }

                $platformGroup = PlatformGroupEnum::suggestFromPlatforms($allPlatforms)->value;

                $mergedGames[$gameId] = [
                    'game_list_id' => $yearlyListId,
                    'game_id' => $gameId,
                    'order' => $order++,
                    'release_date' => $latest->release_date,
                    'platforms' => json_encode(array_values(array_map('intval', $allPlatforms))),
                    'platform_group' => $platformGroup,
                    'is_highlight' => $isHighlight,
                    'is_tba' => (bool) $latest->is_tba,
                    'is_indie' => $isIndie,
                    'genre_ids' => json_encode(array_values(array_map('intval', $allGenreIds))),
                    'primary_genre_id' => $primaryGenreId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Add games that are only in highlights (not in monthly)
            foreach ($highlightGames as $gameId => $hEntry) {
                if (isset($mergedGames[$gameId])) {
                    continue;
                }

                $platforms = json_decode($hEntry->platforms ?? '[]', true) ?: [];
                $genreIds = json_decode($hEntry->genre_ids ?? '[]', true) ?: [];
                $platformGroup = PlatformGroupEnum::suggestFromPlatforms($platforms)->value;

                // Check indie too
                $isIndie = false;
                if ($indieGames->has($gameId)) {
                    $isIndie = true;
                    $iEntry = $indieGames->get($gameId);
                    $iPlatforms = json_decode($iEntry->platforms ?? '[]', true) ?: [];
                    $platforms = array_unique(array_merge($platforms, $iPlatforms));
                    $iGenres = json_decode($iEntry->genre_ids ?? '[]', true) ?: [];
                    $genreIds = array_unique(array_merge($genreIds, $iGenres));
                    $platformGroup = PlatformGroupEnum::suggestFromPlatforms($platforms)->value;
                }

                $mergedGames[$gameId] = [
                    'game_list_id' => $yearlyListId,
                    'game_id' => $gameId,
                    'order' => $order++,
                    'release_date' => $hEntry->release_date,
                    'platforms' => json_encode(array_values(array_map('intval', $platforms))),
                    'platform_group' => $platformGroup,
                    'is_highlight' => true,
                    'is_tba' => (bool) $hEntry->is_tba,
                    'is_indie' => $isIndie,
                    'genre_ids' => json_encode(array_values(array_map('intval', $genreIds))),
                    'primary_genre_id' => $hEntry->primary_genre_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Add games that are only in indie (not in monthly or highlights)
            foreach ($indieGames as $gameId => $iEntry) {
                if (isset($mergedGames[$gameId])) {
                    continue;
                }

                $platforms = json_decode($iEntry->platforms ?? '[]', true) ?: [];
                $genreIds = json_decode($iEntry->genre_ids ?? '[]', true) ?: [];
                $platformGroup = PlatformGroupEnum::suggestFromPlatforms($platforms)->value;

                $mergedGames[$gameId] = [
                    'game_list_id' => $yearlyListId,
                    'game_id' => $gameId,
                    'order' => $order++,
                    'release_date' => $iEntry->release_date,
                    'platforms' => json_encode(array_values(array_map('intval', $platforms))),
                    'platform_group' => $platformGroup,
                    'is_highlight' => false,
                    'is_tba' => (bool) $iEntry->is_tba,
                    'is_indie' => true,
                    'genre_ids' => json_encode(array_values(array_map('intval', $genreIds))),
                    'primary_genre_id' => $iEntry->primary_genre_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insert all merged games
            if (! empty($mergedGames)) {
                foreach (array_chunk(array_values($mergedGames), 500) as $chunk) {
                    DB::table('game_list_game')->insert($chunk);
                }
            }
        }

        // Step 2: Delete old pivot data for monthly, highlights, indie lists
        $oldListIds = DB::table('game_lists')
            ->where('is_system', true)
            ->whereIn('list_type', ['monthly', 'highlights', 'indie-games'])
            ->pluck('id');

        DB::table('game_list_game')->whereIn('game_list_id', $oldListIds)->delete();

        // Step 3: Delete old lists
        DB::table('game_lists')
            ->where('is_system', true)
            ->whereIn('list_type', ['monthly', 'highlights', 'indie-games'])
            ->delete();
    }

    public function down(): void
    {
        // Delete all yearly lists and their pivot data (irreversible data merge)
        $yearlyListIds = DB::table('game_lists')
            ->where('list_type', 'yearly')
            ->where('is_system', true)
            ->pluck('id');

        DB::table('game_list_game')->whereIn('game_list_id', $yearlyListIds)->delete();
        DB::table('game_lists')->whereIn('id', $yearlyListIds)->delete();
    }
};
