@props([
    'games',
    'platformEnums' => null,
    'emptyMessage' => 'No games available.',
])

@php
    $platformEnums = $platformEnums ?? \App\Enums\PlatformEnum::getActivePlatforms();
@endphp

@if($games && $games->count() > 0)
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
        @foreach($games as $game)
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
            @endphp

            <a href="{{ $linkUrl }}" class="group block">
                <div class="relative aspect-[3/4] rounded-xl overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 hover:scale-105">
                    @if($coverUrl)
                        <img src="{{ $coverUrl }}"
                             alt="{{ $game->name }}"
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                             onerror="this.onerror=null; this.replaceWith(this.nextElementSibling);">
                        <x-game-cover-placeholder :gameName="$game->name" class="w-full h-full" style="display: none;" />
                    @else
                        <x-game-cover-placeholder :gameName="$game->name" class="w-full h-full" />
                    @endif
                    
                    <!-- Gradient Overlay -->
                    <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/50 to-transparent"></div>
                    
                    <!-- Platform Badges -->
                    @if($sortedPlatforms->count() > 0)
                        <div class="absolute top-2 left-2 flex flex-wrap gap-1 z-10">
                            @foreach($sortedPlatforms as $platform)
                                @php
                                    $enum = $platformEnums[$platform->igdb_id] ?? null;
                                @endphp
                                <span class="px-2 py-1 text-xs font-bold text-white rounded shadow-lg
                                    @if($enum)
                                        bg-{{ $enum->color() }}-600
                                    @else
                                        bg-gray-600
                                    @endif">
                                    {{ $enum?->label() ?? \Illuminate\Support\Str::limit($platform->name, 6) }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                    
                    <!-- Game Info on Gradient Overlay -->
                    <div class="absolute bottom-0 left-0 right-0 p-4 z-10">
                        <h3 class="font-bold text-lg text-white mb-2 line-clamp-2 group-hover:text-orange-400 transition-colors">
                            {{ $game->name }}
                        </h3>
                        
                        @if($game->first_release_date)
                            <p class="text-sm text-gray-300 mb-2">
                                {{ $game->first_release_date->format('d/m/Y') }}
                            </p>
                        @else
                            <p class="text-sm text-gray-300 mb-2">TBA</p>
                        @endif
                        
                        <div class="mt-2">
                            <span class="{{ $game->getGameTypeEnum()->colorClass() }} px-2 py-0.5 text-xs font-medium rounded text-white">
                                {{ $game->getGameTypeEnum()->label() }}
                            </span>
                        </div>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
@else
    <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
        <p class="text-lg text-gray-600 dark:text-gray-400">
            {{ $emptyMessage }}
        </p>
    </div>
@endif

