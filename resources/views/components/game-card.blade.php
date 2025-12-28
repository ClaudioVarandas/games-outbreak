@props([
    'game',
    'variant' => 'simple', // 'glassmorphism', 'simple', 'carousel', 'overlay'
    'layout' => 'below', // 'overlay' (info on image) or 'below' (info below image)
    'aspectRatio' => '3/4', // '3/4' or 'video'
    'showRank' => false,
    'rank' => null,
    'rankColor' => null,
    'showRemoveButton' => false,
    'removeRoute' => null,
    'wantedScore' => null,
    'platformEnums' => null,
    'backlogList' => null,
    'wishlistList' => null,
    'carousel' => false, // Whether this card is used in a carousel context
    'displayReleaseDate' => null, // Optional: overrides game->first_release_date
])

@php
    $platformEnums = $platformEnums ?? \App\Enums\PlatformEnum::getActivePlatforms();
    
    // Get backlog and wishlist lists if not provided
    if (!$backlogList && auth()->check()) {
        $backlogList = auth()->user()->gameLists()->backlog()->with('games')->first();
    }
    if (!$wishlistList && auth()->check()) {
        $wishlistList = auth()->user()->gameLists()->wishlist()->with('games')->first();
    }
    
    // Determine which release date to display
    $releaseDate = $displayReleaseDate ?? $game->first_release_date;
    
    $coverUrl = $game->cover_image_id
        ? $game->getCoverUrl('cover_big')
        : ($game->steam_data['header_image'] ?? null);
    $linkUrl = route('game.show', $game);
    
    // Platform badges logic
    $validPlatformIds = $platformEnums->keys()->toArray();
    $filteredPlatforms = $game->platforms 
        ? $game->platforms->filter(fn($p) => in_array($p->igdb_id, $validPlatformIds))
        : collect();
    
    $sortedPlatforms = $filteredPlatforms->sortBy(function($platform) {
        return \App\Enums\PlatformEnum::getPriority($platform->igdb_id);
    })->values();
    
    // Aspect ratio class
    $aspectClass = $aspectRatio === 'video' ? 'aspect-video' : 'aspect-[3/4]';
    
    // Variant-specific classes
    $blurIntensity = $carousel ? 'backdrop-blur-sm' : 'backdrop-blur-md';
    $containerClasses = match($variant) {
        'glassmorphism' => 'relative ' . $aspectClass . ' rounded-xl overflow-hidden ' . $blurIntensity . ' bg-white/10 dark:bg-white/5 border border-white/20 shadow-xl hover:shadow-2xl transition-all duration-300 hover:bg-white/20 dark:hover:bg-white/10',
        'carousel' => 'bg-gray-800 dark:bg-gray-800 rounded-xl overflow-hidden shadow-lg hover:shadow-2xl transition-all',
        'overlay' => 'group relative bg-gray-800 dark:bg-gray-800 rounded-xl overflow-hidden shadow-xl hover:shadow-2xl transition-all duration-400',
        'simple' => ($wantedScore !== null ? 'bg-gray-800 rounded-lg' : 'bg-white dark:bg-gray-800') . ' rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow duration-300' . ($wantedScore !== null ? ' cursor-pointer group' : ''),
        default => 'bg-gray-800 dark:bg-gray-800 rounded-xl overflow-hidden shadow-xl hover:shadow-2xl transition-all',
    };
    
    $imageContainerClasses = match($variant) {
        'glassmorphism' => 'relative ' . $aspectClass . ' rounded-xl overflow-hidden',
        'carousel' => $aspectClass . ' relative overflow-hidden',
        'overlay' => 'relative ' . $aspectClass . ' bg-gray-200 dark:bg-gray-700 overflow-hidden',
        'simple' => 'relative ' . $aspectClass . ' bg-gray-200 dark:bg-gray-700',
        default => 'relative ' . $aspectClass . ' bg-gray-200 dark:bg-gray-700 overflow-hidden',
    };
    
    $imageClasses = match($variant) {
        'glassmorphism' => 'w-full h-full object-cover group-hover:scale-110 transition-transform duration-500 opacity-80 group-hover:opacity-100',
        'carousel' => 'w-full h-full object-cover group-hover/card:scale-110 transition-transform duration-500',
        'overlay' => 'w-full h-full object-cover group-hover:scale-110 transition-transform duration-500',
        'simple' => 'w-full h-full object-cover',
        default => 'w-full h-full object-cover group-hover:scale-110 transition-transform duration-500',
    };
    
    $platformBadgeClasses = match($variant) {
        'glassmorphism' => 'px-2 py-1 text-xs font-bold text-white rounded shadow-lg backdrop-blur-sm bg-black/40 border border-white/20',
        default => 'px-2 py-1 text-xs font-bold text-white rounded shadow-lg',
    };
@endphp

<a href="{{ $linkUrl }}" class="group block {{ ($variant === 'carousel' || $carousel) ? 'flex-shrink-0 w-64 transition-all duration-300 hover:z-30' : '' }}">
    <div class="{{ $containerClasses }}">
        <!-- Cover Image Container -->
        <div class="{{ $imageContainerClasses }} group/card">
            @if($coverUrl)
                <img src="{{ $coverUrl }}"
                     alt="{{ $game->name }} cover"
                     class="{{ $imageClasses }}"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <x-game-cover-placeholder :gameName="$game->name" class="w-full h-full" style="display: none;" />
            @else
                <x-game-cover-placeholder :gameName="$game->name" class="w-full h-full" />
            @endif
            
            <!-- Rank Badge (Most Wanted) -->
            @if($showRank && $rank !== null)
                <div class="absolute top-0 left-0 {{ $rankColor ?? 'bg-orange-500' }} text-gray-900 text-sm font-black px-3 py-1 rounded-br-lg z-20 shadow-lg">
                    #{{ $rank }}
                </div>
            @endif
            
            <!-- Platform Badges -->
            @if($sortedPlatforms->count() > 0)
                <div class="absolute top-2 left-2 flex flex-wrap gap-1 z-10">
                    @foreach($sortedPlatforms as $platform)
                        @php
                            $enum = $platformEnums[$platform->igdb_id] ?? null;
                        @endphp
                        <span class="{{ $platformBadgeClasses }}
                            @if($enum)
                                bg-{{ $enum->color() }}-600{{ $variant === 'glassmorphism' ? '/80' : '' }}
                            @else
                                bg-gray-600{{ $variant === 'glassmorphism' ? '/80' : '' }}
                            @endif">
                            {{ $enum?->label() ?? \Illuminate\Support\Str::limit($platform->name, $variant === 'simple' ? 8 : 6) }}
                        </span>
                    @endforeach
                </div>
            @endif
            
            <!-- Quick Actions -->
            <x-game-quick-actions 
                :game="$game" 
                :backlogList="$backlogList" 
                :wishlistList="$wishlistList" />
            
            <!-- Remove Button (Lists Show) -->
            @if($showRemoveButton && $removeRoute)
                <form action="{{ $removeRoute }}" 
                      method="POST" 
                      class="absolute top-2 right-2 z-20"
                      onsubmit="return confirm('Remove this game from the list?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white p-2 rounded-full shadow-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </form>
            @endif
            
            <!-- Overlay for glassmorphism variant -->
            @if($variant === 'glassmorphism')
                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
            @endif
            
            <!-- Overlay for carousel variant -->
            @if($variant === 'carousel')
                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent opacity-0 group-hover/card:opacity-100 transition-opacity"></div>
            @endif
            
            <!-- Info Overlay (for overlay layout) -->
            @if($layout === 'overlay' && $variant !== 'glassmorphism')
                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/90 via-black/70 to-transparent px-4 pt-20 pb-4 opacity-100 translate-y-0 transition-all duration-400 ease-out">
                    <h3 class="text-md font-bold text-white mb-2 leading-tight line-clamp-2">
                        {{ $game->name }}
                    </h3>
                    @if($releaseDate)
                        <p class="text-md font-semibold text-cyan-300 dark:text-cyan-400 mb-2">
                            {{ $releaseDate->format('d/m/Y') }}
                        </p>
                    @endif
                    @if(true)
                        <div>
                            <span class="{{ $game->getGameTypeEnum()->colorClass() }} px-2 py-0.5 text-xs font-medium rounded text-white">
                                {{ $game->getGameTypeEnum()->label() }}
                            </span>
                        </div>
                    @endif
                </div>
            @endif
            
            <!-- Info Overlay for glassmorphism variant -->
            @if($variant === 'glassmorphism')
                <div class="absolute bottom-0 left-0 right-0 p-4 z-10 backdrop-blur-sm bg-black/30 border-t border-white/20">
                    @if($wantedScore !== null)
                        <div class="h-2.5 w-full bg-gray-700/50 rounded-full mb-3 overflow-hidden backdrop-blur-sm">
                            <div class="h-full bg-red-600 transition-all" style="width: {{ $wantedScore }}%;"></div>
                        </div>
                        <p class="text-xs text-gray-300 mb-2">Wanted Score: <span class="text-red-400 font-bold">{{ $wantedScore }}%</span></p>
                    @endif
                    
                    <h3 class="font-bold text-lg text-white mb-2 line-clamp-2 group-hover:text-orange-400 transition-colors">
                        {{ $game->name }}
                    </h3>
                    
                    @if($releaseDate)
                        <p class="text-sm text-gray-200 mb-2">
                            {{ $releaseDate->format('d/m/Y') }}
                        </p>
                    @else
                        <p class="text-sm text-gray-200 mb-2">TBA</p>
                    @endif
                    
                    @if($wantedScore !== null)
                        <div class="flex justify-between items-center text-sm text-gray-300 mb-2">
                            <span class="flex items-center">
                                <svg class="w-4 h-4 mr-1 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zM4 9h12v7a1 1 0 01-1 1H5a1 1 0 01-1-1V9z" fill-rule="evenodd" clip-rule="evenodd"></path>
                                </svg>
                                {{ $releaseDate?->format('d/m/Y') ?? 'TBA' }}
                            </span>
                            <span class="text-xs bg-gray-700/50 backdrop-blur-sm px-2 py-0.5 rounded-full">
                                {{ $game->genres->first()?->name ?? 'N/A' }}
                            </span>
                        </div>
                    @endif
                    
                    <div class="mt-2">
                        <span class="{{ $game->getGameTypeEnum()->colorClass() }} px-2 py-0.5 text-xs font-medium rounded text-white backdrop-blur-sm bg-black/40 border border-white/20">
                            {{ $game->getGameTypeEnum()->label() }}
                        </span>
                    </div>
                </div>
            @endif
        </div>
        
        <!-- Info Below Image (for below layout) -->
        @if($layout === 'below' && $variant !== 'glassmorphism')
            <div class="p-4 {{ $variant === 'simple' ? '' : 'relative' }}">
                @if($wantedScore !== null)
                    <div class="h-2.5 w-full bg-gray-700 rounded-full mb-3 overflow-hidden">
                        <div class="h-full bg-red-600 transition-all" style="width: {{ $wantedScore }}%;"></div>
                    </div>
                    <p class="text-xs text-gray-400 mb-1">Wanted Score: <span class="text-red-400 font-bold">{{ $wantedScore }}%</span></p>
                @endif
                
                <h3 class="font-bold {{ $variant === 'carousel' ? 'text-lg' : ($variant === 'simple' ? 'text-lg' : 'text-lg') }} {{ $variant === 'carousel' ? 'text-white truncate group-hover/card:text-orange-400' : ($variant === 'simple' ? ($wantedScore !== null ? 'text-white truncate mb-2' : 'text-gray-900 dark:text-white truncate mb-2') : 'text-white truncate mb-2') }}">
                    {{ $game->name }}
                </h3>
                
                @if($releaseDate)
                    <p class="text-sm {{ $variant === 'carousel' ? 'text-gray-400' : ($variant === 'simple' ? ($wantedScore !== null ? 'text-gray-400 mb-2' : 'text-gray-600 dark:text-gray-400 mb-3') : 'text-gray-400 mb-2') }} mt-1">
                        {{ $releaseDate->format('d/m/Y') }}
                    </p>
                @else
                    <p class="text-sm {{ $variant === 'carousel' ? 'text-gray-400' : ($variant === 'simple' ? ($wantedScore !== null ? 'text-gray-400 mb-2' : 'text-gray-600 dark:text-gray-400 mb-3') : 'text-gray-400 mb-2') }} mt-1">TBA</p>
                @endif
                
                @if($wantedScore !== null)
                    <div class="flex justify-between items-center text-sm text-gray-400 mb-2">
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-1 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zM4 9h12v7a1 1 0 01-1 1H5a1 1 0 01-1-1V9z" fill-rule="evenodd" clip-rule="evenodd"></path>
                            </svg>
                            {{ $releaseDate?->format('d/m/Y') ?? 'TBA' }}
                        </span>
                        <span class="text-xs bg-gray-700 px-2 py-0.5 rounded-full">
                            {{ $game->genres->first()?->name ?? 'N/A' }}
                        </span>
                    </div>
                @endif
                
                @if(true)
                    <div class="{{ $wantedScore !== null ? 'mt-1' : 'mt-2' }}">
                        <span class="{{ $game->getGameTypeEnum()->colorClass() }} px-2 py-0.5 text-xs font-medium rounded text-white">
                            {{ $game->getGameTypeEnum()->label() }}
                        </span>
                    </div>
                @endif
            </div>
        @endif
    </div>
</a>

