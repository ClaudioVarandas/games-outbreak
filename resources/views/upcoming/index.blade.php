@extends('layouts.app')

@section('title', 'Upcoming Games')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-10">
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">
                Upcoming Games
            </h1>
        </div>

        @if($games->count() > 0)
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-8">
                @foreach($games as $game)
                    <a href="{{ route('game.show', $game) }}" class="block">
                        <div class="group relative bg-gray-800 dark:bg-gray-800 rounded-xl overflow-hidden shadow-xl hover:shadow-2xl transition-all duration-400">
                            <!-- Cover Image -->
                            <div class="relative aspect-[3/4] bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                @php
                                    $coverUrl = $game->cover_image_id
                                        ? $game->getCoverUrl('cover_big')
                                        : ($game->steam_data['header_image'] ?? null);
                                @endphp
                                @if($coverUrl)
                                    <img src="{{ $coverUrl }}"
                                         alt="{{ $game->name }} cover"
                                         class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <x-game-cover-placeholder :gameName="$game->name" class="w-full h-full" style="display: none;" />
                                @else
                                    <x-game-cover-placeholder :gameName="$game->name" class="w-full h-full" />
                                @endif

                                <!-- Platform Badges -->
                                @php
                                    $validPlatformIds = $platformEnums->keys()->toArray();
                                    $filteredPlatforms = $game->platforms 
                                        ? $game->platforms->filter(fn($p) => in_array($p->igdb_id, $validPlatformIds))
                                        : collect();
                                    
                                    // Sort platforms using config-based priority: PC first, then consoles, then Linux/macOS
                                    $sortedPlatforms = $filteredPlatforms->sortBy(function($platform) {
                                        return \App\Enums\PlatformEnum::getPriority($platform->igdb_id);
                                    })->values();
                                    
                                    $displayPlatforms = $sortedPlatforms;
                                @endphp
                                @if($displayPlatforms->count() > 0)
                                    <div class="absolute top-2 left-2 flex flex-wrap gap-1 z-10">
                                        @foreach($displayPlatforms as $platform)
                                            @php
                                                $enum = $platformEnums[$platform->igdb_id] ?? null;
                                            @endphp
                                            <span class="px-2 py-1 text-xs font-bold text-white rounded shadow-lg
                                        @if($enum)
                                            bg-{{ $enum->color() }}-600
                                        @else
                                            bg-gray-600
                                        @endif">
                                        {{ $enum?->label() ?? Str::limit($platform->name, 6) }}
                                    </span>
                                        @endforeach
                                    </div>
                                @endif

                                <!-- Bottom Overlay with Title and Date -->
                                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/90 via-black/70 to-transparent px-4 pt-20 pb-4 opacity-100 translate-y-0 transition-all duration-400 ease-out">
                                    <h3 class="text-md font-bold text-white mb-2 leading-tight line-clamp-2">
                                        {{ $game->name }}
                                    </h3>
                                    @if($game->first_release_date)
                                        <p class="text-md font-semibold text-cyan-300 dark:text-cyan-400 mb-2">
                                            {{ $game->first_release_date->format('d/m/Y') }}
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
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-10">
                {{ $games->links() }}
            </div>
        @else
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                <p class="text-xl text-gray-600 dark:text-gray-400">
                    No upcoming games found.
                </p>
            </div>
        @endif
    </div>
@endsection
