@props([
    'games',
    'platformEnums' => null,
    'emptyMessage' => 'No games available.',
    'initialLimit' => 10,
])

@php
    $platformEnums = $platformEnums ?? \App\Enums\PlatformEnum::getActivePlatforms();
    $allGames = $games ?? collect();
    $totalGames = $allGames->count();
    $initialGames = $allGames->take($initialLimit);
    $remainingGames = $allGames->skip($initialLimit);
@endphp

@if($totalGames > 0)
    <div id="featured-games-container">
        <div id="featured-games-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
            @foreach($initialGames as $index => $game)
                @php
                    // Convert pivot release_date string to Carbon instance if present
                    $displayDate = $game->first_release_date;
                    if (isset($game->pivot->release_date) && $game->pivot->release_date) {
                        $displayDate = \Carbon\Carbon::parse($game->pivot->release_date);
                    }
                @endphp
                <div class="featured-game-item" data-index="{{ $index }}">
                    <x-game-card
                        :game="$game"
                        variant="glassmorphism"
                        layout="overlay"
                        aspectRatio="3/4"
                        :platformEnums="$platformEnums"
                        :displayReleaseDate="$displayDate"
                        :displayPlatforms="isset($game->pivot) ? ($game->pivot->platforms ?? null) : null" />
                </div>
            @endforeach
        </div>
        
        @if($remainingGames->count() > 0)
            <div id="featured-games-hidden" style="display: none;">
                @foreach($remainingGames as $index => $game)
                    @php
                        // Convert pivot release_date string to Carbon instance if present
                        $displayDate = $game->first_release_date;
                        if (isset($game->pivot->release_date) && $game->pivot->release_date) {
                            $displayDate = \Carbon\Carbon::parse($game->pivot->release_date);
                        }
                    @endphp
                    <div class="featured-game-item" data-index="{{ $initialLimit + $index }}">
                        <x-game-card
                            :game="$game"
                            variant="glassmorphism"
                            layout="overlay"
                            aspectRatio="3/4"
                            :platformEnums="$platformEnums"
                            :displayReleaseDate="$displayDate"
                            :displayPlatforms="isset($game->pivot) ? ($game->pivot->platforms ?? null) : null" />
                    </div>
                @endforeach
            </div>
            
            <div class="text-center mt-8">
                <button id="load-more-games" 
                        class="px-6 py-3 bg-orange-600 hover:bg-orange-700 text-white font-semibold rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 backdrop-blur-sm border border-white/20">
                    Load More ({{ $remainingGames->count() }} remaining)
                </button>
            </div>
        @endif
    </div>
@else
    <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
        <p class="text-lg text-gray-600 dark:text-gray-400">
            {{ $emptyMessage }}
        </p>
    </div>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const loadMoreBtn = document.getElementById('load-more-games');
    const hiddenGames = document.getElementById('featured-games-hidden');
    const gamesGrid = document.getElementById('featured-games-grid');
    
    if (loadMoreBtn && hiddenGames && gamesGrid) {
        const gamesPerLoad = 10;
        let currentIndex = 0;
        const allHiddenGames = Array.from(hiddenGames.querySelectorAll('.featured-game-item'));
        const totalHidden = allHiddenGames.length;
        
        loadMoreBtn.addEventListener('click', function() {
            const gamesToShow = allHiddenGames.slice(currentIndex, currentIndex + gamesPerLoad);
            
            gamesToShow.forEach(function(gameElement) {
                gamesGrid.appendChild(gameElement);
            });
            
            currentIndex += gamesPerLoad;
            
            if (currentIndex >= totalHidden) {
                loadMoreBtn.style.display = 'none';
            } else {
                loadMoreBtn.textContent = `Load More (${totalHidden - currentIndex} remaining)`;
            }
        });
    }
});
</script>
@endpush

