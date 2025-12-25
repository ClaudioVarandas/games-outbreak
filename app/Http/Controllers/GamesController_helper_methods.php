    private function searchByIgdbId(int $igdbId): JsonResponse
    {
        try {
            $igdbService = app(IgdbService::class);
            $game = Game::fetchFromIgdbIfMissing($igdbId, $igdbService);

            if (!$game) {
                return response()->json([]);
            }

            // Format to match existing API response structure
            $gameTypeEnum = $game->getGameTypeEnum();
            
            return response()->json([[
                'igdb_id' => $game->igdb_id,
                'name' => $game->name,
                'release' => $game->first_release_date
                    ? $game->first_release_date->format('d/m/Y')
                    : 'TBA',
                'cover_url' => $game->cover_image_id
                    ? $game->getCoverUrl('cover_small')
                    : 'https://via.placeholder.com/90x120/1f2937/6b7280?text=No+Cover',
                'platforms' => $game->platforms
                    ->filter(fn($p) => PlatformEnum::getActivePlatforms()->has($p->igdb_id))
                    ->sortBy(fn($p) => PlatformEnum::getPriority($p->igdb_id))
                    ->take(2)
                    ->map(fn($p) => PlatformEnum::fromIgdbId($p->igdb_id)?->label() ?? $p->name)
                    ->implode(', '),
                'game_type' => $game->game_type ?? 0,
                'game_type_label' => $gameTypeEnum->label(),
            ]]);
        } catch (\Exception $e) {
            \Log::error('IGDB ID search exception', [
                'igdb_id' => $igdbId,
                'error' => $e->getMessage()
            ]);
            return response()->json([]);
        }
    }

    private function searchResultsByIgdbId(int $igdbId, string $query, string $viewMode): View
    {
        try {
            $igdbService = app(IgdbService::class);
            $game = Game::fetchFromIgdbIfMissing($igdbId, $igdbService);

            if (!$game) {
                return view('search.results', [
                    'games' => collect(),
                    'query' => $query,
                    'viewMode' => $viewMode,
                    'totalResults' => 0,
                    'currentPage' => 1,
                    'totalPages' => 1,
                    'platformEnums' => PlatformEnum::getActivePlatforms(),
                ]);
            }

            // Create a paginated collection with single result
            $games = collect([$game]);
            
            return view('search.results', [
                'games' => $games,
                'query' => $query,
                'viewMode' => $viewMode,
                'totalResults' => 1,
                'currentPage' => 1,
                'totalPages' => 1,
                'hasMore' => false,
                'platformEnums' => PlatformEnum::getActivePlatforms(),
            ]);
        } catch (\Exception $e) {
            \Log::error('IGDB ID search results exception', [
                'igdb_id' => $igdbId,
                'error' => $e->getMessage()
            ]);
            
            return view('search.results', [
                'games' => collect(),
                'query' => $query,
                'viewMode' => $viewMode,
                'totalResults' => 0,
                'currentPage' => 1,
                'totalPages' => 1,
                'platformEnums' => PlatformEnum::getActivePlatforms(),
            ]);
        }
    }

