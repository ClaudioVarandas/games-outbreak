{{-- resources/views/most-wanted/index.blade.php --}}

@extends('layouts.app')

@section('title', 'Most Wanted Games')

@section('content')
    <div class="bg-gray-900 text-gray-100 min-h-screen p-8">
        <header class="mb-10">
            <h1 class="text-4xl font-extrabold text-white mb-2">🔥 The Most Wanted Games Grid</h1>
            <p class="text-gray-400">
                Ranked by Wanted Score: Steam Recommendations (40%) + Genre Hype (30%) + Multiplayer Boost (20%) +
                Release Proximity (10%)
            </p>
        </header>

        <div class="flex flex-wrap gap-4 mb-8">
            <select id="genre-filter"
                    class="bg-gray-700 text-gray-200 p-2 rounded-lg focus:ring-orange-500 focus:border-orange-500">
                <option value="all">All Genres</option>
                @foreach($games->pluck('genres')->flatten()->unique('name') as $genre)
                    <option value="{{ $genre->name }}">{{ $genre->name }}</option>
                @endforeach
            </select>

            <select id="sort-by"
                    class="bg-gray-700 text-gray-200 p-2 rounded-lg focus:ring-orange-500 focus:border-orange-500">
                <option value="score">Sort by: Wanted Score (Default)</option>
                <option value="release">Sort by: Release Date</option>
                <option value="title">Sort by: Title</option>
            </select>
        </div>

        <div id="game-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
            @foreach($games as $index => $game)
                @php
                    $rank = $index + 1;
                    $rankColor = $rank === 1 ? 'bg-yellow-500' : ($rank <= 5 ? 'bg-slate-400' : 'bg-orange-500');
                    $release = $game->first_release_date?->format('d/m/Y') ?? 'TBA';
                    $wishlistProxy = $game->steam_data['recommendations'] ?? 'N/A';
                @endphp

                <div class="game-card relative"
                     data-genre="{{ $game->genres->first()?->name ?? 'Unknown' }}"
                     data-score="{{ $game->wanted_score }}"
                     data-release="{{ $game->first_release_date }}"
                     data-title="{{ $game->name }}">
                    <x-game-card 
                        :game="$game"
                        variant="glassmorphism"
                        layout="overlay"
                        aspectRatio="video"
                        :showRank="true"
                        :rank="$rank"
                        :rankColor="$rankColor"
                        :wantedScore="$game->wanted_score"
                        :platformEnums="$platformEnums" />
                    
                    <!-- CTA Hover Overlay (Most Wanted specific) -->
                    <div class="cta-hover absolute inset-0 bg-gray-900 bg-opacity-95 flex flex-col justify-center items-center p-4 z-30">
                        <p class="text-sm text-gray-300 mb-2 font-light">
                            Recommendations: {{ $game->steam_data['recommendations'] ?? 'N/A' }}</p>
                        <button
                            class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 px-6 rounded-full transition duration-150 shadow-lg">
                            Add to Watchlist
                        </button>
                                <p class="text-xs text-gray-500 mt-2">
                                    @php
                                        $validPlatformIds = $platformEnums->keys()->toArray();
                                        $filteredPlatforms = $game->platforms 
                                            ? $game->platforms->filter(fn($p) => in_array($p->igdb_id, $validPlatformIds))
                                            : collect();
                                        
                                        // Sort platforms using config-based priority: PC first, then consoles, then Linux/macOS
                                        $sortedPlatforms = $filteredPlatforms->sortBy(function($platform) {
                                            return \App\Enums\PlatformEnum::getPriority($platform->igdb_id);
                                        })->values();
                                    @endphp
                                    @foreach($sortedPlatforms as $plat)
                                        @php $enum = $platformEnums[$plat->igdb_id] ?? null @endphp
                                        {{ $enum?->label() ?? $plat->name }}
                                        {{ !$loop->last ? ' • ' : '' }}
                                    @endforeach
                                </p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <script>
        // Reuse your original JS filtering/sorting logic here
        // Just adapt it to work with data attributes already set
        document.getElementById('genre-filter').addEventListener('change', filterAndSort);
        document.getElementById('sort-by').addEventListener('change', filterAndSort);
        // ... paste your filterAndSort() function
    </script>
@endsection
