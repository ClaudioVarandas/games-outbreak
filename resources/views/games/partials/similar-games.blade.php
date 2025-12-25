@php
    $similarGames = collect();
    
    // Check if $game variable exists and has similar_games data
    if (isset($game) && $game && $game->similar_games) {
        $similarGamesData = is_array($game->similar_games) ? $game->similar_games : [];
        
        if (count($similarGamesData) > 0) {
            // Get IGDB service instance
            $igdbService = app(\App\Services\IgdbService::class);
            
            $similarGames = collect($similarGamesData)
                ->take(12)
                ->map(function ($similar) use ($igdbService) {
                    // Handle different possible structures of similar_games data
                    $igdbId = null;
                    if (is_array($similar)) {
                        $igdbId = $similar['id'] ?? null;
                    } elseif (is_object($similar)) {
                        $igdbId = $similar->id ?? null;
                    }
                    
                    if (!$igdbId) {
                        return null;
                    }
                    
                    // Try to find the game in the database, or fetch from IGDB if missing
                    $game = \App\Models\Game::fetchFromIgdbIfMissing($igdbId, $igdbService);
                    if ($game) {
                        $game->load('platforms');
                    }
                    return $game;
                })
                ->filter(); // Remove null values
        }
    }
    
    $platformEnums = isset($platformEnums) ? $platformEnums : \App\Enums\PlatformEnum::getActivePlatforms();
    
    $titleIcon = '<svg class="w-8 h-8 mr-3 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2m-8 0h8"/>
    </svg>';
@endphp

<x-game-carousel 
    :games="$similarGames"
    :platformEnums="$platformEnums"
    title="Similar Games"
    :titleIcon="$titleIcon"
    emptyMessage="No similar games available."
/>
