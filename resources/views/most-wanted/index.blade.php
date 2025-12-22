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
                    class="bg-gray-700 text-gray-200 p-2 rounded-lg focus:ring-teal-500 focus:border-teal-500">
                <option value="all">All Genres</option>
                @foreach($games->pluck('genres')->flatten()->unique('name') as $genre)
                    <option value="{{ $genre->name }}">{{ $genre->name }}</option>
                @endforeach
            </select>

            <select id="sort-by"
                    class="bg-gray-700 text-gray-200 p-2 rounded-lg focus:ring-teal-500 focus:border-teal-500">
                <option value="score">Sort by: Wanted Score (Default)</option>
                <option value="release">Sort by: Release Date</option>
                <option value="title">Sort by: Title</option>
            </select>
        </div>

        <div id="game-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
            @foreach($games as $index => $game)
                @php
                    $rank = $index + 1;
                    $rankColor = $rank === 1 ? 'bg-yellow-500' : ($rank <= 5 ? 'bg-slate-400' : 'bg-teal-500');
                    $release = $game->first_release_date?->format('M j, Y') ?? 'TBA';
                    $wishlistProxy = $game->steam_data['recommendations'] ?? 'N/A';
                @endphp

                <a href="{{ route('game.show', $game) }}" class="block">
                    <div class="game-card relative bg-gray-800 rounded-lg overflow-hidden cursor-pointer group"
                         data-genre="{{ $game->genres->first()?->name ?? 'Unknown' }}"
                         data-score="{{ $game->wanted_score }}"
                         data-release="{{ $game->first_release_date }}"
                         data-title="{{ $game->name }}">

                        <div
                            class="absolute top-0 left-0 {{ $rankColor }} text-gray-900 text-sm font-black px-3 py-1 rounded-br-lg z-20 shadow-lg">
                            #{{ $rank }}
                        </div>

                        <div class="w-full aspect-video overflow-hidden">
                            <img src="{{ $game->steam_data['header_image'] ?? $game->getCoverUrl('720p') }}"
                                 alt="{{ $game->name }}"
                                 class="w-full h-full object-cover group-hover:opacity-80 transition-opacity">
                        </div>

                        <div class="p-4 relative">
                            <div class="h-2.5 w-full bg-gray-700 rounded-full mb-3 overflow-hidden">
                                <div class="h-full bg-red-600 transition-all"
                                     style="width: {{ $game->wanted_score }}%;"></div>
                            </div>
                            <p class="text-xs text-gray-400 mb-1">Wanted Score: <span class="text-red-400 font-bold">{{ $game->wanted_score }}%</span>
                            </p>

                            <h3 class="text-lg font-semibold text-white truncate mb-2">{{ $game->name }}</h3>

                            <div class="flex justify-between items-center text-sm text-gray-400 mb-2">
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-1 text-teal-400" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zM4 9h12v7a1 1 0 01-1 1H5a1 1 0 01-1-1V9z"
                                    fill-rule="evenodd" clip-rule="evenodd"></path>
                            </svg>
                            {{ $release }}
                        </span>
                                <span class="text-xs bg-gray-700 px-2 py-0.5 rounded-full">
                            {{ $game->genres->first()?->name ?? 'N/A' }}
                        </span>
                            </div>
                            <div class="mt-1">
                                <span class="{{ $game->getGameTypeEnum()->colorClass() }} px-2 py-0.5 text-xs font-medium rounded text-white">
                                    {{ $game->getGameTypeEnum()->label() }}
                                </span>
                            </div>

                            <div
                                class="cta-hover absolute inset-0 bg-gray-900 bg-opacity-95 flex flex-col justify-center items-center p-4 z-30">
                                <p class="text-sm text-gray-300 mb-2 font-light">
                                    Recommendations: {{ $wishlistProxy }}</p>
                                <button
                                    class="bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 px-6 rounded-full transition duration-150 shadow-lg">
                                    Add to Watchlist
                                </button>
                                <p class="text-xs text-gray-500 mt-2">
                                    @foreach($game->platforms->take(3) as $plat)
                                        @php $enum = $platformEnums[$plat->igdb_id] ?? null @endphp
                                        {{ $enum?->label() ?? $plat->name }}
                                        {{ !$loop->last ? ' • ' : '' }}
                                    @endforeach
                                </p>
                            </div>
                        </div>
                    </div>
                </a>
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
