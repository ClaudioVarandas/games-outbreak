@extends('layouts.app')

@section('title', 'Indie Games' . ($indieList ? ' - ' . $indieList->name : ''))

@section('content')
    <!-- Releases Navigation Menu -->
    <x-releases-nav active="indie-games" />

    <div class="container mx-auto px-4 py-8">
        @if($indieList)
            <!-- Page Header -->
            <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">
                        {{ $indieList->name }}
                    </h1>
                    @if($indieList->description)
                        <p class="text-gray-600 dark:text-gray-400 mt-2">
                            {{ $indieList->description }}
                        </p>
                    @endif
                </div>

                <div class="flex items-center gap-4">
                    <!-- Year Dropdown -->
                    @if($availableYears->count() > 1)
                        <select
                            onchange="window.location.href = '{{ route('indie-games') }}?year=' + this.value"
                            class="px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                        >
                            @foreach($availableYears as $availableYear)
                                <option value="{{ $availableYear }}" {{ $year == $availableYear ? 'selected' : '' }}>
                                    {{ $availableYear }}
                                </option>
                            @endforeach
                        </select>
                    @endif

                    <!-- Search Box -->
                    <div x-data="{ searchQuery: '' }" class="relative">
                        <input
                            type="text"
                            x-model="searchQuery"
                            @input="$dispatch('indie-search', { query: searchQuery })"
                            placeholder="Search games..."
                            class="w-48 md:w-64 px-4 py-2 pl-10 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-800 dark:text-gray-100 placeholder-gray-500 focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                        >
                        <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            @if(count($gamesByGenre) > 0)
                <!-- Genre Tabs -->
                <div class="mb-8" x-data="{
                    activeGenre: '{{ $defaultGenre }}',
                    searchQuery: '',
                    validGenres: @js(array_merge($configuredGenres, ['other'])),
                    init() {
                        const hash = window.location.hash.substring(1);
                        if (hash && (this.validGenres.includes(hash) || hash === 'other')) {
                            this.activeGenre = hash;
                        } else if (this.activeGenre) {
                            window.location.hash = this.activeGenre;
                        }
                        this.$watch('activeGenre', (value) => {
                            window.location.hash = value;
                        });
                    }
                }" @indie-search.window="searchQuery = $event.detail.query.toLowerCase()">
                    <!-- Tab Navigation -->
                    <div class="flex flex-wrap gap-2 mb-6 border-b border-gray-200 dark:border-gray-700 pb-4">
                        @foreach($configuredGenres as $genre)
                            @if(isset($gamesByGenre[$genre]))
                                <button
                                    @click="activeGenre = '{{ $genre }}'"
                                    :class="activeGenre === '{{ $genre }}'
                                        ? 'bg-orange-500 text-white'
                                        : 'bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-300 dark:hover:bg-gray-500'"
                                    class="px-4 py-2 rounded-lg font-medium transition-colors capitalize"
                                >
                                    {{ ucfirst($genre) }}
                                    <span class="ml-1 text-sm opacity-80">({{ $genreCounts[$genre] ?? 0 }})</span>
                                </button>
                            @endif
                        @endforeach
                        @if(isset($gamesByGenre['other']))
                            <button
                                @click="activeGenre = 'other'"
                                :class="activeGenre === 'other'
                                    ? 'bg-orange-500 text-white'
                                    : 'bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-400 hover:bg-gray-300 dark:hover:bg-gray-500'"
                                class="px-4 py-2 rounded-lg font-medium transition-colors"
                            >
                                Other
                                <span class="ml-1 text-sm opacity-80">({{ $genreCounts['other'] ?? 0 }})</span>
                            </button>
                        @endif
                    </div>

                    <!-- Games Grid for Each Genre -->
                    @foreach($configuredGenres as $genre)
                        @if(isset($gamesByGenre[$genre]))
                            <div x-show="activeGenre === '{{ $genre }}'" x-cloak>
                                <!-- Genre Header -->
                                <div class="flex items-center gap-3 mb-6">
                                    <span class="px-3 py-1 rounded text-white font-medium bg-amber-500 capitalize">
                                        {{ ucfirst($genre) }}
                                    </span>
                                    <span class="text-gray-600 dark:text-gray-400">
                                        {{ $genreCounts[$genre] ?? 0 }} {{ Str::plural('game', $genreCounts[$genre] ?? 0) }}
                                    </span>
                                </div>

                                <!-- Games by Month -->
                                <div>
                                    @foreach($gamesByGenre[$genre] as $monthKey => $monthData)
                                        <div class="pt-10">
                                            <!-- Month Header -->
                                            <div class="flex items-center justify-center gap-4 mb-8">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                                </div>
                                                <div class="text-center px-4">
                                                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100">
                                                        {{ $monthKey === 'tba' ? 'To Be Announced' : $monthData['label'] }}
                                                    </h3>
                                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                                        {{ count($monthData['games']) }} {{ Str::plural('game', count($monthData['games'])) }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                                </div>
                                            </div>

                                            <!-- Games Grid for this Month -->
                                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-8">
                                                @foreach($monthData['games'] as $game)
                                                    @php
                                                        $pivotReleaseDate = $game->pivot->release_date ?? null;
                                                        if ($pivotReleaseDate && is_string($pivotReleaseDate)) {
                                                            $pivotReleaseDate = \Carbon\Carbon::parse($pivotReleaseDate);
                                                        }
                                                        $displayDate = $pivotReleaseDate ?? $game->first_release_date;
                                                    @endphp
                                                    <div x-show="searchQuery === '' || '{{ strtolower($game->name) }}'.includes(searchQuery)">
                                                        <x-game-card
                                                            :game="$game"
                                                            :displayReleaseDate="$displayDate"
                                                            variant="glassmorphism"
                                                            layout="overlay"
                                                            aspectRatio="3/4"
                                                            :platformEnums="$platformEnums" />
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach

                    @if(isset($gamesByGenre['other']))
                        <div x-show="activeGenre === 'other'" x-cloak>
                            <!-- Genre Header -->
                            <div class="flex items-center gap-3 mb-6">
                                <span class="px-3 py-1 rounded text-white font-medium bg-gray-500">
                                    Other
                                </span>
                                <span class="text-gray-600 dark:text-gray-400">
                                    {{ $genreCounts['other'] ?? 0 }} {{ Str::plural('game', $genreCounts['other'] ?? 0) }}
                                </span>
                            </div>

                            <!-- Games by Month -->
                            <div>
                                @foreach($gamesByGenre['other'] as $monthKey => $monthData)
                                    <div class="pt-10">
                                        <!-- Month Header -->
                                        <div class="flex items-center justify-center gap-4 mb-8">
                                            <div class="flex items-center gap-1.5">
                                                <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                                                <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                                                <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                                            </div>
                                            <div class="text-center px-4">
                                                <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100">
                                                    {{ $monthKey === 'tba' ? 'To Be Announced' : $monthData['label'] }}
                                                </h3>
                                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ count($monthData['games']) }} {{ Str::plural('game', count($monthData['games'])) }}
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                                                <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                                                <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                                            </div>
                                        </div>

                                        <!-- Games Grid for this Month -->
                                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-8">
                                            @foreach($monthData['games'] as $game)
                                                @php
                                                    $pivotReleaseDate = $game->pivot->release_date ?? null;
                                                    if ($pivotReleaseDate && is_string($pivotReleaseDate)) {
                                                        $pivotReleaseDate = \Carbon\Carbon::parse($pivotReleaseDate);
                                                    }
                                                    $displayDate = $pivotReleaseDate ?? $game->first_release_date;
                                                @endphp
                                                <div x-show="searchQuery === '' || '{{ strtolower($game->name) }}'.includes(searchQuery)">
                                                    <x-game-card
                                                        :game="$game"
                                                        :displayReleaseDate="$displayDate"
                                                        variant="glassmorphism"
                                                        layout="overlay"
                                                        aspectRatio="3/4"
                                                        :platformEnums="$platformEnums" />
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @else
                <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                    <p class="text-xl text-gray-600 dark:text-gray-400">
                        No indie games in the list yet.
                    </p>
                </div>
            @endif
        @else
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100 mb-4">
                    Indie Games
                </h1>
                <p class="text-xl text-gray-600 dark:text-gray-400">
                    No active indie games list for {{ $year }}.
                </p>
                @if($availableYears->count() > 0)
                    <div class="mt-4">
                        <p class="text-gray-600 dark:text-gray-400 mb-2">Available years:</p>
                        <div class="flex justify-center gap-2">
                            @foreach($availableYears as $availableYear)
                                <a href="{{ route('indie-games') }}?year={{ $availableYear }}"
                                   class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg transition">
                                    {{ $availableYear }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection
