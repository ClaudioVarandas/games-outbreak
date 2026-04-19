@extends('layouts.app')

@section('title', ($month ? \Carbon\Carbon::create($year, $month)->format('F') . ' ' : '') . "Game Releases {$year}")

@section('body-class', 'neon-body')

@section('content')
<div class="theme-neon overflow-x-hidden" x-data="releasesFilter()" x-init="init()">
    <div class="page-shell">

        @if($yearlyList)
            {{-- Filter Bar --}}
            <div class="neon-section-frame mb-6">
                {{-- Title + Year Nav --}}
                <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="neon-section-heading__title">{{ __('Game Releases') }}</h2>
                        <h1 class="text-3xl font-bold uppercase tracking-wide text-slate-100">
                            @if($month)
                                {{ \Carbon\Carbon::create($year, $month)->format('F') }} {{ $year }}
                            @else
                                Year {{ $year }}
                            @endif
                        </h1>
                        @if($yearlyList->description)
                            <p class="mt-1 text-sm text-slate-400">{{ $yearlyList->description }}</p>
                        @endif
                    </div>

                    {{-- Right column: [Year nav + ALL|TBA] row, then Month strip row --}}
                    <div class="flex w-full flex-col items-end gap-2 sm:w-auto">
                        {{-- Row 1: Year nav + ALL|TBA --}}
                        <div class="flex flex-nowrap items-center justify-end gap-2">
                            {{-- Year Navigation --}}
                            <div class="neon-btn-ghost inline-flex items-center divide-x divide-white/10 rounded-full overflow-hidden">
                                @if($prevYear)
                                    <a href="{{ route('releases.year', $prevYear) }}"
                                       class="inline-flex items-center gap-1 px-1 py-[2px] text-[0.5rem] font-bold uppercase tracking-[0.08em] text-slate-200 hover:text-cyan-300 transition-colors">
                                        <x-heroicon-o-arrow-left class="h-2.5 w-2.5" />
                                        {{ $prevYear }}
                                    </a>
                                @else
                                    <span class="inline-flex items-center gap-1 px-1 py-[2px] text-[0.5rem] font-bold uppercase tracking-[0.08em] text-slate-600 cursor-not-allowed">
                                        <x-heroicon-o-arrow-left class="h-2.5 w-2.5" />
                                        {{ $year - 1 }}
                                    </span>
                                @endif

                                <span class="neon-eyebrow px-1 py-[2px] text-[0.5rem]">{{ $year }}</span>

                                @if($nextYear)
                                    <a href="{{ route('releases.year', $nextYear) }}"
                                       class="inline-flex items-center gap-1 px-1 py-[2px] text-[0.5rem] font-bold uppercase tracking-[0.08em] text-slate-200 hover:text-cyan-300 transition-colors">
                                        {{ $nextYear }}
                                        <x-heroicon-o-arrow-right class="h-2.5 w-2.5" />
                                    </a>
                                @else
                                    <span class="inline-flex items-center gap-1 px-1 py-[2px] text-[0.5rem] font-bold uppercase tracking-[0.08em] text-slate-600 cursor-not-allowed">
                                        {{ $year + 1 }}
                                        <x-heroicon-o-arrow-right class="h-2.5 w-2.5" />
                                    </span>
                                @endif
                            </div>

                            {{-- ALL | TBA --}}
                            <div class="neon-btn-ghost inline-flex items-center divide-x divide-white/10 rounded-full overflow-hidden">
                                @if ($month === null && $only === null)
                                    <span class="neon-eyebrow px-1 py-[2px] text-[0.5rem]">{{ __('All') }}</span>
                                @else
                                    <a href="{{ route('releases.year', $year) }}"
                                       class="inline-flex items-center px-1 py-[2px] text-[0.5rem] font-bold uppercase tracking-[0.08em] text-slate-200 hover:text-cyan-300 transition-colors">
                                        {{ __('All') }}
                                    </a>
                                @endif

                                @if ($only === 'tba')
                                    <span class="neon-eyebrow px-1 py-[2px] text-[0.5rem]">TBA</span>
                                @else
                                    <a href="{{ route('releases.year', $year) }}?only=tba"
                                       class="inline-flex items-center px-1 py-[2px] text-[0.5rem] font-bold uppercase tracking-[0.08em] text-slate-200 hover:text-cyan-300 transition-colors">
                                        TBA
                                    </a>
                                @endif
                            </div>
                        </div>

                        {{-- Row 2: Month strip (scrollable on mobile) --}}
                        <div class="max-w-full self-stretch overflow-x-auto sm:self-end sm:overflow-visible">
                            <div class="neon-btn-ghost inline-flex items-center divide-x divide-white/10 rounded-full overflow-hidden whitespace-nowrap">
                                @for ($m = 1; $m <= 12; $m++)
                                    @if ($month === $m)
                                        <span class="neon-eyebrow px-1 py-[2px] text-[0.5rem]">
                                            {{ \Carbon\Carbon::create($year, $m)->format('M') }}
                                        </span>
                                    @else
                                        <a href="{{ route('releases.year.month', [$year, $m]) }}"
                                           class="inline-flex items-center px-1 py-[2px] text-[0.5rem] font-bold uppercase tracking-[0.08em] text-slate-200 hover:text-cyan-300 transition-colors">
                                            {{ \Carbon\Carbon::create($year, $m)->format('M') }}
                                        </a>
                                    @endif
                                @endfor
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-white/[0.06] mb-3"></div>

                <div class="flex flex-wrap items-center gap-3">

                    {{-- Mobile row 1: Highlights/Indies on the left, Grid/List on the right --}}
                    <div class="flex w-full items-center justify-between gap-3 sm:hidden">
                        <div class="flex items-center gap-2">
                            <button type="button" @click="typeFilter = typeFilter === 'highlights' ? 'all' : 'highlights'"
                                    :class="typeFilter === 'highlights' ? 'border-yellow-400/60 text-yellow-300 bg-yellow-400/10' : 'text-slate-400 hover:text-slate-200'"
                                    class="neon-btn-ghost rounded-full px-3 py-1 text-[0.7rem] font-bold uppercase tracking-[0.08em] transition">
                                {{ __('Highlights') }}
                            </button>
                            <button type="button" @click="typeFilter = typeFilter === 'indies' ? 'all' : 'indies'"
                                    :class="typeFilter === 'indies' ? 'border-orange-400/60 text-orange-300 bg-orange-400/10' : 'text-slate-400 hover:text-slate-200'"
                                    class="neon-btn-ghost rounded-full px-3 py-1 text-[0.7rem] font-bold uppercase tracking-[0.08em] transition">
                                {{ __('Indies') }}
                            </button>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" @click="viewMode = 'grid'"
                                    :class="viewMode === 'grid' ? 'border-cyan-400/60 text-cyan-300 bg-cyan-400/10' : 'text-slate-400 hover:text-slate-200'"
                                    class="neon-btn-ghost rounded-lg p-1.5 transition" title="Grid view">
                                <x-heroicon-o-squares-2x2 class="h-4 w-4" />
                            </button>
                            <button type="button" @click="viewMode = 'list'"
                                    :class="viewMode === 'list' ? 'border-cyan-400/60 text-cyan-300 bg-cyan-400/10' : 'text-slate-400 hover:text-slate-200'"
                                    class="neon-btn-ghost rounded-lg p-1.5 transition" title="List view">
                                <x-heroicon-o-bars-3 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    {{-- Desktop Type Pills --}}
                    <button type="button" @click="typeFilter = typeFilter === 'highlights' ? 'all' : 'highlights'"
                            :class="typeFilter === 'highlights' ? 'border-yellow-400/60 text-yellow-300 bg-yellow-400/10' : 'text-slate-400 hover:text-slate-200'"
                            class="hidden neon-btn-ghost rounded-full px-4 py-1.5 text-[0.72rem] font-bold uppercase tracking-[0.08em] transition sm:inline-flex">
                        {{ __('Highlights') }}
                    </button>
                    <button type="button" @click="typeFilter = typeFilter === 'indies' ? 'all' : 'indies'"
                            :class="typeFilter === 'indies' ? 'border-orange-400/60 text-orange-300 bg-orange-400/10' : 'text-slate-400 hover:text-slate-200'"
                            class="hidden neon-btn-ghost rounded-full px-4 py-1.5 text-[0.72rem] font-bold uppercase tracking-[0.08em] transition sm:inline-flex">
                        {{ __('Indies') }}
                    </button>

                    {{-- Genre Multi-Select (hidden) --}}
                    <div class="hidden">
                        <select x-ref="genreSelect" multiple>
                            @foreach($genres as $genre)
                                <option value="{{ $genre->id }}">{{ $genre->name }}</option>
                            @endforeach
                            @if($otherGenre)
                                <option value="other">Other</option>
                            @endif
                        </select>
                    </div>

                    {{-- Search — centered full-width row on mobile, flex-1 centered on desktop --}}
                    <div class="flex w-full justify-center sm:w-auto sm:flex-1">
                        <div class="relative">
                            <input type="text"
                                   x-model="searchQuery"
                                   placeholder="{{ __('Search games...') }}"
                                   class="w-56 rounded-full border border-white/10 bg-slate-900/60 py-1.5 pl-9 pr-4 text-[0.72rem] text-slate-200 placeholder-slate-500 focus:border-cyan-400/50 focus:outline-none focus:ring-0 md:w-80">
                            <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-500" />
                        </div>
                    </div>

                    <div class="hidden h-4 w-px bg-white/10 sm:block"></div>

                    {{-- Desktop View Toggle --}}
                    <div class="hidden items-center gap-1 sm:flex @if($month) ml-auto @endif">
                        <button type="button" @click="viewMode = 'grid'"
                                :class="viewMode === 'grid' ? 'border-cyan-400/60 text-cyan-300 bg-cyan-400/10' : 'text-slate-400 hover:text-slate-200'"
                                class="neon-btn-ghost rounded-lg p-2 transition" title="Grid view">
                            <x-heroicon-o-squares-2x2 class="h-4 w-4" />
                        </button>
                        <button type="button" @click="viewMode = 'list'"
                                :class="viewMode === 'list' ? 'border-cyan-400/60 text-cyan-300 bg-cyan-400/10' : 'text-slate-400 hover:text-slate-200'"
                                class="neon-btn-ghost rounded-lg p-2 transition" title="List view">
                            <x-heroicon-o-bars-3 class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            {{-- Month Sections --}}
            @if(count($gamesByMonth) > 0)
                @php
                    // TBA first, then months in chronological order
                    $tbaSection = isset($gamesByMonth['tba']) ? ['tba' => $gamesByMonth['tba']] : [];
                    $monthSections = array_filter($gamesByMonth, fn($k) => $k !== 'tba', ARRAY_FILTER_USE_KEY);
                    $orderedSections = $tbaSection + $monthSections;
                @endphp

                @foreach($orderedSections as $monthKey => $monthData)
                    @php
                        $monthGames = collect($monthData['games'])->map(function ($game) {
                            $pivotReleaseDate = $game->pivot->release_date ?? null;
                            if ($pivotReleaseDate && is_string($pivotReleaseDate)) {
                                $pivotReleaseDate = \Carbon\Carbon::parse($pivotReleaseDate);
                            }
                            return [
                                'game' => $game,
                                'displayDate' => $pivotReleaseDate ?? $game->first_release_date,
                                'displayPlatforms' => $game->pivot->platforms ?? null,
                                'primaryGenreId' => $game->pivot->primary_genre_id,
                                'genreIds' => is_string($game->pivot->genre_ids) ? json_decode($game->pivot->genre_ids, true) ?? [] : ($game->pivot->genre_ids ?? []),
                                'isHighlight' => (bool) $game->pivot->is_highlight,
                                'isIndie' => (bool) $game->pivot->is_indie,
                                'platformGroup' => $game->pivot->platform_group ?? '',
                            ];
                        });
                    @endphp

                    <div class="neon-section-frame mb-6 scroll-mt-24" id="month-{{ $monthKey }}">

                        {{-- Section heading --}}
                        <div class="mb-5 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                @if($monthKey !== 'tba' && !$month)
                                    <a href="{{ route('releases.year.month', [$year, $monthData['month_number']]) }}"
                                       class="neon-section-heading__title hover:text-cyan-300 transition-colors">
                                        {{ $monthData['label'] }}
                                    </a>
                                @else
                                    <h2 class="neon-section-heading__title">{{ $monthData['label'] }}</h2>
                                @endif
                                <span class="neon-eyebrow">
                                    {{ count($monthData['games']) }} {{ Str::plural('game', count($monthData['games'])) }}
                                </span>
                            </div>
                            @if($monthKey !== 'tba' && !$month)
                                <a href="{{ route('releases.year.month', [$year, $monthData['month_number']]) }}"
                                   class="inline-flex items-center gap-1 text-[0.72rem] font-semibold uppercase tracking-[0.06em] text-cyan-400 hover:text-cyan-300 transition-colors">
                                    View month
                                    <x-heroicon-o-arrow-right class="h-2.5 w-2.5" />
                                </a>
                            @endif
                        </div>

                        {{-- Grid View --}}
                        <div x-show="viewMode === 'grid'"
                             class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6"
                             data-month="{{ $monthKey }}">
                            @foreach($monthGames as $entry)
                                <div x-show="matchesFilters(@js(strtolower($entry['game']->name)), {{ $entry['primaryGenreId'] ? $entry['primaryGenreId'] : 'null' }}, @js(array_map('intval', $entry['genreIds'])), {{ $entry['isHighlight'] ? 'true' : 'false' }}, {{ $entry['isIndie'] ? 'true' : 'false' }}, @js($entry['platformGroup']))"
                                     data-game-card>
                                    <x-game-card
                                        :game="$entry['game']"
                                        :displayReleaseDate="$entry['displayDate']"
                                        :displayPlatforms="$entry['displayPlatforms']"
                                        variant="neon"
                                        aspectRatio="3/4"
                                        :platformEnums="$platformEnums" />
                                </div>
                            @endforeach
                        </div>

                        {{-- List View --}}
                        <div x-show="viewMode === 'list'" class="flex flex-col gap-1.5" data-month="{{ $monthKey }}">
                            @foreach($monthGames as $entry)
                                <div x-show="matchesFilters(@js(strtolower($entry['game']->name)), {{ $entry['primaryGenreId'] ? $entry['primaryGenreId'] : 'null' }}, @js(array_map('intval', $entry['genreIds'])), {{ $entry['isHighlight'] ? 'true' : 'false' }}, {{ $entry['isIndie'] ? 'true' : 'false' }}, @js($entry['platformGroup']))"
                                     data-game-card
                                     class="overflow-hidden rounded-xl border border-white/[0.06] bg-slate-900/50">
                                    <x-game-card
                                        :game="$entry['game']"
                                        :displayReleaseDate="$entry['displayDate']"
                                        :displayPlatforms="$entry['displayPlatforms']"
                                        variant="table-row"
                                        :platformEnums="$platformEnums" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @else
                <div class="neon-panel px-6 py-10 text-center text-sm uppercase tracking-[0.08em] text-slate-400">
                    @if($month)
                        No games found for {{ \Carbon\Carbon::create($year, $month)->format('F Y') }}.
                    @else
                        No games in this list yet.
                    @endif
                </div>
            @endif

        @else
            {{-- No list for this year --}}
            <div class="neon-panel px-8 py-14 text-center">
                <p class="neon-eyebrow mb-4 justify-center">Game Releases {{ $year }}</p>
                <p class="text-sm uppercase tracking-[0.08em] text-slate-400 mb-6">
                    No active releases list for {{ $year }}.
                </p>
                @if($availableYears->count() > 0)
                    <div class="flex flex-wrap justify-center gap-2">
                        @foreach($availableYears as $availableYear)
                            <a href="{{ route('releases.year', $availableYear) }}" class="neon-btn">
                                {{ $availableYear }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

    </div>
</div>

@push('scripts')
<script>
    function releasesFilter() {
        return {
            typeFilter: 'all',
            platformGroupFilter: '',
            searchQuery: '',
            selectedGenres: [],
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
                if (this.typeFilter === 'highlights' && !isHighlight) return false;
                if (this.typeFilter === 'indies' && !isIndie) return false;
                if (this.platformGroupFilter && platformGroup !== this.platformGroupFilter) return false;
                if (this.searchQuery && !gameName.includes(this.searchQuery.toLowerCase())) return false;
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
