@props([
    'game',
    'variant' => 'simple', // 'glassmorphism', 'simple', 'carousel', 'overlay', 'table-row'
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
    'displayPlatforms' => null, // Optional: JSON array of platform IDs to display (from pivot)
    'displayReleaseDateFormatted' => null, // Optional: pre-formatted release date string
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

    // Generate unique ID for this card
    $cardId = 'game-card-' . $game->id . '-' . uniqid();

    $coverUrl = $game->cover_image_id
        ? $game->getCoverUrl('cover_big')
        : ($game->steam_data['header_image'] ?? null);
    $linkUrl = $game->slug
        ? route('game.show', $game)
        : route('game.show.igdb', $game->igdb_id);

    // Platform badges logic
    $validPlatformIds = $platformEnums->keys()->toArray();

    // If displayPlatforms is provided (from pivot), decode and filter by those IDs
    if ($displayPlatforms) {
        $decodedPlatformIds = is_string($displayPlatforms) ? json_decode($displayPlatforms, true) : $displayPlatforms;
        $filteredPlatforms = $game->platforms
            ? $game->platforms->filter(fn($p) => in_array($p->igdb_id, $decodedPlatformIds) && in_array($p->igdb_id, $validPlatformIds))
            : collect();
    } else {
        // Default: use all game platforms
        $filteredPlatforms = $game->platforms
            ? $game->platforms->filter(fn($p) => in_array($p->igdb_id, $validPlatformIds))
            : collect();
    }

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

{{-- TABLE ROW VARIANT --}}
@if($variant === 'table-row')
<div class="relative flex items-center gap-4 p-3 bg-white dark:bg-gray-800 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors border border-gray-200 dark:border-gray-700">
    {{-- Small Cover Thumbnail --}}
    <a href="{{ $linkUrl }}" class="flex-shrink-0">
        <div class="w-12 h-16 rounded overflow-hidden bg-gray-200 dark:bg-gray-700">
            @if($coverUrl)
                <img src="{{ $coverUrl }}"
                     alt="{{ $game->name }}"
                     class="w-full h-full object-cover"
                     loading="lazy">
            @else
                <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
            @endif
        </div>
    </a>

    {{-- Game Name --}}
    <div class="flex-1 min-w-0">
        <a href="{{ $linkUrl }}" class="block">
            <h3 class="font-semibold text-gray-900 dark:text-white truncate hover:text-orange-500 dark:hover:text-orange-400 transition-colors">
                {{ $game->name }}
            </h3>
            <span class="{{ $game->getGameTypeEnum()->colorClass() }} px-1.5 py-0.5 text-xs font-medium rounded inline-block mt-1">
                {{ $game->getGameTypeEnum()->label() }}
            </span>
        </a>
    </div>

    {{-- Platforms (hidden on mobile) --}}
    <div class="hidden sm:flex flex-shrink-0 gap-1 items-center">
        @foreach($sortedPlatforms->take(4) as $platform)
            @php
                $enum = $platformEnums[$platform->igdb_id] ?? null;
            @endphp
            <span class="px-1.5 py-0.5 text-xs font-bold text-white rounded bg-{{ $enum?->color() ?? 'gray' }}-600">
                {{ $enum?->label() ?? \Illuminate\Support\Str::limit($platform->name, 4) }}
            </span>
        @endforeach
        @if($sortedPlatforms->count() > 4)
            <span class="px-1.5 py-0.5 text-xs font-medium text-gray-500 dark:text-gray-400">
                +{{ $sortedPlatforms->count() - 4 }}
            </span>
        @endif
    </div>

    {{-- Release Date --}}
    <div class="flex-shrink-0 text-right hidden xs:block">
        <span class="text-sm font-medium text-gray-600 dark:text-gray-400 whitespace-nowrap">
            {{ $displayReleaseDateFormatted ?? $releaseDate?->format('M j, Y') ?? 'TBA' }}
        </span>
    </div>

    {{-- Quick Actions --}}
    <div class="flex-shrink-0">
        <x-game-quick-actions
            :game="$game"
            :backlogList="$backlogList"
            :wishlistList="$wishlistList"
            compact="true" />
    </div>
</div>
@else
{{-- Wrapper for card + mobile buttons --}}
<div class="{{ ($variant === 'carousel' || $carousel) ? 'flex-shrink-0 w-56 md:w-64' : '' }}">
    <a href="{{ $linkUrl }}" class="group block transition-all duration-300 hover:z-30">
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
            
            <!-- Quick Actions (Desktop Only) -->
            <div class="hidden md:block">
                <x-game-quick-actions
                    :game="$game"
                    :backlogList="$backlogList"
                    :wishlistList="$wishlistList" />
            </div>

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
                    <div class="flex items-center justify-between mb-2">
                        <span class="{{ $game->getGameTypeEnum()->colorClass() }} px-2 py-0.5 text-xs font-medium rounded text-white">
                            {{ $game->getGameTypeEnum()->label() }}
                        </span>
                        <p class="text-sm font-semibold text-cyan-300 dark:text-cyan-400">
                            {{ $releaseDate?->format('d/m/Y') ?? 'TBA' }}
                        </p>
                    </div>

                    <!-- Mobile Quick Actions -->
                    <x-game-quick-actions-mobile
                        :game="$game"
                        :backlogList="$backlogList"
                        :wishlistList="$wishlistList" />
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

                    <div class="flex items-center justify-between mb-2">
                        <span class="{{ $game->getGameTypeEnum()->colorClass() }} px-2 py-0.5 text-xs font-medium rounded text-white backdrop-blur-sm bg-black/40 border border-white/20">
                            {{ $game->getGameTypeEnum()->label() }}
                        </span>
                        <p class="text-sm text-gray-200">
                            {{ $releaseDate?->format('d/m/Y') ?? 'TBA' }}
                        </p>
                    </div>

                    <!-- Mobile Quick Actions -->
                    <x-game-quick-actions-mobile
                        :game="$game"
                        :backlogList="$backlogList"
                        :wishlistList="$wishlistList" />
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
                    <p class="text-xs text-gray-400 mb-2">Wanted Score: <span class="text-red-400 font-bold">{{ $wantedScore }}%</span></p>
                @endif

                <h3 class="font-bold text-lg {{ $variant === 'carousel' ? 'text-white truncate group-hover/card:text-orange-400' : ($variant === 'simple' ? ($wantedScore !== null ? 'text-white truncate' : 'text-gray-900 dark:text-white truncate') : 'text-white truncate') }} mb-2">
                    {{ $game->name }}
                </h3>

                <div class="flex items-center justify-between mb-2">
                    <span class="{{ $game->getGameTypeEnum()->colorClass() }} px-2 py-0.5 text-xs font-medium rounded text-white">
                        {{ $game->getGameTypeEnum()->label() }}
                    </span>
                    <p class="text-sm {{ $variant === 'carousel' ? 'text-gray-400' : ($variant === 'simple' ? ($wantedScore !== null ? 'text-gray-400' : 'text-gray-600 dark:text-gray-400') : 'text-gray-400') }}">
                        {{ $releaseDate?->format('d/m/Y') ?? 'TBA' }}
                    </p>
                </div>

                <!-- Mobile Quick Actions -->
                <x-game-quick-actions-mobile
                    :game="$game"
                    :backlogList="$backlogList"
                    :wishlistList="$wishlistList" />
            </div>
        @endif
    </div>
    </a>
</div>
@endif
