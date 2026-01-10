@extends('layouts.app')

@section('title', $gameList->name . ' - Games List')

@push('head')
    {{-- Open Graph Meta Tags --}}
    <meta property="og:title" content="{{ $gameList->name }} | GamesOutbreak">
    <meta property="og:description" content="Browse {{ $gameList->games->count() }}+ games from {{ $gameList->name }}. Filter by platform, genre, and more.">
    <meta property="og:image" content="{{ public_path($gameList->og_image_url) ?? ($gameList->games->first()?->getCoverUrl() ?? asset('images/og-default.png')) }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $gameList->name }}">
    <meta name="twitter:description" content="Browse {{ $gameList->games->count() }}+ games">
    <meta name="twitter:image" content="{{ $gameList->og_image_url ?? ($gameList->games->first()?->getCoverUrl() ?? asset('images/og-default.png')) }}">

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
    @endphp

    {{-- System List with Filtering --}}
    @if($isSystemList && isset($gamesData))
        <div x-data="listFilter(
            {{ Js::from($gamesData) }},
            {{ Js::from($initialFilters ?? []) }},
            {{ Js::from($filterOptions ?? []) }}
        )" class="min-h-screen">
            {{-- Header Section --}}
            <div class="bg-gradient-to-b from-gray-900 to-gray-800 border-b border-gray-700">
                <div class="container mx-auto px-4 py-8">
                    <h1 class="text-3xl md:text-4xl font-bold text-white mb-2">
                        {{ $gameList->name }}
                    </h1>
                    @if($gameList->description)
                        <p class="text-lg text-gray-400 max-w-3xl">
                            {{ $gameList->description }}
                        </p>
                    @endif
                </div>
            </div>

            {{-- Stats Bar --}}
            <div class="bg-gray-800 border-b border-gray-700 sticky top-0 z-40">
                <div class="container mx-auto px-4 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-4 text-sm">
                            <span class="text-gray-300">
                                Showing <strong class="text-white" x-text="stats.filtered"></strong>
                                of <strong class="text-white">{{ count($gamesData) }}</strong> games
                            </span>
                            <template x-if="stats.filtered !== stats.total">
                                <button @click="clearAllFilters()" class="text-orange-400 hover:text-orange-300 font-medium transition">
                                    Clear filters
                                </button>
                            </template>
                        </div>

                        <div class="flex items-center gap-4">
                            {{-- View Toggle --}}
                            <div class="flex items-center gap-1 bg-gray-700 rounded-lg p-1">
                                <button @click="setViewMode('grid')"
                                        :class="viewMode === 'grid' ? 'bg-orange-500 text-white' : 'text-gray-400 hover:text-white'"
                                        class="p-2 rounded transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                                    </svg>
                                </button>
                                <button @click="setViewMode('list')"
                                        :class="viewMode === 'list' ? 'bg-orange-500 text-white' : 'text-gray-400 hover:text-white'"
                                        class="p-2 rounded transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                                    </svg>
                                </button>
                            </div>

                            {{-- Mobile Filter Button --}}
                            <button @click="openMobileFilters()"
                                    class="lg:hidden bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                                </svg>
                                <span>Filters</span>
                                <template x-if="hasActiveFilters">
                                    <span class="bg-orange-500 text-white text-xs font-bold px-2 py-0.5 rounded-full" x-text="activeFilterPills.length"></span>
                                </template>
                            </button>
                        </div>
                    </div>

                    {{-- Active Filter Pills --}}
                    <template x-if="hasActiveFilters">
                        <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-gray-700">
                            <template x-for="pill in activeFilterPills" :key="pill.type + '-' + pill.id">
                                <button @click="removeFilter(pill.type, pill.id)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-orange-500/20 text-orange-400 rounded-full text-sm hover:bg-orange-500/30 transition">
                                    <span x-text="pill.name"></span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </template>
                            <button @click="clearAllFilters()" class="text-gray-400 hover:text-white text-sm transition">
                                Clear all
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Main Content --}}
            <div class="container mx-auto px-4 py-6">
                <div class="flex flex-col lg:flex-row gap-6">
                    {{-- Filter Sidebar (Desktop) --}}
                    <aside class="hidden lg:block w-64 flex-shrink-0">
                        <div class="sticky top-24 bg-gray-800 rounded-xl border border-gray-700 p-4 space-y-6">
                            <h2 class="text-lg font-bold text-orange-500">Filters</h2>

                            {{-- Platform Filter --}}
                            @if(count($filterOptions['platforms'] ?? []) > 0)
                                <div>
                                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Platform</h3>
                                    <div class="space-y-2">
                                        @foreach($filterOptions['platforms'] as $platform)
                                            <label class="flex items-center gap-2 cursor-pointer hover:text-orange-400 transition text-gray-300"
                                                   :class="isSelected('platforms', {{ $platform['id'] }}) && 'text-orange-400'">
                                                <input type="checkbox"
                                                       :checked="isSelected('platforms', {{ $platform['id'] }})"
                                                       @change="toggleFilter('platforms', {{ $platform['id'] }})"
                                                       class="rounded bg-gray-700 border-gray-600 text-orange-500 focus:ring-orange-500">
                                                <span>{{ $platform['name'] }}</span>
                                                <span class="text-gray-500 text-sm ml-auto">({{ $platform['count'] }})</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Genre Filter --}}
                            @if(count($filterOptions['genres'] ?? []) > 0)
                                <div>
                                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Genre</h3>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($filterOptions['genres'] as $genre)
                                            <button @click="toggleFilter('genres', {{ $genre['id'] }})"
                                                    :class="isSelected('genres', {{ $genre['id'] }}) ? 'bg-orange-500 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
                                                    class="px-3 py-1 rounded-full text-sm transition">
                                                {{ $genre['name'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Game Type Filter --}}
                            @if(count($filterOptions['gameTypes'] ?? []) > 1)
                                <div>
                                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Game Type</h3>
                                    <div class="space-y-2">
                                        @foreach($filterOptions['gameTypes'] as $gameType)
                                            <label class="flex items-center gap-2 cursor-pointer hover:text-orange-400 transition text-gray-300"
                                                   :class="isSelected('gameTypes', {{ $gameType['id'] }}) && 'text-orange-400'">
                                                <input type="checkbox"
                                                       :checked="isSelected('gameTypes', {{ $gameType['id'] }})"
                                                       @change="toggleFilter('gameTypes', {{ $gameType['id'] }})"
                                                       class="rounded bg-gray-700 border-gray-600 text-orange-500 focus:ring-orange-500">
                                                <span>{{ $gameType['name'] }}</span>
                                                <span class="text-gray-500 text-sm ml-auto">({{ $gameType['count'] }})</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Game Mode Filter --}}
                            @if(count($filterOptions['modes'] ?? []) > 0)
                                <div>
                                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Game Mode</h3>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($filterOptions['modes'] as $mode)
                                            <button @click="toggleFilter('modes', {{ $mode['id'] }})"
                                                    :class="isSelected('modes', {{ $mode['id'] }}) ? 'bg-orange-500 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
                                                    class="px-3 py-1 rounded-full text-sm transition">
                                                {{ $mode['name'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Perspective Filter --}}
                            @if(count($filterOptions['perspectives'] ?? []) > 0)
                                <div>
                                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Perspective</h3>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($filterOptions['perspectives'] as $perspective)
                                            <button @click="toggleFilter('perspectives', {{ $perspective['id'] }})"
                                                    :class="isSelected('perspectives', {{ $perspective['id'] }}) ? 'bg-orange-500 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
                                                    class="px-3 py-1 rounded-full text-sm transition">
                                                {{ $perspective['name'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </aside>

                    {{-- Game Grid/List --}}
                    <main class="flex-1">
                        {{-- Grid View --}}
                        <div x-show="viewMode === 'grid'" x-cloak
                             class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"
                             :class="isFiltering && 'opacity-50 transition-opacity'">
                            <template x-for="game in filteredGames" :key="game.id">
                                <div class="bg-gray-800 rounded-xl overflow-hidden shadow-lg hover:shadow-2xl transition-all group">
                                    <a :href="'/game/' + game.slug" class="block">
                                        <div class="aspect-[3/4] relative bg-gray-700 overflow-hidden">
                                            <img :src="game.cover_url"
                                                 :alt="game.name"
                                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                                 loading="lazy">
                                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent"></div>
                                            <div class="absolute bottom-0 left-0 right-0 p-4">
                                                <h3 class="font-bold text-white text-lg line-clamp-2 mb-2" x-text="game.name"></h3>
                                                <div class="flex items-center justify-between">
                                                    <span class="text-xs px-2 py-1 rounded bg-gray-700 text-gray-300" x-text="game.game_type.name"></span>
                                                    <span class="text-sm text-orange-400 font-medium" x-text="game.release_date_formatted"></span>
                                                </div>
                                            </div>
                                            <div class="absolute top-2 left-2 flex flex-wrap gap-1">
                                                <template x-for="platform in game.platforms.slice(0, 3)" :key="platform.id">
                                                    <span class="px-2 py-1 text-xs font-bold text-white rounded bg-gray-900/80" x-text="platform.name.substring(0, 6)"></span>
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
                                <div class="flex items-center gap-4 p-3 bg-gray-800 rounded-lg hover:bg-gray-700/50 transition-colors border border-gray-700">
                                    <a :href="'/game/' + game.slug" class="flex-shrink-0">
                                        <div class="w-12 h-16 rounded overflow-hidden bg-gray-700">
                                            <img :src="game.cover_url"
                                                 :alt="game.name"
                                                 class="w-full h-full object-cover"
                                                 loading="lazy">
                                        </div>
                                    </a>
                                    <div class="flex-1 min-w-0">
                                        <a :href="'/game/' + game.slug" class="block">
                                            <h3 class="font-semibold text-white truncate hover:text-orange-400 transition-colors" x-text="game.name"></h3>
                                            <span class="text-xs px-1.5 py-0.5 rounded bg-gray-700 text-gray-400 inline-block mt-1" x-text="game.game_type.name"></span>
                                        </a>
                                    </div>
                                    <div class="hidden sm:flex flex-shrink-0 gap-1 items-center">
                                        <template x-for="platform in game.platforms.slice(0, 3)" :key="platform.id">
                                            <span class="px-1.5 py-0.5 text-xs font-bold text-white rounded bg-gray-600" x-text="platform.name.substring(0, 4)"></span>
                                        </template>
                                    </div>
                                    <div class="flex-shrink-0 text-right">
                                        <span class="text-sm font-medium text-gray-400 whitespace-nowrap" x-text="game.release_date_formatted"></span>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Empty State --}}
                        <div x-show="filteredGames.length === 0" x-cloak class="text-center py-16 bg-gray-800 rounded-xl">
                            <svg class="w-16 h-16 mx-auto text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-gray-400 text-lg mb-4">No games match your current filters.</p>
                            <button @click="clearAllFilters()" class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2 rounded-lg transition">
                                Clear All Filters
                            </button>
                        </div>
                    </main>
                </div>
            </div>

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
                {{-- Backdrop --}}
                <div class="absolute inset-0 bg-black/60" @click="closeMobileFilters()"></div>

                {{-- Panel --}}
                <div x-show="mobileFiltersOpen"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="translate-y-full"
                     x-transition:enter-end="translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="translate-y-0"
                     x-transition:leave-end="translate-y-full"
                     class="absolute inset-x-0 bottom-0 max-h-[85vh] bg-gray-900 rounded-t-2xl overflow-hidden flex flex-col">
                    {{-- Header --}}
                    <div class="flex items-center justify-between p-4 border-b border-gray-700">
                        <h2 class="text-lg font-bold text-white">Filters</h2>
                        <button @click="closeMobileFilters()" class="text-gray-400 hover:text-white p-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    {{-- Filter Content --}}
                    <div class="flex-1 overflow-y-auto p-4 space-y-6">
                        {{-- Platform Filter --}}
                        @if(count($filterOptions['platforms'] ?? []) > 0)
                            <div>
                                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Platform</h3>
                                <div class="space-y-2">
                                    @foreach($filterOptions['platforms'] as $platform)
                                        <label class="flex items-center gap-2 cursor-pointer text-gray-300">
                                            <input type="checkbox"
                                                   :checked="isSelected('platforms', {{ $platform['id'] }})"
                                                   @change="toggleFilter('platforms', {{ $platform['id'] }})"
                                                   class="rounded bg-gray-700 border-gray-600 text-orange-500 focus:ring-orange-500">
                                            <span>{{ $platform['name'] }}</span>
                                            <span class="text-gray-500 text-sm ml-auto">({{ $platform['count'] }})</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Genre Filter --}}
                        @if(count($filterOptions['genres'] ?? []) > 0)
                            <div>
                                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Genre</h3>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($filterOptions['genres'] as $genre)
                                        <button @click="toggleFilter('genres', {{ $genre['id'] }})"
                                                :class="isSelected('genres', {{ $genre['id'] }}) ? 'bg-orange-500 text-white' : 'bg-gray-700 text-gray-300'"
                                                class="px-3 py-1.5 rounded-full text-sm transition">
                                            {{ $genre['name'] }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Game Type Filter --}}
                        @if(count($filterOptions['gameTypes'] ?? []) > 1)
                            <div>
                                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Game Type</h3>
                                <div class="space-y-2">
                                    @foreach($filterOptions['gameTypes'] as $gameType)
                                        <label class="flex items-center gap-2 cursor-pointer text-gray-300">
                                            <input type="checkbox"
                                                   :checked="isSelected('gameTypes', {{ $gameType['id'] }})"
                                                   @change="toggleFilter('gameTypes', {{ $gameType['id'] }})"
                                                   class="rounded bg-gray-700 border-gray-600 text-orange-500 focus:ring-orange-500">
                                            <span>{{ $gameType['name'] }}</span>
                                            <span class="text-gray-500 text-sm ml-auto">({{ $gameType['count'] }})</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="p-4 border-t border-gray-700 flex gap-3">
                        <button @click="clearAllFilters()" class="flex-1 px-4 py-3 bg-gray-700 text-gray-300 rounded-lg font-medium hover:bg-gray-600 transition">
                            Clear All
                        </button>
                        <button @click="closeMobileFilters()" class="flex-1 px-4 py-3 bg-orange-500 text-white rounded-lg font-medium hover:bg-orange-600 transition">
                            Show <span x-text="stats.filtered"></span> Games
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- Regular List View (non-system or editable) --}}
        <div class="container mx-auto px-4 py-8">
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100 mb-2">
                            {{ $gameList->name }}
                        </h1>
                        @if($gameList->is_system)
                            <span class="inline-block px-3 py-1 bg-orange-600 text-white rounded text-sm font-bold">System List</span>
                        @endif
                        @if($gameList->is_public)
                            <span class="inline-block px-3 py-1 bg-green-600 text-white rounded text-sm ml-2">Public</span>
                        @else
                            <span class="inline-block px-3 py-1 bg-gray-600 text-white rounded text-sm ml-2">Private</span>
                        @endif
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
                                <div class="flex gap-2">
                                    <a href="{{ route('lists.edit', [$gameList->list_type->toSlug(), $gameList->slug]) }}" class="bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 px-4 py-2 rounded-lg transition">
                                        Edit
                                    </a>
                                    @if($gameList->canBeDeleted())
                                        <form action="{{ route('lists.destroy', [$gameList->list_type->toSlug(), $gameList->slug]) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this list?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition">
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
                    <p class="text-lg text-gray-600 dark:text-gray-400 mb-4">
                        {{ $gameList->description }}
                    </p>
                @endif
                @if($gameList->user)
                    <p class="text-sm text-gray-500 dark:text-gray-500">
                        Created by {{ $gameList->user->name }}
                    </p>
                @endif
                @if($gameList->end_at)
                    <p class="text-sm text-gray-500 dark:text-gray-500">
                        Expires {{ $gameList->end_at->format('d/m/Y') }}
                    </p>
                @endif
            </div>

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mb-6">
                <p class="text-lg text-gray-700 dark:text-gray-300 mb-4">
                    {{ $gameList->games->count() }} {{ Str::plural('game', $gameList->games->count()) }} in this list
                </p>
                @auth
                    @if($canEdit && (!isset($readOnly) || !$readOnly))
                        <div class="mb-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                            <h2 class="text-2xl font-bold mb-4 text-gray-800 dark:text-gray-100">Add Games to List</h2>
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
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
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
                            variant="glassmorphism"
                            layout="overlay"
                            aspectRatio="3/4"
                            :showRemoveButton="($canEdit && (!isset($readOnly) || !$readOnly))"
                            :removeRoute="(!isset($readOnly) || !$readOnly && $gameList->user) ? route('user.lists.games.remove', [$gameList->user->username, $gameList->list_type->toSlug(), $game]) : null"
                            :platformEnums="$platformEnums" />
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                    <p class="text-xl text-gray-600 dark:text-gray-400 mb-4">
                        This list is empty.
                    </p>
                    @auth
                        @if($canEdit && (!isset($readOnly) || !$readOnly))
                            <p class="text-gray-500 dark:text-gray-500">
                                Browse games and add them to this list.
                            </p>
                        @endif
                    @endauth
                </div>
            @endif
        </div>
    @endif
@endsection
