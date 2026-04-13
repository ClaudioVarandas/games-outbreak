@php
    $similarGames = collect();

    if (isset($game) && $game && $game->similar_games) {
        $similarGamesData = is_array($game->similar_games) ? $game->similar_games : [];

        if (count($similarGamesData) > 0) {
            $igdbService = app(\App\Services\IgdbService::class);

            $similarGames = collect($similarGamesData)
                ->take(12)
                ->map(function ($similar) use ($igdbService) {
                    $igdbId = is_array($similar) ? ($similar['id'] ?? null) : ($similar->id ?? null);

                    if (!$igdbId) {
                        return null;
                    }

                    $game = \App\Models\Game::fetchFromIgdbIfMissing($igdbId, $igdbService);
                    if ($game) {
                        $game->load('platforms');
                    }
                    return $game;
                })
                ->filter();
        }
    }

    $platformEnums = isset($platformEnums) ? $platformEnums : \App\Enums\PlatformEnum::getActivePlatforms();
@endphp

@if($similarGames->isNotEmpty())
    <section class="neon-section-frame">
        <x-homepage.section-heading icon="sparkles" title="Similar Games" />
        <x-game-carousel
            :games="$similarGames"
            :platformEnums="$platformEnums"
            variant="neon"
            emptyMessage="No similar games available." />
    </section>
@endif
