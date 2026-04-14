@extends('layouts.app')

@section('title', $gameList->name . ' - Games List')

@section('body-class', 'neon-body')

@push('head')
    {{-- Open Graph Meta Tags --}}
    <meta property="og:title" content="{{ $gameList->name }} | GamesOutbreak">
    <meta property="og:description" content="Browse {{ $gameList->games->count() }}+ games from {{ $gameList->name }}. Filter by platform, genre, and more.">
    <meta property="og:image" content="{{ $gameList->og_image_url ?? asset('images/og-default.png') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $gameList->name }}">
    <meta name="twitter:description" content="Browse {{ $gameList->games->count() }}+ games">
    <meta name="twitter:image" content="{{ $gameList->og_image_url ?? asset('images/og-default.png') }}">

    {{-- Meta Description --}}
    <meta name="description" content="Discover all {{ $gameList->games->count() }} games from {{ $gameList->name }}. Filter by platform, genre, and more.">

    {{-- JSON-LD Schema --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "ItemList",
        "name": "{{ $gameList->name }}",
        "description": "{{ $gameList->description ?? 'A curated list of games' }}",
        "url": "{{ url()->current() }}",
        "numberOfItems": {{ $gameList->games->count() }},
        "itemListElement": [
            @foreach($gameList->games as $index => $game)
            {
                "@@type": "ListItem",
                "position": {{ $index + 1 }},
                "item": {
                    "@@type": "VideoGame",
                    "name": "{{ addslashes($game->name) }}",
                    "genre": {!! json_encode($game->genres->pluck('name')->toArray()) !!},
                    "gamePlatform": {!! json_encode($game->platforms->pluck('name')->toArray()) !!},
                    "image": "{{ $game->getCoverUrl() }}",
                    "url": "{{ route('game.show', $game->slug) }}"
                }
            }@if(!$loop->last),@endif
            @endforeach
        ]
    }
    </script>
@endpush

@section('content')
    @php
        $currentUser = auth()->user();
        $canEdit = false;
        if ($currentUser) {
            $canEdit = $gameList->canBeEditedBy($currentUser);
        }
        $isSystemList = $gameList->is_system && ($readOnly ?? false);

        $eventHasStarted = true;
        if ($gameList->isEvents() && $gameList->getEventTime()) {
            $eventHasStarted = $gameList->getEventTime()->isPast();
        }
    @endphp

<div class="theme-neon overflow-x-hidden">

    {{-- System List with Filtering --}}
    @if($isSystemList && isset($gamesData))
        <div x-data="listFilter(
            {{ Js::from($gamesData) }},
            {{ Js::from($initialFilters ?? []) }},
            {{ Js::from($filterOptions ?? []) }},
            {{ Js::from([
                'enabled' => auth()->check(),
                'backlogGameIds' => $backlogGameIds ?? [],
                'wishlistGameIds' => $wishlistGameIds ?? [],
                'csrfToken' => csrf_token(),
                'username' => auth()->user()?->username ?? '',
            ]) }}
        )" class="min-h-screen">

            {{-- Event Hero --}}
            @if($gameList->isEvents() && ($gameList->hasVideo() || $gameList->getEventTime()))
                <div class="relative overflow-hidden border-b border-white/[0.07]">
                    @if($gameList->og_image_path)
                        <div class="absolute inset-0">
                            <img src="{{ $gameList->og_image_url }}" alt="" class="w-full h-full object-cover opacity-20">
                            <div class="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-950/85 to-transparent"></div>
                        </div>
                    @else
                        <div class="absolute inset-0 bg-gradient-to-b from-slate-950 to-slate-900"></div>
                    @endif

                    <div class="page-shell py-10 relative z-10">
                        <div class="flex flex-col lg:flex-row gap-8 items-start">
                            {{-- Video Embed --}}
                            @if($gameList->hasVideo())
                                <div class="w-full lg:w-3/5 neon-section-frame overflow-hidden p-2">
                                    <x-video-embed :url="$gameList->getVideoUrl()" />
                                </div>
                            @endif

                            {{-- Event Info --}}
                            <div class="w-full {{ $gameList->hasVideo() ? 'lg:w-2/5' : '' }}">
                                <h1 class="text-3xl font-bold uppercase tracking-wide text-slate-100 mb-4">
                                    {{ $gameList->name }}
                                </h1>

                                @if($gameList->getEventTime())
                                    <div class="mb-5" x-data="{
                                        localTime: '',
                                        init() {
                                            const eventTime = new Date('{{ $gameList->getEventTime()->toIso8601String() }}');
                                            this.localTime = eventTime.toLocaleString(undefined, {
                                                weekday: 'short',
                                                month: 'short',
                                                day: 'numeric',
                                                year: 'numeric',
                                                hour: 'numeric',
                                                minute: '2-digit',
                                                timeZoneName: 'short'
                                            });
                                        }
                                    }">
                                        <div class="flex items-center gap-2 text-orange-400 mb-1">
                                            <x-heroicon-o-calendar class="w-4 h-4" />
                                            <span class="text-[0.8rem] font-bold uppercase tracking-[0.06em]">{{ $gameList->getEventTime()->format('M j, Y \a\t g:i A') }} {{ strtoupper($gameList->getEventTimezone() ? \Carbon\Carbon::now($gameList->getEventTimezone())->format('T') : '') }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-slate-400 text-sm">
                                            <x-heroicon-o-globe-alt class="w-4 h-4" />
                                            <span>Your time: <span x-text="localTime" class="text-slate-200"></span></span>
                                        </div>
                                    </div>
                                @endif

                                @if($gameList->hasSocialLinks())
                                    @php $socialLinks = $gameList->getSocialLinks(); @endphp
                                    <div class="flex items-center gap-3 mb-5">
                                        @if(!empty($socialLinks['twitter']))
                                            <a href="{{ $socialLinks['twitter'] }}" target="_blank" rel="noopener"
                                               class="w-9 h-9 rounded-full border border-white/10 bg-white/5 hover:bg-black hover:border-white/20 flex items-center justify-center text-slate-300 transition"
                                               title="Twitter / X">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                            </a>
                                        @endif
                                        @if(!empty($socialLinks['youtube']))
                                            <a href="{{ $socialLinks['youtube'] }}" target="_blank" rel="noopener"
                                               class="w-9 h-9 rounded-full border border-white/10 bg-white/5 hover:bg-red-600 hover:border-red-600 flex items-center justify-center text-slate-300 transition"
                                               title="YouTube">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                            </a>
                                        @endif
                                        @if(!empty($socialLinks['twitch']))
                                            <a href="{{ $socialLinks['twitch'] }}" target="_blank" rel="noopener"
                                               class="w-9 h-9 rounded-full border border-white/10 bg-white/5 hover:bg-purple-600 hover:border-purple-600 flex items-center justify-center text-slate-300 transition"
                                               title="Twitch">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0L1.714 4.286v15.428h5.143V24l4.286-4.286h3.428L22.286 12V0zm14.571 11.143l-3.428 3.428h-3.429l-3 3v-3H6.857V1.714h13.714Z"/></svg>
                                            </a>
                                        @endif
                                        @if(!empty($socialLinks['discord']))
                                            <a href="{{ $socialLinks['discord'] }}" target="_blank" rel="noopener"
                                               class="w-9 h-9 rounded-full border border-white/10 bg-white/5 hover:bg-indigo-600 hover:border-indigo-600 flex items-center justify-center text-slate-300 transition"
                                               title="Discord">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418Z"/></svg>
                                            </a>
                                        @endif
                                    </div>
                                @endif

                                @if($gameList->description)
                                    <p class="text-slate-300 text-sm leading-relaxed mb-4">
                                        {{ $gameList->description }}
                                    </p>
                                @endif

                                @if($gameList->getEventAbout())
                                    <div x-data="{ expanded: false }" class="mt-4 pt-4 border-t border-white/[0.08]">
                                        <button @click="expanded = !expanded" class="flex items-center gap-2 text-cyan-400 hover:text-cyan-300 transition mb-2 md:hidden text-xs font-bold uppercase tracking-[0.08em]">
                                            <span x-text="expanded ? 'Hide details' : 'Show details'"></span>
                                            <x-heroicon-o-chevron-down class="w-3.5 h-3.5 transition-transform" ::class="expanded && 'rotate-180'" />
                                        </button>
                                        <div class="text-slate-400 text-sm leading-relaxed" :class="{ 'hidden md:block': !expanded }">
                                            {!! nl2br(e($gameList->getEventAbout())) !!}
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

            @else
                {{-- Standard Header --}}
                <div class="border-b border-white/[0.07] bg-slate-950/60">
                    <div class="page-shell py-8">
                        <h1 class="text-3xl font-bold uppercase tracking-wide text-slate-100">
                            {{ $gameList->name }}
                        </h1>
                        @if($gameList->description)
                            <p class="mt-2 text-sm text-slate-400 max-w-3xl">
                                {{ $gameList->description }}
                            </p>
                        @endif
                    </div>
                </div>
            @endif

            @if($eventHasStarted)
                {{-- Stats Bar --}}
                <div class="sticky top-[6.5rem] z-[35] border-b border-white/[0.07] bg-slate-900/95 backdrop-blur-xl">
                    <div class="page-shell py-3">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div class="flex items-center gap-4 text-sm">
                                <span class="text-slate-400 text-xs">
                                    Showing <strong class="text-slate-100" x-text="stats.filtered"></strong>
                                    of <strong class="text-slate-100">{{ count($gamesData) }}</strong> games
                                </span>
                                <template x-if="stats.filtered !== stats.total">
                                    <button @click="clearAllFilters()" class="text-cyan-400 hover:text-cyan-300 font-semibold text-xs uppercase tracking-[0.06em] transition">
                                        Clear filters
                                    </button>
                                </template>
                            </div>

                            <div class="flex items-center gap-3">
                                {{-- View Toggle --}}
                                <div class="flex items-center gap-1 border border-white/10 rounded-full p-1 bg-white/5">
                                    <button @click="setViewMode('grid')"
                                            :class="viewMode === 'grid' ? 'bg-orange-500 text-white' : 'text-slate-400 hover:text-slate-200'"
                                            class="p-1.5 rounded-full transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                                        </svg>
                                    </button>
                                    <button @click="setViewMode('list')"
                                            :class="viewMode === 'list' ? 'bg-orange-500 text-white' : 'text-slate-400 hover:text-slate-200'"
                                            class="p-1.5 rounded-full transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                                        </svg>
                                    </button>
                                </div>

                                {{-- Mobile Filter Button --}}
                                <button @click="openMobileFilters()"
                                        class="lg:hidden inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-bold uppercase tracking-[0.06em] text-slate-300 transition hover:border-white/20 hover:text-slate-100">
                                    <x-heroicon-o-funnel class="w-4 h-4" />
                                    <span>Filters</span>
                                    <template x-if="hasActiveFilters">
                                        <span class="bg-orange-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full" x-text="activeFilterPills.length"></span>
                                    </template>
                                </button>
                            </div>
                        </div>

                        {{-- Active Filter Pills --}}
                        <template x-if="hasActiveFilters">
                            <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-white/[0.06]">
                                <template x-for="pill in activeFilterPills" :key="pill.type + '-' + pill.id">
                                    <button @click="removeFilter(pill.type, pill.id)"
                                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border border-cyan-400/30 bg-cyan-400/10 text-cyan-300 text-xs font-semibold hover:bg-cyan-400/20 transition">
                                        <span x-text="pill.name"></span>
                                        <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                                    </button>
                                </template>
                                <button @click="clearAllFilters()" class="text-slate-400 hover:text-slate-200 text-xs transition">
                                    Clear all
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Main Content --}}
                <div class="page-shell py-8">
                    <div class="flex gap-6">
                        {{-- Filter Sidebar (Desktop) --}}
                        <aside class="hidden lg:block w-60 shrink-0">
                            <div class="sticky top-24 neon-section-frame space-y-6">
                                <h2 class="neon-eyebrow">Filters</h2>

                                @if(count($filterOptions['platforms'] ?? []) > 0)
                                    <div>
                                        <h3 class="mb-3 text-[0.68rem] font-bold uppercase tracking-[0.1em] text-slate-500">Platform</h3>
                                        <div class="space-y-2">
                                            @foreach($filterOptions['platforms'] as $platform)
                                                <label class="flex cursor-pointer items-center gap-2 text-slate-300 transition hover:text-slate-100"
                                                       :class="isSelected('platforms', {{ $platform['id'] }}) && 'text-cyan-300'">
                                                    <input type="checkbox"
                                                           :checked="isSelected('platforms', {{ $platform['id'] }})"
                                                           @change="toggleFilter('platforms', {{ $platform['id'] }})"
                                                           class="rounded border-white/20 bg-slate-800 accent-cyan-400">
                                                    <span class="text-sm">{{ $platform['name'] }}</span>
                                                    <span class="ml-auto text-slate-500 text-xs">({{ $platform['count'] }})</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if(count($filterOptions['genres'] ?? []) > 0)
                                    <div>
                                        <h3 class="mb-3 text-[0.68rem] font-bold uppercase tracking-[0.1em] text-slate-500">Genre</h3>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($filterOptions['genres'] as $genre)
                                                <button @click="toggleFilter('genres', {{ $genre['id'] }})"
                                                        :class="isSelected('genres', {{ $genre['id'] }}) ? 'border-cyan-400/40 bg-cyan-400/15 text-cyan-300' : 'border-white/10 bg-white/5 text-slate-400 hover:text-slate-200'"
                                                        class="rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wider transition">
                                                    {{ $genre['name'] }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if(count($filterOptions['gameTypes'] ?? []) > 1)
                                    <div>
                                        <h3 class="mb-3 text-[0.68rem] font-bold uppercase tracking-[0.1em] text-slate-500">Game Type</h3>
                                        <div class="space-y-2">
                                            @foreach($filterOptions['gameTypes'] as $gameType)
                                                <label class="flex cursor-pointer items-center gap-2 text-slate-300 transition hover:text-slate-100"
                                                       :class="isSelected('gameTypes', {{ $gameType['id'] }}) && 'text-cyan-300'">
                                                    <input type="checkbox"
                                                           :checked="isSelected('gameTypes', {{ $gameType['id'] }})"
                                                           @change="toggleFilter('gameTypes', {{ $gameType['id'] }})"
                                                           class="rounded border-white/20 bg-slate-800 accent-cyan-400">
                                                    <span class="text-sm">{{ $gameType['name'] }}</span>
                                                    <span class="ml-auto text-slate-500 text-xs">({{ $gameType['count'] }})</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if(count($filterOptions['modes'] ?? []) > 0)
                                    <div>
                                        <h3 class="mb-3 text-[0.68rem] font-bold uppercase tracking-[0.1em] text-slate-500">Game Mode</h3>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($filterOptions['modes'] as $mode)
                                                <button @click="toggleFilter('modes', {{ $mode['id'] }})"
                                                        :class="isSelected('modes', {{ $mode['id'] }}) ? 'border-cyan-400/40 bg-cyan-400/15 text-cyan-300' : 'border-white/10 bg-white/5 text-slate-400 hover:text-slate-200'"
                                                        class="rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wider transition">
                                                    {{ $mode['name'] }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if(count($filterOptions['perspectives'] ?? []) > 0)
                                    <div>
                                        <h3 class="mb-3 text-[0.68rem] font-bold uppercase tracking-[0.1em] text-slate-500">Perspective</h3>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($filterOptions['perspectives'] as $perspective)
                                                <button @click="toggleFilter('perspectives', {{ $perspective['id'] }})"
                                                        :class="isSelected('perspectives', {{ $perspective['id'] }}) ? 'border-cyan-400/40 bg-cyan-400/15 text-cyan-300' : 'border-white/10 bg-white/5 text-slate-400 hover:text-slate-200'"
                                                        class="rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wider transition">
                                                    {{ $perspective['name'] }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </aside>

                        {{-- Game Grid / List --}}
                        <main class="flex-1 min-w-0">
                            @if($gameList->isEvents() && !empty($gamesByMonth))
                                {{-- Events: Month-grouped layout --}}
                                @foreach($gamesByMonth as $monthKey => $monthData)
                                    <div class="pt-10 first:pt-0">
                                        {{-- Month Header --}}
                                        <div class="mb-8 flex items-center gap-4">
                                            <div class="h-px flex-1 bg-gradient-to-r from-orange-400/40 to-transparent"></div>
                                            <div class="text-center">
                                                <h3 class="neon-eyebrow neon-eyebrow--orange">
                                                    {{ $monthData['label'] }}
                                                </h3>
                                                <span class="text-xs text-slate-500">
                                                    {{ count($monthData['games']) }} {{ Str::plural('game', count($monthData['games'])) }}
                                                </span>
                                            </div>
                                            <div class="h-px flex-1 bg-gradient-to-l from-orange-400/40 to-transparent"></div>
                                        </div>

                                        {{-- Grid View --}}
                                        <div x-show="viewMode === 'grid'" class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                                            @foreach($monthData['games'] as $game)
                                                @php
                                                    $isTba = (bool) ($game->pivot->is_tba ?? false);
                                                    $pivotReleaseDate = $game->pivot->release_date ?? null;
                                                    if ($pivotReleaseDate && is_string($pivotReleaseDate)) {
                                                        $pivotReleaseDate = \Carbon\Carbon::parse($pivotReleaseDate);
                                                    }
                                                    $displayDate = $isTba ? null : ($pivotReleaseDate ?? $game->first_release_date);
                                                    $pivotPlatforms = $game->pivot->platforms ?? null;
                                                @endphp
                                                <div x-show="gameMatchesFilters({{ $game->id }})">
                                                    <x-game-card
                                                        :game="$game"
                                                        :displayReleaseDate="$displayDate"
                                                        :displayPlatforms="$pivotPlatforms"
                                                        variant="neon"
                                                        layout="below"
                                                        aspectRatio="3/4"
                                                        :platformEnums="$platformEnums"
                                                        :isTba="$isTba"
                                                    />
                                                </div>
                                            @endforeach
                                        </div>

                                        {{-- List View --}}
                                        <div x-show="viewMode === 'list'" class="space-y-2">
                                            @foreach($monthData['games'] as $game)
                                                @php
                                                    $isTba = (bool) ($game->pivot->is_tba ?? false);
                                                    $pivotReleaseDate = $game->pivot->release_date ?? null;
                                                    if ($pivotReleaseDate && is_string($pivotReleaseDate)) {
                                                        $pivotReleaseDate = \Carbon\Carbon::parse($pivotReleaseDate);
                                                    }
                                                    $displayDate = $isTba ? null : ($pivotReleaseDate ?? $game->first_release_date);
                                                    $pivotPlatforms = $game->pivot->platforms ?? null;
                                                @endphp
                                                <div x-show="gameMatchesFilters({{ $game->id }})">
                                                    <x-game-card
                                                        :game="$game"
                                                        :displayReleaseDate="$displayDate"
                                                        :displayPlatforms="$pivotPlatforms"
                                                        variant="table-row"
                                                        :platformEnums="$platformEnums"
                                                        :isTba="$isTba"
                                                    />
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach

                                @if(count($gamesByMonth) === 0)
                                    <div class="neon-panel bg-slate-800/40 px-6 py-16 text-center">
                                        <p class="text-slate-400">No games in this event yet.</p>
                                    </div>
                                @endif

                                <div x-show="filteredGames.length === 0" x-cloak class="neon-panel bg-slate-800/40 px-6 py-16 text-center">
                                    <p class="text-slate-400 mb-4">No games match your current filters.</p>
                                    <button @click="clearAllFilters()" class="neon-btn-ghost rounded-full px-6 py-2 text-sm">
                                        Clear All Filters
                                    </button>
                                </div>

                            @else
                                {{-- Standard System List: Alpine.js filtered view --}}

                                {{-- Grid View --}}
                                <div x-show="viewMode === 'grid'" x-cloak
                                     class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"
                                     :class="isFiltering && 'opacity-50 transition-opacity'">
                                    <template x-for="game in filteredGames" :key="game.id">
                                        <div class="neon-card group/card overflow-hidden">
                                            <a :href="'/game/' + game.slug" class="block">
                                                <div class="relative aspect-[3/4] overflow-hidden rounded-[inherit]">
                                                    <img :src="game.cover_url"
                                                         :alt="game.name"
                                                         class="h-full w-full object-cover transition-transform duration-500 group-hover/card:scale-105"
                                                         loading="lazy">
                                                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>

                                                    @auth
                                                    <div class="absolute inset-0 z-20 flex flex-col items-center justify-center opacity-0 transition-opacity duration-300 group-hover/card:opacity-100 pointer-events-none">
                                                        <div class="absolute inset-0 bg-black/20 backdrop-blur-sm rounded-[inherit]"></div>
                                                        <div class="relative mb-3 px-3">
                                                            <h3 class="text-center text-sm font-bold text-white line-clamp-2 drop-shadow-lg" x-text="game.name"></h3>
                                                        </div>
                                                        <div class="relative flex items-center gap-3 pointer-events-auto">
                                                            <button @click.stop.prevent="toggleBacklog(game)"
                                                                    :disabled="isLoading(game.id, 'backlog')"
                                                                    class="group/btn flex h-12 w-12 items-center justify-center rounded-full bg-transparent text-white transition-all duration-200 hover:bg-white/10 hover:scale-110 disabled:opacity-50 disabled:cursor-not-allowed"
                                                                    :class="isInBacklog(game.id) ? 'border-2 border-white/50' : ''"
                                                                    :title="isInBacklog(game.id) ? 'Remove from Backlog' : 'Add to Backlog'">
                                                                <div class="relative h-6 w-6" x-show="!isLoading(game.id, 'backlog')">
                                                                    <svg x-show="isInBacklog(game.id)" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                                                        <path d="M5.625 3.75a2.625 2.625 0 1 0 0 5.25h12.75a2.625 2.625 0 0 0 0-5.25H5.625ZM3.75 11.25a.75.75 0 0 0 0 1.5h16.5a.75.75 0 0 0 0-1.5H3.75ZM3 15.75a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75ZM3.75 18.75a.75.75 0 0 0 0 1.5h16.5a.75.75 0 0 0 0-1.5H3.75Z" />
                                                                    </svg>
                                                                    <template x-if="!isInBacklog(game.id)">
                                                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                                                                        </svg>
                                                                    </template>
                                                                </div>
                                                                <svg x-show="isLoading(game.id, 'backlog')" class="h-5 w-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                                </svg>
                                                            </button>

                                                            <button @click.stop.prevent="toggleWishlist(game)"
                                                                    :disabled="isLoading(game.id, 'wishlist')"
                                                                    class="group/btn flex h-12 w-12 items-center justify-center rounded-full bg-transparent text-white transition-all duration-200 hover:bg-white/10 hover:scale-110 disabled:opacity-50 disabled:cursor-not-allowed"
                                                                    :class="isInWishlist(game.id) ? 'border-2 border-white/50' : ''"
                                                                    :title="isInWishlist(game.id) ? 'Remove from Wishlist' : 'Add to Wishlist'">
                                                                <div class="relative h-6 w-6" x-show="!isLoading(game.id, 'wishlist')">
                                                                    <svg x-show="isInWishlist(game.id)" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                                                        <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z" />
                                                                    </svg>
                                                                    <template x-if="!isInWishlist(game.id)">
                                                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                                                                        </svg>
                                                                    </template>
                                                                </div>
                                                                <svg x-show="isLoading(game.id, 'wishlist')" class="h-5 w-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    @else
                                                    <div class="absolute inset-0 z-20 flex flex-col items-center justify-center opacity-0 transition-opacity duration-300 group-hover/card:opacity-100 pointer-events-none">
                                                        <div class="absolute inset-0 bg-black/20 backdrop-blur-sm rounded-[inherit]"></div>
                                                        <div class="relative flex gap-3 pointer-events-auto">
                                                            <a :href="userUrl(game)" @click.stop
                                                               class="flex h-12 w-12 items-center justify-center rounded-full border border-white/30 bg-transparent text-white opacity-90 transition-all duration-200 hover:bg-white/10 hover:scale-110 hover:border-white/50"
                                                               title="Login to add to Backlog">
                                                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                                                                </svg>
                                                            </a>
                                                            <a :href="userUrl(game)" @click.stop
                                                               class="flex h-12 w-12 items-center justify-center rounded-full border border-white/30 bg-transparent text-white opacity-90 transition-all duration-200 hover:bg-white/10 hover:scale-110 hover:border-white/50"
                                                               title="Login to add to Wishlist">
                                                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                                                                </svg>
                                                            </a>
                                                        </div>
                                                    </div>
                                                    @endguest

                                                    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-3">
                                                        <h3 class="mb-1 font-bold text-white text-sm line-clamp-2" x-text="game.name"></h3>
                                                        <div class="flex items-center justify-between">
                                                            <span class="rounded px-1.5 py-0.5 text-xs font-medium text-white" :class="game.game_type.color" x-text="game.game_type.name"></span>
                                                            <span class="text-xs font-medium text-white/80" x-text="game.release_date_formatted"></span>
                                                        </div>
                                                    </div>

                                                    {{-- Platform badges (mobile: 3, desktop: all) --}}
                                                    <div class="absolute top-2 left-2 flex flex-wrap gap-1 md:hidden">
                                                        <template x-for="platform in game.platforms.slice(0, 3)" :key="'mob-' + platform.id">
                                                            <span class="rounded border border-white/20 bg-black/50 px-1.5 py-0.5 text-xs font-bold text-white backdrop-blur-sm"
                                                                  :class="'bg-' + platform.color + '-600/80'"
                                                                  x-text="platform.name"></span>
                                                        </template>
                                                        <span x-show="game.platforms.length > 3"
                                                              class="rounded border border-white/20 bg-slate-700/80 px-1.5 py-0.5 text-xs font-bold text-white/80 backdrop-blur-sm"
                                                              x-text="'+' + (game.platforms.length - 3)"></span>
                                                    </div>
                                                    <div class="absolute top-2 left-2 hidden md:flex flex-wrap gap-1">
                                                        <template x-for="platform in game.platforms" :key="'dsk-' + platform.id">
                                                            <span class="rounded border border-white/20 bg-black/50 px-1.5 py-0.5 text-xs font-bold text-white backdrop-blur-sm"
                                                                  :class="'bg-' + platform.color + '-600/80'"
                                                                  x-text="platform.name"></span>
                                                        </template>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                    </template>
                                </div>

                                {{-- List View --}}
                                <div x-show="viewMode === 'list'" x-cloak
                                     class="space-y-2"
                                     :class="isFiltering && 'opacity-50 transition-opacity'">
                                    <template x-for="game in filteredGames" :key="game.id">
                                        <div class="neon-card flex items-center gap-4 p-3">
                                            <a :href="'/game/' + game.slug" class="shrink-0">
                                                <div class="h-14 w-10 overflow-hidden rounded-lg bg-slate-800">
                                                    <img :src="game.cover_url"
                                                         :alt="game.name"
                                                         class="h-full w-full object-cover"
                                                         loading="lazy">
                                                </div>
                                            </a>
                                            <div class="min-w-0 flex-1">
                                                <a :href="'/game/' + game.slug" class="block">
                                                    <h3 class="truncate text-sm font-semibold text-slate-100 transition-colors hover:text-orange-400" x-text="game.name"></h3>
                                                    <span class="mt-1 inline-block rounded px-1.5 py-0.5 text-xs font-medium text-white" :class="game.game_type.color" x-text="game.game_type.name"></span>
                                                </a>
                                            </div>
                                            <div class="hidden sm:flex shrink-0 flex-wrap items-center justify-end gap-1 max-w-xs">
                                                <template x-for="platform in game.platforms" :key="platform.id">
                                                    <span class="rounded px-1.5 py-0.5 text-xs font-bold text-white"
                                                          :class="'bg-' + platform.color + '-600'"
                                                          x-text="platform.name"></span>
                                                </template>
                                            </div>
                                            <div class="shrink-0 text-right">
                                                <span class="whitespace-nowrap text-xs font-medium text-slate-400" x-text="game.release_date_formatted"></span>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                {{-- Empty State --}}
                                <div x-show="filteredGames.length === 0" x-cloak class="neon-panel bg-slate-800/40 px-6 py-16 text-center">
                                    <p class="text-slate-400 mb-4">No games match your current filters.</p>
                                    <button @click="clearAllFilters()" class="neon-btn-ghost rounded-full px-6 py-2 text-sm">
                                        Clear All Filters
                                    </button>
                                </div>
                            @endif
                        </main>
                    </div>
                </div>

            @else
                {{-- Event Not Started Yet --}}
                <div class="page-shell py-16 md:py-24">
                    <div class="mx-auto max-w-2xl text-center">
                        <div class="mb-8">
                            <div class="inline-flex h-24 w-24 items-center justify-center rounded-full border border-orange-400/30 bg-orange-400/10">
                                <x-heroicon-o-clock class="h-12 w-12 text-orange-400" />
                            </div>
                        </div>

                        <h2 class="mb-4 text-3xl font-bold uppercase tracking-wide text-slate-100 md:text-4xl">
                            The Event Hasn't Started Yet
                        </h2>
                        <p class="mb-8 text-slate-400">
                            The games will be revealed when the event begins. Stay tuned!
                        </p>

                        @if($gameList->getEventTime())
                            <div class="neon-section-frame inline-flex items-center gap-3 px-6 py-4">
                                <x-heroicon-o-calendar class="h-5 w-5 text-orange-400 shrink-0" />
                                <div class="text-left">
                                    <p class="text-xs font-bold uppercase tracking-[0.08em] text-slate-500">Event starts</p>
                                    <p class="text-slate-100 font-semibold">
                                        {{ $gameList->getEventTime()->format('F j, Y \a\t g:i A') }}
                                        <span class="text-slate-500 text-sm">({{ $gameList->getEventTimezone() }})</span>
                                    </p>
                                </div>
                            </div>

                            <div class="mt-6"
                                 x-data="{
                                     localTime: '',
                                     timeLeft: '',
                                     init() {
                                         const eventTime = new Date('{{ $gameList->getEventTime()->toIso8601String() }}');
                                         this.localTime = eventTime.toLocaleString(undefined, {
                                             month: 'short',
                                             day: 'numeric',
                                             hour: 'numeric',
                                             minute: '2-digit',
                                             timeZoneName: 'short'
                                         });
                                         this.updateCountdown();
                                         setInterval(() => this.updateCountdown(), 1000);
                                     },
                                     updateCountdown() {
                                         const eventTime = new Date('{{ $gameList->getEventTime()->toIso8601String() }}');
                                         const now = new Date();
                                         const diff = eventTime - now;
                                         if (diff <= 0) {
                                             window.location.reload();
                                             return;
                                         }
                                         const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                                         const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                                         const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                                         const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                                         if (days > 0) {
                                             this.timeLeft = days + 'd ' + hours + 'h ' + minutes + 'm';
                                         } else if (hours > 0) {
                                             this.timeLeft = hours + 'h ' + minutes + 'm ' + seconds + 's';
                                         } else {
                                             this.timeLeft = minutes + 'm ' + seconds + 's';
                                         }
                                     }
                                 }">
                                <p class="text-sm text-slate-500">
                                    <span class="text-orange-400 font-semibold" x-text="timeLeft"></span> until the event begins
                                </p>
                                <p class="mt-1 text-sm text-slate-400">
                                    <span class="text-slate-500">Your time:</span> <span x-text="localTime"></span>
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Mobile Filter Overlay --}}
            <div x-show="mobileFiltersOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 z-50 lg:hidden"
                 x-cloak>
                <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="closeMobileFilters()"></div>

                <div x-show="mobileFiltersOpen"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="translate-y-full"
                     x-transition:enter-end="translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="translate-y-0"
                     x-transition:leave-end="translate-y-full"
                     class="absolute inset-x-0 bottom-0 flex max-h-[85vh] flex-col overflow-hidden rounded-t-3xl border-t border-white/[0.08] bg-slate-900">
                    <div class="flex items-center justify-between border-b border-white/[0.08] px-5 py-4">
                        <h2 class="neon-eyebrow">Filters</h2>
                        <button @click="closeMobileFilters()" class="text-slate-400 hover:text-slate-200 transition p-1">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="flex-1 space-y-6 overflow-y-auto p-5">
                        @if(count($filterOptions['platforms'] ?? []) > 0)
                            <div>
                                <h3 class="mb-3 text-[0.68rem] font-bold uppercase tracking-[0.1em] text-slate-500">Platform</h3>
                                <div class="space-y-2">
                                    @foreach($filterOptions['platforms'] as $platform)
                                        <label class="flex cursor-pointer items-center gap-2 text-slate-300">
                                            <input type="checkbox"
                                                   :checked="isSelected('platforms', {{ $platform['id'] }})"
                                                   @change="toggleFilter('platforms', {{ $platform['id'] }})"
                                                   class="rounded border-white/20 bg-slate-800 accent-cyan-400">
                                            <span class="text-sm">{{ $platform['name'] }}</span>
                                            <span class="ml-auto text-slate-500 text-xs">({{ $platform['count'] }})</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(count($filterOptions['genres'] ?? []) > 0)
                            <div>
                                <h3 class="mb-3 text-[0.68rem] font-bold uppercase tracking-[0.1em] text-slate-500">Genre</h3>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($filterOptions['genres'] as $genre)
                                        <button @click="toggleFilter('genres', {{ $genre['id'] }})"
                                                :class="isSelected('genres', {{ $genre['id'] }}) ? 'border-cyan-400/40 bg-cyan-400/15 text-cyan-300' : 'border-white/10 bg-white/5 text-slate-400'"
                                                class="rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-wider transition">
                                            {{ $genre['name'] }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(count($filterOptions['gameTypes'] ?? []) > 1)
                            <div>
                                <h3 class="mb-3 text-[0.68rem] font-bold uppercase tracking-[0.1em] text-slate-500">Game Type</h3>
                                <div class="space-y-2">
                                    @foreach($filterOptions['gameTypes'] as $gameType)
                                        <label class="flex cursor-pointer items-center gap-2 text-slate-300">
                                            <input type="checkbox"
                                                   :checked="isSelected('gameTypes', {{ $gameType['id'] }})"
                                                   @change="toggleFilter('gameTypes', {{ $gameType['id'] }})"
                                                   class="rounded border-white/20 bg-slate-800 accent-cyan-400">
                                            <span class="text-sm">{{ $gameType['name'] }}</span>
                                            <span class="ml-auto text-slate-500 text-xs">({{ $gameType['count'] }})</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="flex gap-3 border-t border-white/[0.08] p-5">
                        <button @click="clearAllFilters()" class="flex-1 rounded-full border border-white/10 bg-white/5 py-3 text-sm font-semibold text-slate-300 transition hover:bg-white/10">
                            Clear All
                        </button>
                        <button @click="closeMobileFilters()" class="flex-1 rounded-full bg-orange-500 py-3 text-sm font-semibold text-white transition hover:bg-orange-600">
                            Show <span x-text="stats.filtered"></span> Games
                        </button>
                    </div>
                </div>
            </div>
        </div>

    @else
        {{-- Regular List View (non-system or editable) --}}
        <div class="page-shell py-10">

            {{-- Header --}}
            <div class="neon-section-frame mb-8">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold uppercase tracking-wide text-slate-100">
                            {{ $gameList->name }}
                        </h1>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            @if($gameList->is_system)
                                <span class="inline-flex items-center rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-0.5 text-xs font-bold uppercase tracking-[0.08em] text-cyan-300">System List</span>
                            @endif
                            @if($gameList->is_public)
                                <span class="inline-flex items-center rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-0.5 text-xs font-bold uppercase tracking-[0.08em] text-emerald-300">Public</span>
                            @else
                                <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-0.5 text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Private</span>
                            @endif
                        </div>
                    </div>

                    @auth
                        @if(!isset($readOnly) || !$readOnly)
                            @php
                                $authUser = auth()->user();
                                $canEditList = false;
                                if ($authUser) {
                                    if ($authUser->isAdmin()) {
                                        $canEditList = true;
                                    } elseif (!$gameList->is_system && $gameList->user_id === $authUser->id) {
                                        $canEditList = true;
                                    }
                                }
                            @endphp
                            @if($canEditList)
                                <div class="flex shrink-0 gap-2">
                                    <a href="{{ route('lists.edit', [$gameList->list_type->toSlug(), $gameList->slug]) }}"
                                       class="neon-btn-ghost rounded-full px-4 py-2 text-xs font-bold uppercase tracking-[0.06em]">
                                        Edit
                                    </a>
                                    @if($gameList->canBeDeleted())
                                        <form action="{{ route('lists.destroy', [$gameList->list_type->toSlug(), $gameList->slug]) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this list?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="rounded-full border border-red-500/30 bg-red-500/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.06em] text-red-400 transition hover:bg-red-500/20">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        @endif
                    @endauth
                </div>

                @if($gameList->description)
                    <p class="text-sm text-slate-400 leading-relaxed">
                        {{ $gameList->description }}
                    </p>
                @endif

                @if($gameList->user)
                    <p class="mt-2 text-xs text-slate-500">
                        Created by {{ $gameList->user->name }}
                    </p>
                @endif
                @if($gameList->end_at)
                    <p class="mt-1 text-xs text-slate-500">
                        Expires {{ $gameList->end_at->format('d/m/Y') }}
                    </p>
                @endif
            </div>

            @if(session('success'))
                <div class="neon-panel mb-6 border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-300">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mb-6">
                <p class="mb-4 text-sm text-slate-400">
                    {{ $gameList->games->count() }} {{ Str::plural('game', $gameList->games->count()) }} in this list
                </p>

                @auth
                    @if($canEdit && (!isset($readOnly) || !$readOnly))
                        <div class="neon-section-frame mb-8">
                            <h2 class="neon-eyebrow mb-4">Add Games to List</h2>
                            <div
                                data-vue-component="add-game-to-list"
                                data-list-id="{{ $gameList->id }}"
                                data-platforms="{{ json_encode(\App\Enums\PlatformEnum::getActivePlatforms()->map(fn($enum) => ['id' => $enum->value, 'label' => $enum->label(), 'color' => $enum->color()])->values()) }}"
                            ></div>
                        </div>
                    @endif
                @endauth
            </div>

            @if($gameList->games->count() > 0)
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5">
                    @foreach($gameList->games as $game)
                        @php
                            $pivotReleaseDate = $game->pivot->release_date ?? null;
                            if ($pivotReleaseDate && is_string($pivotReleaseDate)) {
                                $pivotReleaseDate = \Carbon\Carbon::parse($pivotReleaseDate);
                            }
                            $displayDate = $pivotReleaseDate ?? $game->first_release_date;
                        @endphp
                        <x-game-card
                            :game="$game"
                            :displayReleaseDate="$displayDate"
                            variant="neon"
                            layout="below"
                            aspectRatio="3/4"
                            :showRemoveButton="($canEdit && (!isset($readOnly) || !$readOnly))"
                            :removeRoute="(!isset($readOnly) || !$readOnly && $gameList->user) ? route('user.lists.games.remove', [$gameList->user->username, $gameList->list_type->toSlug(), $game]) : null"
                            :platformEnums="$platformEnums" />
                    @endforeach
                </div>
            @else
                <div class="neon-panel bg-slate-800/40 px-6 py-16 text-center">
                    <p class="text-slate-400 mb-2">This list is empty.</p>
                    @auth
                        @if($canEdit && (!isset($readOnly) || !$readOnly))
                            <p class="text-sm text-slate-500">
                                Browse games and add them to this list.
                            </p>
                        @endif
                    @endauth
                </div>
            @endif
        </div>
    @endif

</div>
@endsection
