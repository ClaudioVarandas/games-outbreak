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

                <div class="flex flex-wrap items-center gap-4">
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

                    <!-- Genre Multi-Select Filter -->
                    <div x-data="genreFilter()" x-init="init()" class="relative min-w-[200px]">
                        <select x-ref="genreSelect" multiple>
                            @foreach($genres as $genre)
                                <option value="{{ $genre->id }}">{{ $genre->name }}</option>
                            @endforeach
                            @if($otherGenre)
                                <option value="other">Other</option>
                            @endif
                        </select>
                    </div>

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

            @if(count($gamesByMonth) > 0)
                <!-- Main Content Area -->
                <main x-data="{
                    searchQuery: '',
                    selectedGenres: [],
                    matchesFilters(gameName, primaryGenreId, genreIds) {
                        const matchesSearch = this.searchQuery === '' || gameName.includes(this.searchQuery);
                        if (this.selectedGenres.length === 0) return matchesSearch;
                        const hasNoGenre = primaryGenreId === null && genreIds.length === 0;
                        const matchesGenre = this.selectedGenres.some(g =>
                            (g === 'other' && hasNoGenre) ||
                            g === primaryGenreId ||
                            genreIds.includes(g)
                        );
                        return matchesSearch && matchesGenre;
                    }
                }"
                @indie-search.window="searchQuery = $event.detail.query.toLowerCase()"
                @genre-filter-change.window="selectedGenres = $event.detail.genres">

                    <!-- Games by Month -->
                    @foreach($gamesByMonth as $monthKey => $monthData)
                        <div class="pt-10 first:pt-0">
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
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-8">
                                @foreach($monthData['games'] as $game)
                                    @php
                                        $pivotReleaseDate = $game->pivot->release_date ?? null;
                                        if ($pivotReleaseDate && is_string($pivotReleaseDate)) {
                                            $pivotReleaseDate = \Carbon\Carbon::parse($pivotReleaseDate);
                                        }
                                        $displayDate = $pivotReleaseDate ?? $game->first_release_date;
                                        $primaryGenreId = $game->pivot->primary_genre_id;
                                        $rawGenreIds = $game->pivot->genre_ids;
                                        $genreIds = is_string($rawGenreIds) ? json_decode($rawGenreIds, true) ?? [] : ($rawGenreIds ?? []);
                                    @endphp
                                    <div x-show="matchesFilters(@js(strtolower($game->name)), {{ $primaryGenreId ? $primaryGenreId : 'null' }}, @js(array_map('intval', $genreIds)))">
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
                </main>
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

    @push('scripts')
    <script>
        function genreFilter() {
            return {
                selectedGenres: [],
                init() {
                    if (typeof TomSelect === 'undefined') {
                        console.error('TomSelect is not loaded');
                        return;
                    }

                    new TomSelect(this.$refs.genreSelect, {
                        plugins: ['remove_button'],
                        placeholder: 'Filter by genre...',
                        allowEmptyOption: true,
                        maxItems: 5,
                        onChange: (values) => {
                            this.selectedGenres = values.map(v => v === 'other' ? 'other' : parseInt(v));
                            this.$dispatch('genre-filter-change', { genres: this.selectedGenres });
                        }
                    });
                }
            };
        }
    </script>
    @endpush
@endsection
