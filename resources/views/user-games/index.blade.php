@extends('layouts.app')

@section('title', ($collection ? $collection->name : $user->username . "'s Games"))

@section('content')
    <div class="min-h-screen">
        <!-- Cover Banner -->
        @php $total = max($stats['total'], 1); @endphp
        <div class="relative h-64 md:h-72 overflow-hidden bg-gradient-to-r from-gray-800 via-gray-700 to-gray-800">
            @if($collection && $collection->cover_image_url)
                <img src="{{ $collection->cover_image_url }}"
                     alt="Collection cover"
                     class="absolute inset-0 w-full h-full object-cover">
                <div class="absolute inset-0 bg-black/20"></div>
            @endif

            {{-- Content inside cover --}}
            <div class="absolute inset-0 flex items-end">
                <div class="container mx-auto px-4 pt-10 pb-6 md:py-6">
                    <div class="flex flex-col md:flex-row md:items-end gap-4">
                        {{-- Avatar --}}
                        <div class="flex-shrink-0">
                            @if($user->avatar_url)
                                <img src="{{ $user->avatar_url }}"
                                     alt="{{ $user->username }}"
                                     class="w-28 h-28 rounded-full object-cover ring-4 ring-white/20 bg-gray-700 shadow-lg">
                            @else
                                <div class="w-28 h-28 rounded-full ring-4 ring-white/20 bg-orange-600 flex items-center justify-center shadow-lg">
                                    <span class="text-3xl font-bold text-white">{{ $user->getInitials() }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- User Info --}}
                        <div class="flex-1 min-w-0 pb-1">
                            <h1 class="text-2xl md:text-3xl font-bold text-white truncate drop-shadow-lg">
                                {{ $collection ? $collection->name : $user->username . "'s Games" }}
                            </h1>
                            @if($collection && $collection->description)
                                <p class="text-gray-200 mt-1 max-w-2xl text-sm drop-shadow">{{ $collection->description }}</p>
                            @endif
                        </div>

                        {{-- Stat Cards (2 rows) --}}
                        <div class="flex-shrink-0 pb-1">
                            {{-- Row 1: Total | Hours | Wishlist --}}
                            <div class="hidden md:flex gap-2 mb-2">
                                @include('user-games.partials.stat-card', [
                                    'value' => $stats['total'],
                                    'label' => 'Total',
                                    'iconColor' => 'text-gray-400',
                                    'iconPath' => 'M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.491 48.491 0 01-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 01-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 00-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 01-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 00.657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 01-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 005.427-.63 48.05 48.05 0 00.582-4.717.532.532 0 00-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.96.401v0a.656.656 0 00.658-.663 48.422 48.422 0 00-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 01-.61-.58v0z',
                                    'max' => $total,
                                    'showBar' => false,
                                    'barColor' => '',
                                ])
                                @include('user-games.partials.stat-card', [
                                    'value' => round($stats['total_hours']),
                                    'label' => 'Hours',
                                    'iconColor' => 'text-gray-400',
                                    'iconPath' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
                                    'max' => $total,
                                    'showBar' => false,
                                    'barColor' => '',
                                ])
                                <div class="bg-black/40 backdrop-blur-sm rounded-lg px-3 py-2 min-w-[4.5rem]">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <svg class="w-3.5 h-3.5 text-red-400" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/>
                                        </svg>
                                        <span class="text-sm font-bold text-white">{{ $stats['wishlist'] }}</span>
                                    </div>
                                    <span class="text-[10px] text-gray-400 uppercase tracking-wide">Wishlist</span>
                                </div>
                            </div>
                            {{-- Row 2: Playing | Played | Backlog --}}
                            <div class="hidden md:flex gap-2">
                                @include('user-games.partials.stat-card', [
                                    'value' => $stats['playing'],
                                    'label' => 'Playing',
                                    'iconColor' => 'text-green-400',
                                    'iconPath' => 'M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.491 48.491 0 01-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 01-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 00-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 01-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 00.657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 01-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 005.427-.63 48.05 48.05 0 00.582-4.717.532.532 0 00-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.96.401v0a.656.656 0 00.658-.663 48.422 48.422 0 00-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 01-.61-.58v0z',
                                    'max' => $total,
                                    'showBar' => true,
                                    'barColor' => 'bg-green-500',
                                ])
                                @include('user-games.partials.stat-card', [
                                    'value' => $stats['played'],
                                    'label' => 'Played',
                                    'iconColor' => 'text-purple-400',
                                    'iconPath' => 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0016.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.023 6.023 0 01-2.77.896m5.25-5.624V2.721',
                                    'max' => $total,
                                    'showBar' => true,
                                    'barColor' => 'bg-purple-500',
                                ])
                                @include('user-games.partials.stat-card', [
                                    'value' => $stats['backlog'],
                                    'label' => 'Backlog',
                                    'iconColor' => 'text-orange-400',
                                    'iconPath' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
                                    'max' => $total,
                                    'showBar' => true,
                                    'barColor' => 'bg-orange-500',
                                ])
                            </div>

                            {{-- Mobile stats (all visible, no expand/collapse) --}}
                            <div class="flex md:hidden flex-wrap items-center gap-x-3 gap-y-1 pb-1 text-sm text-white">
                                <span class="font-bold">{{ $stats['total'] }}</span><span class="text-gray-300 text-xs">games</span>
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span><span class="font-bold">{{ $stats['playing'] }}</span>
                                <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span><span class="font-bold">{{ $stats['played'] }}</span>
                                <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span><span class="font-bold">{{ $stats['backlog'] }}</span>
                                <span class="inline-flex items-center gap-0.5 text-gray-400 text-xs">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ round($stats['total_hours']) }}h
                                </span>
                                <svg class="w-3.5 h-3.5 text-red-400" fill="currentColor" viewBox="0 0 24 24"><path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/></svg>
                                <span class="font-bold">{{ $stats['wishlist'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container mx-auto px-4 py-6">

            <!-- Filter Tabs + Sort + View Toggle -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-6"
                 x-data="{
                     currentStatus: '{{ $statusFilter }}',
                     currentWishlist: {{ $wishlistFilter ? 'true' : 'false' }},
                     currentSort: '{{ $sortBy }}',
                     navigate(params) {
                         const url = new URL(window.location);
                         url.search = '';
                         Object.entries(params).forEach(([k, v]) => {
                             if (v) url.searchParams.set(k, v);
                         });
                         window.location = url.toString();
                     }
                 }">
                <!-- Filter Tabs -->
                <div class="flex flex-wrap gap-2">
                    @php
                        $filters = [
                            ['key' => 'all', 'label' => 'All', 'count' => $stats['total'], 'dot' => null],
                            ['key' => 'playing', 'label' => 'Playing', 'count' => $stats['playing'], 'dot' => 'bg-green-500'],
                            ['key' => 'played', 'label' => 'Played', 'count' => $stats['played'], 'dot' => 'bg-purple-500'],
                            ['key' => 'backlog', 'label' => 'Backlog', 'count' => $stats['backlog'], 'dot' => 'bg-orange-500'],
                        ];
                    @endphp

                    @foreach($filters as $filter)
                        <a href="{{ route('user.games', ['user' => $user->username, 'status' => $filter['key'], 'sort' => $sortBy]) }}"
                           class="px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2
                               {{ $statusFilter === $filter['key'] && !$wishlistFilter
                                   ? 'bg-orange-600 text-white'
                                   : 'bg-gray-800 text-gray-300 hover:bg-gray-700' }}">
                            @if($filter['dot'])
                                <span class="w-2 h-2 rounded-full {{ $filter['dot'] }}"></span>
                            @endif
                            {{ $filter['label'] }}
                            <span class="text-xs opacity-70">({{ $filter['count'] }})</span>
                        </a>
                    @endforeach

                    <a href="{{ route('user.games', ['user' => $user->username, 'wishlist' => 1, 'sort' => $sortBy]) }}"
                       class="px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2
                           {{ $wishlistFilter
                               ? 'bg-orange-600 text-white'
                               : 'bg-gray-800 text-gray-300 hover:bg-gray-700' }}">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/>
                        </svg>
                        Wishlist
                        <span class="text-xs opacity-70">({{ $stats['wishlist'] }})</span>
                    </a>
                </div>

                <div class="flex items-center gap-2 ml-auto">
                    @if($isOwner)
                        <!-- Settings Button -->
                        <a href="{{ route('user.games.settings', $user->username) }}"
                           class="flex items-center gap-2 px-3 py-2 bg-gray-800 rounded-lg text-sm text-gray-300 hover:bg-gray-700 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.559.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.204-.107-.397.165-.71.505-.78.929l-.15.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.272-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Settings
                        </a>
                    @endif

                    <!-- View Toggle -->
                    <div class="flex items-center gap-1 bg-gray-800 rounded-lg p-1">
                        <button onclick="toggleViewMode('grid')"
                                class="p-1.5 rounded transition {{ $viewMode === 'grid' ? 'bg-orange-600 text-white' : 'text-gray-400 hover:text-white' }}">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                            </svg>
                        </button>
                        <button onclick="toggleViewMode('list')"
                                class="p-1.5 rounded transition {{ $viewMode === 'list' ? 'bg-orange-600 text-white' : 'text-gray-400 hover:text-white' }}">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Sort Dropdown -->
                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                        <button @click="open = !open" class="flex items-center gap-2 px-3 py-2 bg-gray-800 rounded-lg text-sm text-gray-300 hover:bg-gray-700 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5-4.5L16.5 16.5m0 0L12 12m4.5 4.5V3"/>
                            </svg>
                            Sort
                        </button>
                        <div x-show="open" x-transition class="absolute right-0 mt-1 w-44 bg-gray-800 border border-gray-700 rounded-lg shadow-lg z-30" style="display: none;">
                            @foreach(['date_added' => 'Date Added', 'alpha' => 'Alphabetical', 'release_date' => 'Release Date', 'time_played' => 'Time Played', 'rating' => 'Rating', 'manual' => 'Manual Order'] as $sortKey => $sortLabel)
                                <a href="{{ route('user.games', array_merge(['user' => $user->username], request()->query(), ['sort' => $sortKey])) }}"
                                   class="block px-4 py-2 text-sm transition {{ $sortBy === $sortKey ? 'text-orange-400 bg-gray-700' : 'text-gray-300 hover:bg-gray-700' }}">
                                    {{ $sortLabel }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            @if($isOwner)
                <!-- Add Game Search -->
                <div class="mb-6">
                    <div data-vue-component="user-game-search"
                         data-route="{{ route('user.games.store', $user->username) }}"
                         data-status="{{ $statusFilter }}"
                         data-csrf="{{ csrf_token() }}">
                    </div>
                </div>
            @endif

            <!-- Games Grid/List -->
            @if($games->count() > 0)
                @if($viewMode === 'grid')
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4"
                         id="games-grid">
                        @foreach($games as $userGame)
                            <div class="group/card relative"
                                 data-user-game-id="{{ $userGame->id }}">

                                <a href="{{ route('game.show', $userGame->game->slug) }}" class="block">
                                    <div class="relative aspect-[3/4] rounded-xl overflow-hidden bg-gray-800 shadow-lg hover:ring-2 hover:ring-orange-500/50 transition-all">
                                        <img src="{{ $userGame->game->getCoverUrl() }}"
                                             alt="{{ $userGame->game->name }}"
                                             class="w-full h-full object-cover"
                                             loading="lazy"
                                             onerror="this.style.display='none'">

                                        {{-- Drag Handle (manual sort + owner only) --}}
                                        @if($isOwner && $sortBy === 'manual')
                                            <div class="absolute top-2 left-2 z-20 drag-handle cursor-move bg-gray-900/70 backdrop-blur-sm rounded-lg p-1" onclick="event.preventDefault(); event.stopPropagation();">
                                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-8a2 2 0 1 0-.001-4.001A2 2 0 0 0 13 6zm0 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"></path>
                                                </svg>
                                            </div>
                                        @endif

                                        <!-- Status Badge -->
                                        @if($userGame->status)
                                            <div class="absolute {{ $isOwner && $sortBy === 'manual' ? 'top-2 left-10' : 'top-2 left-2' }} z-10">
                                                <span data-status-badge class="px-2 py-0.5 rounded-full text-xs font-semibold text-white {{ $userGame->status->badgeColor() }}">
                                                    {{ $userGame->status->label() }}
                                                </span>
                                            </div>
                                        @else
                                            <span data-status-badge style="display:none" class="absolute {{ $isOwner && $sortBy === 'manual' ? 'top-2 left-10' : 'top-2 left-2' }} z-10 px-2 py-0.5 rounded-full text-xs font-semibold text-white bg-gray-600"></span>
                                        @endif

                                        <!-- Time + Rating Badges -->
                                        <div class="absolute bottom-2 left-2 right-2 flex items-center gap-1 z-10">
                                            <span {{ $userGame->time_played ? '' : 'style=display:none' }} class="flex items-center gap-1 px-1.5 py-0.5 rounded bg-black/70 text-xs text-white font-medium">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                <span data-time-badge>{{ $userGame->getFormattedTimePlayed() }}</span>
                                            </span>
                                            <span {{ $userGame->rating ? '' : 'style=display:none' }} class="flex items-center gap-1 px-1.5 py-0.5 rounded bg-black/70 text-xs text-orange-400 font-medium">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005z"/></svg>
                                                <span data-rating-badge>{{ $userGame->rating }}/100</span>
                                            </span>
                                        </div>

                                        <!-- Wishlist indicator -->
                                        @if(!$isOwner)
                                            <div data-wishlist-icon class="absolute top-2 right-2 z-10" {{ $userGame->is_wishlisted ? '' : 'style=display:none' }}>
                                                <svg class="w-4 h-4 text-red-400" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/>
                                                </svg>
                                            </div>
                                        @endif
                                    </div>
                                </a>

                                {{-- Edit button (owner only) --}}
                                @if($isOwner)
                                    <button x-data
                                            @click="$dispatch('open-user-game-edit', {
                                                gameId: {{ $userGame->game->id }},
                                                gameName: '{{ addslashes($userGame->game->name) }}',
                                                gameCover: '{{ $userGame->game->getCoverUrl() }}',
                                                gameSlug: '{{ $userGame->game->slug }}',
                                                userGameId: {{ $userGame->id }},
                                                status: {{ $userGame->status ? "'" . $userGame->status->value . "'" : 'null' }},
                                                isWishlisted: {{ $userGame->is_wishlisted ? 'true' : 'false' }},
                                                timePlayed: {{ $userGame->time_played !== null ? $userGame->time_played : 'null' }},
                                                rating: {{ $userGame->rating ?? 'null' }},
                                                cardElement: $el.closest('[data-user-game-id]'),
                                                statusFilter: '{{ $statusFilter }}',
                                                wishlistFilter: {{ $wishlistFilter ? 'true' : 'false' }},
                                            })"
                                            class="absolute top-2 right-2 z-20 p-1.5 bg-gray-900/70 backdrop-blur-sm rounded-lg text-white md:opacity-0 md:group-hover/card:opacity-100 transition-opacity hover:bg-orange-600"
                                            title="Edit game">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                                        </svg>
                                    </button>
                                    <div data-wishlist-icon class="absolute top-2 {{ $sortBy === 'manual' ? 'right-10' : 'right-2' }} z-10 pointer-events-none hidden md:block md:group-hover/card:opacity-0 transition-opacity" {{ $userGame->is_wishlisted ? '' : 'style=display:none' }}>
                                        <svg class="w-4 h-4 text-red-400" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/>
                                        </svg>
                                    </div>
                                @endif

                                <div class="mt-2">
                                    <a href="{{ route('game.show', $userGame->game->slug) }}"
                                       class="text-sm font-medium text-gray-200 hover:text-white line-clamp-2">
                                        {{ $userGame->game->name }}
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <!-- List View -->
                    <div class="bg-gray-800 rounded-xl overflow-hidden" id="games-list">
                        <table class="w-full">
                            <thead class="bg-gray-700/50">
                                <tr>
                                    @if($isOwner && $sortBy === 'manual')
                                        <th class="w-10 px-2"></th>
                                    @endif
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase">Game</th>
                                    <th class="text-center px-3 py-3 text-xs font-semibold text-gray-400 uppercase hidden md:table-cell w-12">
                                        <svg class="w-4 h-4 mx-auto text-gray-500" fill="currentColor" viewBox="0 0 24 24"><path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/></svg>
                                    </th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase hidden md:table-cell">Status</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase hidden md:table-cell">Time</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase hidden md:table-cell">Rating</th>
                                    @if($isOwner)
                                        <th class="w-14 px-2"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700/50">
                                @foreach($games as $userGame)
                                    <tr class="hover:bg-gray-700/30 transition"
                                        data-user-game-id="{{ $userGame->id }}">
                                        @if($isOwner && $sortBy === 'manual')
                                            <td class="px-2 py-4">
                                                <div class="drag-handle cursor-move text-gray-500 hover:text-white">
                                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-8a2 2 0 1 0-.001-4.001A2 2 0 0 0 13 6zm0 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"></path>
                                                    </svg>
                                                </div>
                                            </td>
                                        @endif
                                        <td class="px-4 py-4">
                                            <div class="flex items-center gap-3">
                                                <img src="{{ $userGame->game->getCoverUrl() }}"
                                                     alt="{{ $userGame->game->name }}"
                                                     class="w-14 h-[4.5rem] rounded object-cover flex-shrink-0"
                                                     loading="lazy">
                                                <div>
                                                    <a href="{{ route('game.show', $userGame->game->slug) }}" class="text-sm font-medium text-gray-200 hover:text-white">{{ $userGame->game->name }}</a>
                                                    @if($userGame->game->first_release_date)
                                                        <span class="block text-xs text-gray-500">{{ $userGame->game->first_release_date->format('M j, Y') }}</span>
                                                    @endif
                                                    <div class="flex flex-wrap items-center gap-1.5 md:hidden mt-1">
                                                        @if($userGame->status)
                                                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold text-white {{ $userGame->status->badgeColor() }}">
                                                                {{ $userGame->status->label() }}
                                                            </span>
                                                        @endif
                                                        @if($userGame->getFormattedTimePlayed())
                                                            <span class="inline-flex items-center gap-0.5 text-[10px] text-gray-400">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                                {{ $userGame->getFormattedTimePlayed() }}
                                                            </span>
                                                        @endif
                                                        @if($userGame->rating)
                                                            <span class="inline-flex items-center gap-0.5 text-[10px] text-orange-400 font-medium">
                                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005z"/></svg>
                                                                {{ $userGame->rating }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-4 text-center hidden md:table-cell" data-wishlist-cell>
                                            @if($userGame->is_wishlisted)
                                                <svg data-wishlist-icon class="w-4 h-4 text-red-400 mx-auto" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/>
                                                </svg>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 hidden md:table-cell" data-status-cell>
                                            @if($userGame->status)
                                                <span data-status-badge class="px-2 py-0.5 rounded-full text-xs font-semibold text-white {{ $userGame->status->badgeColor() }}">
                                                    {{ $userGame->status->label() }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-400 hidden md:table-cell" data-time-cell>
                                            @if($userGame->getFormattedTimePlayed())
                                                <span class="flex items-center gap-1">
                                                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    {{ $userGame->getFormattedTimePlayed() }}
                                                </span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm hidden md:table-cell" data-rating-cell>
                                            @if($userGame->rating)
                                                <span class="flex items-center gap-1 text-orange-400 font-medium">
                                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005z"/></svg>
                                                    {{ $userGame->rating }}/100
                                                </span>
                                            @else
                                                <span class="text-gray-500">-</span>
                                            @endif
                                        </td>
                                        @if($isOwner)
                                            <td class="px-2 py-4" x-data>
                                                <button @click="$dispatch('open-user-game-edit', {
                                                            gameId: {{ $userGame->game->id }},
                                                            gameName: '{{ addslashes($userGame->game->name) }}',
                                                            gameCover: '{{ $userGame->game->getCoverUrl() }}',
                                                            gameSlug: '{{ $userGame->game->slug }}',
                                                            userGameId: {{ $userGame->id }},
                                                            status: {{ $userGame->status ? "'" . $userGame->status->value . "'" : 'null' }},
                                                            isWishlisted: {{ $userGame->is_wishlisted ? 'true' : 'false' }},
                                                            timePlayed: {{ $userGame->time_played !== null ? $userGame->time_played : 'null' }},
                                                            rating: {{ $userGame->rating ?? 'null' }},
                                                            cardElement: $el.closest('[data-user-game-id]'),
                                                            statusFilter: '{{ $statusFilter }}',
                                                            wishlistFilter: {{ $wishlistFilter ? 'true' : 'false' }},
                                                        })"
                                                        class="p-2 rounded-lg border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white transition"
                                                        title="Edit game">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                                                    </svg>
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @else
                <!-- Empty State -->
                <div class="text-center py-16">
                    <svg class="w-16 h-16 mx-auto text-gray-600 mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.491 48.491 0 01-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 01-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 00-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 01-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 00.657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 01-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 005.427-.63 48.05 48.05 0 00.582-4.717.532.532 0 00-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.96.401v0a.656.656 0 00.658-.663 48.422 48.422 0 00-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 01-.61-.58v0z" />
                    </svg>
                    <h3 class="text-xl font-semibold text-gray-400 mb-2">No games yet</h3>
                    <p class="text-gray-500 mb-4">
                        @if($isOwner)
                            Start building your collection by adding games.
                        @else
                            {{ $user->username }} hasn't added any games yet.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </div>

    {{-- Edit Modal (owner only) --}}
    @if($isOwner)
        @include('user-games.partials.edit-modal')
    @endif

    {{-- SortableJS for drag-and-drop reorder (manual sort + owner) --}}
    @if($isOwner && $sortBy === 'manual' && $games->count() > 1)
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const container = document.querySelector('#games-grid') || document.querySelector('#games-list tbody');
                if (!container) return;

                Sortable.create(container, {
                    handle: '.drag-handle',
                    animation: 150,
                    ghostClass: 'opacity-30',
                    onEnd: function() {
                        const items = Array.from(container.querySelectorAll('[data-user-game-id]'))
                            .map((el, index) => ({
                                id: parseInt(el.dataset.userGameId),
                                sort_order: index + 1
                            }));

                        fetch('{{ route("user.games.reorder", $user->username) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-HTTP-Method-Override': 'PATCH',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ items: items, _method: 'PATCH' })
                        });
                    }
                });
            });
        </script>
    @endif

    <script>
        function toggleViewMode(mode) {
            fetch('{{ route("user.lists.toggle-view") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ mode: mode })
            }).then(() => window.location.reload());
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-target-width]').forEach(function(bar) {
                setTimeout(function() {
                    bar.style.width = bar.dataset.targetWidth;
                }, 100);
            });
        });
    </script>
@endsection
