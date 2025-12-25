@extends('layouts.app')

@section('title', 'Upcoming Games')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">
                Upcoming Games
            </h1>
            <x-filter-drawer 
                :allGenres="$allGenres"
                :allGameModes="$allGameModes"
                :allGameTypes="$allGameTypes"
                :platformEnums="$platformEnums"
                :activeFilters="$activeFilters"
                :startDate="$startDate"
                :endDate="$endDate"
                :today="$today"
                :maxDate="$maxDate"
            />
        </div>

        <!-- Results Count -->
        @if($games->total() > 0)
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                Found {{ $games->total() }} {{ Str::plural('game', $games->total()) }}
                @if($startDate->ne($today) || $endDate->ne($maxDate))
                    ({{ $startDate->format('d/m/Y') }} - {{ $endDate->format('d/m/Y') }})
                @endif
            </p>
        @endif

        <!-- Active Filters Chips -->
        @php
            $hasActiveFilters = false;
            if ($activeFilters['start_date'] && $activeFilters['start_date'] !== $today->format('Y-m-d')) $hasActiveFilters = true;
            if ($activeFilters['end_date'] && $activeFilters['end_date'] !== $maxDate->format('Y-m-d')) $hasActiveFilters = true;
            if (!empty($activeFilters['genres'])) $hasActiveFilters = true;
            if (!empty($activeFilters['platforms'])) $hasActiveFilters = true;
            if (!empty($activeFilters['game_modes'])) $hasActiveFilters = true;
            if (!empty($activeFilters['game_types'])) $hasActiveFilters = true;
        @endphp

        @if($hasActiveFilters)
            <div class="flex flex-wrap gap-2 mb-6">
                @if($activeFilters['start_date'] && $activeFilters['start_date'] !== $today->format('Y-m-d'))
                    <span class="px-3 py-1 bg-orange-600 text-white rounded-full text-sm flex items-center gap-2">
                        Start: {{ Carbon\Carbon::createFromFormat('Y-m-d', $activeFilters['start_date'])->format('d/m/Y') }}
                        <a href="{{ request()->fullUrlWithQuery(['start_date' => null]) }}" class="hover:text-gray-300">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </a>
                    </span>
                @endif
                @if($activeFilters['end_date'] && $activeFilters['end_date'] !== $maxDate->format('Y-m-d'))
                    <span class="px-3 py-1 bg-orange-600 text-white rounded-full text-sm flex items-center gap-2">
                        End: {{ Carbon\Carbon::createFromFormat('Y-m-d', $activeFilters['end_date'])->format('d/m/Y') }}
                        <a href="{{ request()->fullUrlWithQuery(['end_date' => null]) }}" class="hover:text-gray-300">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </a>
                    </span>
                @endif
                @foreach($activeFilters['genres'] ?? [] as $genreId)
                    @php 
                        $genre = $allGenres->firstWhere('id', (int)$genreId);
                        $remainingGenres = collect($activeFilters['genres'])->reject(fn($id) => (string)$id === (string)$genreId)->values()->toArray();
                        $queryParams = request()->query();
                        $queryParams['genres'] = $remainingGenres ?: null;
                        if (empty($remainingGenres)) {
                            unset($queryParams['genres']);
                        }
                    @endphp
                    @if($genre)
                        <span class="px-3 py-1 bg-orange-600 text-white rounded-full text-sm flex items-center gap-2">
                            {{ $genre->name }}
                            <a href="{{ request()->url() . '?' . http_build_query($queryParams) }}" class="hover:text-gray-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </a>
                        </span>
                    @endif
                @endforeach
                @foreach($activeFilters['platforms'] ?? [] as $platformId)
                    @php 
                        $platformEnum = $platformEnums[(int)$platformId] ?? null;
                        $remainingPlatforms = collect($activeFilters['platforms'])->reject(fn($id) => (string)$id === (string)$platformId)->values()->toArray();
                        $queryParams = request()->query();
                        $queryParams['platforms'] = $remainingPlatforms ?: null;
                        if (empty($remainingPlatforms)) {
                            unset($queryParams['platforms']);
                        }
                    @endphp
                    @if($platformEnum)
                        <span class="px-3 py-1 bg-orange-600 text-white rounded-full text-sm flex items-center gap-2">
                            {{ $platformEnum->label() }}
                            <a href="{{ request()->url() . '?' . http_build_query($queryParams) }}" class="hover:text-gray-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </a>
                        </span>
                    @endif
                @endforeach
                @foreach($activeFilters['game_modes'] ?? [] as $modeId)
                    @php 
                        $mode = $allGameModes->firstWhere('id', (int)$modeId);
                        $remainingModes = collect($activeFilters['game_modes'])->reject(fn($id) => (string)$id === (string)$modeId)->values()->toArray();
                        $queryParams = request()->query();
                        $queryParams['game_modes'] = $remainingModes ?: null;
                        if (empty($remainingModes)) {
                            unset($queryParams['game_modes']);
                        }
                    @endphp
                    @if($mode)
                        <span class="px-3 py-1 bg-orange-600 text-white rounded-full text-sm flex items-center gap-2">
                            {{ $mode->name }}
                            <a href="{{ request()->url() . '?' . http_build_query($queryParams) }}" class="hover:text-gray-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </a>
                        </span>
                    @endif
                @endforeach
                @foreach($activeFilters['game_types'] ?? [] as $gameTypeValue)
                    @php 
                        $gameType = \App\Enums\GameTypeEnum::fromValue((int)$gameTypeValue);
                        $remainingTypes = collect($activeFilters['game_types'])->reject(fn($id) => (string)$id === (string)$gameTypeValue)->values()->toArray();
                        $queryParams = request()->query();
                        $queryParams['game_types'] = $remainingTypes ?: null;
                        if (empty($remainingTypes)) {
                            unset($queryParams['game_types']);
                        }
                    @endphp
                    @if($gameType)
                        <span class="px-3 py-1 bg-orange-600 text-white rounded-full text-sm flex items-center gap-2">
                            {{ $gameType->label() }}
                            <a href="{{ request()->url() . '?' . http_build_query($queryParams) }}" class="hover:text-gray-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </a>
                        </span>
                    @endif
                @endforeach
            </div>
        @endif

        @if($games->count() > 0)
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-8">
                @foreach($games as $game)
                    <x-game-card 
                        :game="$game"
                        variant="glassmorphism"
                        layout="overlay"
                        aspectRatio="3/4"
                        :platformEnums="$platformEnums" />
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
