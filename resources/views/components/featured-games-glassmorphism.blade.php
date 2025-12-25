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
                $coverUrl = $game->cover_image_id
                    ? $game->getCoverUrl('cover_big')
                    : ($game->steam_data['header_image'] ?? null);
                $linkUrl = route('game.show', $game);
                
                $validPlatformIds = $platformEnums->keys()->toArray();
                $filteredPlatforms = $game->platforms 
                    ? $game->platforms->filter(fn($p) => in_array($p->igdb_id, $validPlatformIds))
                    : collect();
                
                $sortedPlatforms = $filteredPlatforms->sortBy(function($platform) {
                    return \App\Enums\PlatformEnum::getPriority($platform->igdb_id);
                })->values();
                
                // Get user's backlog and wishlist lists for quick actions
                $backlogList = auth()->check() ? auth()->user()->gameLists()->backlog()->with('games')->first() : null;
                $wishlistList = auth()->check() ? auth()->user()->gameLists()->wishlist()->with('games')->first() : null;
            @endphp

                <a href="{{ $linkUrl }}" class="group block featured-game-item" data-index="{{ $index }}">
                    <div class="relative aspect-[3/4] rounded-xl overflow-hidden backdrop-blur-md bg-white/10 dark:bg-white/5 border border-white/20 shadow-xl hover:shadow-2xl transition-all duration-300 hover:bg-white/20 dark:hover:bg-white/10 hover:backdrop-blur-lg group/card">
                        @if($coverUrl)
                            <img src="{{ $coverUrl }}"
                                 alt="{{ $game->name }}"
                                 class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500 opacity-80 group-hover:opacity-100"
                                 onerror="this.onerror=null; this.replaceWith(this.nextElementSibling);">
                            <div class="w-full h-full bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center opacity-50" style="display: none;">
                                <x-game-cover-placeholder :gameName="$game->name" class="w-full h-full" />
                            </div>
                        @else
                            <div class="w-full h-full bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center opacity-50">
                                <x-game-cover-placeholder :gameName="$game->name" class="w-full h-full" />
                            </div>
                        @endif
                        
                        <!-- Glass Overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                        
                        @auth
                            <x-game-quick-actions 
                                :game="$game" 
                                :backlogList="$backlogList" 
                                :wishlistList="$wishlistList" />
                        @endauth
                        
                        <!-- Platform Badges with Glass Effect -->
                        @if($sortedPlatforms->count() > 0)
                            <div class="absolute top-2 left-2 flex flex-wrap gap-1 z-10">
                                @foreach($sortedPlatforms as $platform)
                                    @php
                                        $enum = $platformEnums[$platform->igdb_id] ?? null;
                                    @endphp
                                    <span class="px-2 py-1 text-xs font-bold text-white rounded shadow-lg backdrop-blur-sm bg-black/40 border border-white/20
                                        @if($enum)
                                            bg-{{ $enum->color() }}-600/80
                                        @else
                                            bg-gray-600/80
                                        @endif">
                                        {{ $enum?->label() ?? \Illuminate\Support\Str::limit($platform->name, 6) }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                        
                        <!-- Game Info on Glass Surface -->
                        <div class="absolute bottom-0 left-0 right-0 p-4 z-10 backdrop-blur-sm bg-black/30 border-t border-white/20">
                            <h3 class="font-bold text-lg text-white mb-2 line-clamp-2 group-hover:text-orange-400 transition-colors">
                                {{ $game->name }}
                            </h3>
                            
                            @if($game->first_release_date)
                                <p class="text-sm text-gray-200 mb-2">
                                    {{ $game->first_release_date->format('d/m/Y') }}
                                </p>
                            @else
                                <p class="text-sm text-gray-200 mb-2">TBA</p>
                            @endif
                            
                            <div class="mt-2">
                                <span class="{{ $game->getGameTypeEnum()->colorClass() }} px-2 py-0.5 text-xs font-medium rounded text-white backdrop-blur-sm bg-black/40 border border-white/20">
                                    {{ $game->getGameTypeEnum()->label() }}
                                </span>
                            </div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        
        @if($remainingGames->count() > 0)
            <div id="featured-games-hidden" style="display: none;">
                @foreach($remainingGames as $index => $game)
                    @php
                        $coverUrl = $game->cover_image_id
                            ? $game->getCoverUrl('cover_big')
                            : ($game->steam_data['header_image'] ?? null);
                        $linkUrl = route('game.show', $game);
                        
                        $validPlatformIds = $platformEnums->keys()->toArray();
                        $filteredPlatforms = $game->platforms 
                            ? $game->platforms->filter(fn($p) => in_array($p->igdb_id, $validPlatformIds))
                            : collect();
                        
                        $sortedPlatforms = $filteredPlatforms->sortBy(function($platform) {
                            return \App\Enums\PlatformEnum::getPriority($platform->igdb_id);
                        })->values();
                        
                        // Get user's backlog and wishlist lists for quick actions
                        $backlogList = auth()->check() ? auth()->user()->gameLists()->backlog()->with('games')->first() : null;
                        $wishlistList = auth()->check() ? auth()->user()->gameLists()->wishlist()->with('games')->first() : null;
                    @endphp
                    <a href="{{ $linkUrl }}" class="group block featured-game-item" data-index="{{ $initialLimit + $index }}">
                        <div class="relative aspect-[3/4] rounded-xl overflow-hidden backdrop-blur-md bg-white/10 dark:bg-white/5 border border-white/20 shadow-xl hover:shadow-2xl transition-all duration-300 hover:bg-white/20 dark:hover:bg-white/10 hover:backdrop-blur-lg group/card">
                            @if($coverUrl)
                                <img src="{{ $coverUrl }}"
                                     alt="{{ $game->name }}"
                                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500 opacity-80 group-hover:opacity-100"
                                     onerror="this.onerror=null; this.replaceWith(this.nextElementSibling);">
                                <div class="w-full h-full bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center opacity-50" style="display: none;">
                                    <x-game-cover-placeholder :gameName="$game->name" class="w-full h-full" />
                                </div>
                            @else
                                <div class="w-full h-full bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center opacity-50">
                                    <x-game-cover-placeholder :gameName="$game->name" class="w-full h-full" />
                                </div>
                            @endif
                            
                            <!-- Glass Overlay -->
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                            
                            @auth
                                <x-game-quick-actions 
                                    :game="$game" 
                                    :backlogList="$backlogList" 
                                    :wishlistList="$wishlistList" />
                            @endauth
                            
                            <!-- Platform Badges with Glass Effect -->
                            @if($sortedPlatforms->count() > 0)
                                <div class="absolute top-2 left-2 flex flex-wrap gap-1 z-10">
                                    @foreach($sortedPlatforms as $platform)
                                        @php
                                            $enum = $platformEnums[$platform->igdb_id] ?? null;
                                        @endphp
                                        <span class="px-2 py-1 text-xs font-bold text-white rounded shadow-lg backdrop-blur-sm bg-black/40 border border-white/20
                                            @if($enum)
                                                bg-{{ $enum->color() }}-600/80
                                            @else
                                                bg-gray-600/80
                                            @endif">
                                            {{ $enum?->label() ?? \Illuminate\Support\Str::limit($platform->name, 6) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                            
                            <!-- Game Info on Glass Surface -->
                            <div class="absolute bottom-0 left-0 right-0 p-4 z-10 backdrop-blur-sm bg-black/30 border-t border-white/20">
                                <h3 class="font-bold text-lg text-white mb-2 line-clamp-2 group-hover:text-orange-400 transition-colors">
                                    {{ $game->name }}
                                </h3>
                                
                                @if($game->first_release_date)
                                    <p class="text-sm text-gray-200 mb-2">
                                        {{ $game->first_release_date->format('d/m/Y') }}
                                    </p>
                                @else
                                    <p class="text-sm text-gray-200 mb-2">TBA</p>
                                @endif
                                
                                <div class="mt-2">
                                    <span class="{{ $game->getGameTypeEnum()->colorClass() }} px-2 py-0.5 text-xs font-medium rounded text-white backdrop-blur-sm bg-black/40 border border-white/20">
                                        {{ $game->getGameTypeEnum()->label() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
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

