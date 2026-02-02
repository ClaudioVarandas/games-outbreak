@extends('layouts.app')

@section('title', ($month ? \Carbon\Carbon::create($year, $month)->format('F') . ' ' : '') . "Game Releases {$year}")

@section('content')
    <!-- Releases Navigation Menu -->
    <x-releases-nav active="releases" />

    <div class="container mx-auto px-4 py-8">
        @if($yearlyList)
            <!-- Page Header -->
            <div class="mb-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">
                            @if($month)
                                {{ \Carbon\Carbon::create($year, $month)->format('F') }} {{ $year }}
                            @else
                                Game Releases {{ $year }}
                            @endif
                        </h1>
                        @if($yearlyList->description)
                            <p class="text-gray-600 dark:text-gray-400 mt-2">
                                {{ $yearlyList->description }}
                            </p>
                        @endif
                    </div>

                    <!-- Year Navigation -->
                    <div class="flex items-center gap-4">
                        @if($prevYear)
                            <a href="{{ route('releases.year', $prevYear) }}"
                               class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                                &larr; {{ $prevYear }}
                            </a>
                        @endif

                        <span class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ $year }}</span>

                        @if($nextYear)
                            <a href="{{ route('releases.year', $nextYear) }}"
                               class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                                {{ $nextYear }} &rarr;
                            </a>
                        @endif
                    </div>
                </div>

                @if($month)
                    <a href="{{ route('releases.year', $year) }}"
                       class="inline-block mt-3 text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300 font-medium transition">
                        &larr; Back to full year
                    </a>
                @endif
            </div>

            <!-- Filter Bar -->
            <div class="mb-8" x-data="releasesFilter()" x-init="init()">
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <!-- Type Pills -->
                    <button @click="typeFilter = 'all'" :class="typeFilter === 'all' ? 'bg-orange-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200'"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition hover:opacity-90">
                        All
                    </button>
                    <button @click="typeFilter = 'highlights'" :class="typeFilter === 'highlights' ? 'bg-yellow-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200'"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition hover:opacity-90">
                        Highlights
                    </button>
                    <button @click="typeFilter = 'indies'" :class="typeFilter === 'indies' ? 'bg-amber-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200'"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition hover:opacity-90">
                        Indies
                    </button>

                    <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 hidden sm:block"></div>

                    <!-- Platform Group Filter -->
                    <select x-model="platformGroupFilter"
                            class="px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        <option value="">All Platforms</option>
                        @foreach(\App\Enums\PlatformGroupEnum::orderedCases() as $group)
                            <option value="{{ $group->value }}">{{ $group->label() }}</option>
                        @endforeach
                    </select>

                    <!-- Genre Multi-Select -->
                    <div class="relative min-w-[180px]">
                        <select x-ref="genreSelect" multiple>
                            @foreach($genres as $genre)
                                <option value="{{ $genre->id }}">{{ $genre->name }}</option>
                            @endforeach
                            @if($otherGenre)
                                <option value="other">Other</option>
                            @endif
                        </select>
                    </div>

                    <!-- Search -->
                    <div class="relative">
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="Search games..."
                               class="w-40 md:w-56 px-4 py-2 pl-10 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-800 dark:text-gray-100 placeholder-gray-500 focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>

                    @if(!$month)
                        <!-- Hide TBA Checkbox -->
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                            <input type="checkbox" x-model="hideTba" class="rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                            Hide TBA
                        </label>
                    @endif

                    <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 hidden sm:block"></div>

                    <!-- View Toggle -->
                    <div class="flex items-center gap-1">
                        <button @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-orange-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300'"
                                class="p-2 rounded-lg transition hover:opacity-90" title="Grid view">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                            </svg>
                        </button>
                        <button @click="viewMode = 'list'" :class="viewMode === 'list' ? 'bg-orange-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300'"
                                class="p-2 rounded-lg transition hover:opacity-90" title="List view">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                @if(!$month)
                    <!-- Month Dropdown -->
                    <div class="mb-4">
                        <select onchange="if(this.value) window.location.href = this.value"
                                class="px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            <option value="">Jump to month...</option>
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ route('releases.year.month', [$year, $m]) }}">
                                    {{ \Carbon\Carbon::create($year, $m)->format('F') }}
                                </option>
                            @endfor
                        </select>
                    </div>
                @endif

                <!-- Games by Month -->
                @if(count($gamesByMonth) > 0)
                    @foreach($gamesByMonth as $monthKey => $monthData)
                        <div class="pt-10 first:pt-0"
                             x-show="!hideTba || '{{ $monthKey }}' !== 'tba'"
                             x-data="{ visibleCount: 0 }"
                             x-effect="visibleCount = document.querySelectorAll('[data-month=\'{{ $monthKey }}\'] [data-game-card]:not([style*=\'display: none\'])').length">

                            <!-- Month Header -->
                            <div class="flex items-center justify-center gap-4 mb-8">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-orange-400"></span>
                                    <span class="w-2 h-2 rounded-full bg-orange-400"></span>
                                    <span class="w-2 h-2 rounded-full bg-orange-400"></span>
                                </div>
                                <div class="text-center px-4">
                                    @if($monthKey !== 'tba' && !$month)
                                        <a href="{{ route('releases.year.month', [$year, $monthData['month_number']]) }}"
                                           class="text-xl font-bold text-gray-800 dark:text-gray-100 hover:text-orange-600 dark:hover:text-orange-400 transition">
                                            {{ $monthData['label'] }}
                                        </a>
                                    @else
                                        <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100">
                                            {{ $monthData['label'] }}
                                        </h3>
                                    @endif
                                    <span class="block text-sm text-gray-500 dark:text-gray-400">
                                        {{ count($monthData['games']) }} {{ Str::plural('game', count($monthData['games'])) }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-orange-400"></span>
                                    <span class="w-2 h-2 rounded-full bg-orange-400"></span>
                                    <span class="w-2 h-2 rounded-full bg-orange-400"></span>
                                </div>
                            </div>

                            @php
                                $monthGames = collect($monthData['games'])->map(function ($game) {
                                    $pivotReleaseDate = $game->pivot->release_date ?? null;
                                    if ($pivotReleaseDate && is_string($pivotReleaseDate)) {
                                        $pivotReleaseDate = \Carbon\Carbon::parse($pivotReleaseDate);
                                    }
                                    return [
                                        'game' => $game,
                                        'displayDate' => $pivotReleaseDate ?? $game->first_release_date,
                                        'primaryGenreId' => $game->pivot->primary_genre_id,
                                        'genreIds' => is_string($game->pivot->genre_ids) ? json_decode($game->pivot->genre_ids, true) ?? [] : ($game->pivot->genre_ids ?? []),
                                        'isHighlight' => (bool) $game->pivot->is_highlight,
                                        'isIndie' => (bool) $game->pivot->is_indie,
                                        'platformGroup' => $game->pivot->platform_group ?? '',
                                    ];
                                });
                            @endphp

                            <!-- Games Grid -->
                            <div x-show="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-8" data-month="{{ $monthKey }}">
                                @foreach($monthGames as $entry)
                                    <div x-show="matchesFilters(@js(strtolower($entry['game']->name)), {{ $entry['primaryGenreId'] ? $entry['primaryGenreId'] : 'null' }}, @js(array_map('intval', $entry['genreIds'])), {{ $entry['isHighlight'] ? 'true' : 'false' }}, {{ $entry['isIndie'] ? 'true' : 'false' }}, @js($entry['platformGroup']))"
                                         data-game-card>
                                        <x-game-card
                                            :game="$entry['game']"
                                            :displayReleaseDate="$entry['displayDate']"
                                            variant="default"
                                            layout="overlay"
                                            aspectRatio="3/4"
                                            :platformEnums="$platformEnums" />
                                    </div>
                                @endforeach
                            </div>

                            <!-- Games List -->
                            <div x-show="viewMode === 'list'" class="space-y-2" data-month="{{ $monthKey }}">
                                @foreach($monthGames as $entry)
                                    <div x-show="matchesFilters(@js(strtolower($entry['game']->name)), {{ $entry['primaryGenreId'] ? $entry['primaryGenreId'] : 'null' }}, @js(array_map('intval', $entry['genreIds'])), {{ $entry['isHighlight'] ? 'true' : 'false' }}, {{ $entry['isIndie'] ? 'true' : 'false' }}, @js($entry['platformGroup']))"
                                         data-game-card>
                                        <x-game-card
                                            :game="$entry['game']"
                                            :displayReleaseDate="$entry['displayDate']"
                                            variant="table-row"
                                            :platformEnums="$platformEnums" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                        <p class="text-xl text-gray-600 dark:text-gray-400">
                            @if($month)
                                No games found for {{ \Carbon\Carbon::create($year, $month)->format('F Y') }}.
                            @else
                                No games in this list yet.
                            @endif
                        </p>
                    </div>
                @endif
            </div>
        @else
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100 mb-4">
                    Game Releases {{ $year }}
                </h1>
                <p class="text-xl text-gray-600 dark:text-gray-400">
                    No active releases list for {{ $year }}.
                </p>
                @if($availableYears->count() > 0)
                    <div class="mt-4">
                        <p class="text-gray-600 dark:text-gray-400 mb-2">Available years:</p>
                        <div class="flex justify-center gap-2">
                            @foreach($availableYears as $availableYear)
                                <a href="{{ route('releases.year', $availableYear) }}"
                                   class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition">
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
        function releasesFilter() {
            return {
                typeFilter: 'all',
                platformGroupFilter: '',
                searchQuery: '',
                selectedGenres: [],
                hideTba: false,
                viewMode: localStorage.getItem('releases_view_mode') || 'grid',

                init() {
                    this.$watch('viewMode', (value) => {
                        localStorage.setItem('releases_view_mode', value);
                    });

                    if (typeof TomSelect !== 'undefined') {
                        new TomSelect(this.$refs.genreSelect, {
                            plugins: ['remove_button'],
                            placeholder: 'Filter by genre...',
                            allowEmptyOption: true,
                            maxItems: 5,
                            onChange: (values) => {
                                this.selectedGenres = values.map(v => v === 'other' ? 'other' : parseInt(v));
                            }
                        });
                    }
                },

                matchesFilters(gameName, primaryGenreId, genreIds, isHighlight, isIndie, platformGroup) {
                    // Type filter
                    if (this.typeFilter === 'highlights' && !isHighlight) return false;
                    if (this.typeFilter === 'indies' && !isIndie) return false;

                    // Platform group filter
                    if (this.platformGroupFilter && platformGroup !== this.platformGroupFilter) return false;

                    // Search filter
                    if (this.searchQuery && !gameName.includes(this.searchQuery.toLowerCase())) return false;

                    // Genre filter
                    if (this.selectedGenres.length > 0) {
                        const hasNoGenre = primaryGenreId === null && genreIds.length === 0;
                        const matchesGenre = this.selectedGenres.some(g =>
                            (g === 'other' && hasNoGenre) ||
                            g === primaryGenreId ||
                            genreIds.includes(g)
                        );
                        if (!matchesGenre) return false;
                    }

                    return true;
                }
            };
        }
    </script>
    @endpush
@endsection
