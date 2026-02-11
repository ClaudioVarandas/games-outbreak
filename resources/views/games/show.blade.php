@extends('layouts.app')

@section('title', $game->name)

@push('head')
    @if($game->steam_data['header_image'] ?? null)
        <link rel="preload" as="image" href="{{ $game->steam_data['header_image'] }}" fetchpriority="high">
    @elseif($game->hero_image_id)
        <link rel="preload" as="image" href="{{ $game->getHeroImageUrl() }}" fetchpriority="high">
    @endif
    @if($game->cover_image_id)
        <link rel="preload" as="image" href="{{ $game->getCoverUrl('cover_big') }}" fetchpriority="high">
    @endif
@endpush

@section('content')
    <div class="min-h-screen bg-gray-900 text-white pb-20 md:pb-0">
        <!-- Hero with Trailer or Header -->
        <div class="relative h-96 overflow-hidden">
            @if($game->steam_data['header_image'] ?? null)
            <img src="{{ $game->steam_data['header_image'] }}"
                 class="absolute inset-0 w-full h-full object-cover"
                 onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='block';">
            <x-game-cover-placeholder :gameName="$game->name" class="absolute inset-0 w-full h-full" style="display: none;" />
            @else
            <div class="absolute inset-0 w-full h-full z-0 bg-gradient-to-br from-gray-800 to-gray-900 flex flex-col items-center justify-center p-4 text-center">
                <img src="{{ $game->getHeroImageUrl() }}"
                     id="hero-background-image"
                     class="absolute inset-0 w-full h-full object-cover"
                     loading="eager"
                     fetchpriority="high"
                     onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <p class="text-white font-semibold text-sm md:text-base mb-6 line-clamp-2 px-2 relative z-10" style="display: none;">
                    {{ $game->name }}
                </p>
                <img src="{{ asset('images/game-controller.svg') }}"
                     alt="Game Controller"
                     class="w-24 h-24 max-w-full opacity-70 relative z-10"
                     style="display: none;">
            </div>
            @endif

            <!-- Dark Overlay for Readability -->
            <div class="absolute inset-0 bg-gradient-to-t from-black via-black/70 to-transparent z-[1]"></div>

            <!-- Left-Aligned Container -->
            <div class="absolute inset-0 flex items-center justify-start z-10">
                <div class="container mx-auto px-8 w-full">
                    <h1 class="text-5xl md:text-5xl font-black mb-6 drop-shadow-2xl text-white text-left max-w-[50%] break-words">
                        {{ $game->name }}
                    </h1>

                    <!-- Developers -->
                    @if($game->getDevelopers()->count() > 0)
                        <div class="mb-0">
                            <span class="text-gray-400 text-sm">Developer{{ $game->getDevelopers()->count() > 1 ? 's' : '' }}: </span>
                            <span class="text-white text-sm font-semibold">
                                {{ $game->getDevelopers()->pluck('name')->join(', ') }}
                            </span>
                        </div>
                    @endif

                    <!-- Publishers -->
                    @if($game->getPublishers()->count() > 0)
                        <div class="mb-4">
                            <span class="text-gray-400 text-sm">Publisher{{ $game->getPublishers()->count() > 1 ? 's' : '' }}: </span>
                            <span class="text-white text-sm font-semibold">
                                {{ $game->getPublishers()->pluck('name')->join(', ') }}
                            </span>
                        </div>
                    @endif

                    <!-- Platforms -->
                    @php
                        // On game detail page, show ALL platforms (not just active ones)
                        // Filter out platforms that don't have an enum AND have "Unknown Platform" name
                        $displayPlatforms = $game->platforms
                            ? $game->platforms->filter(function($p) {
                                $enum = \App\Enums\PlatformEnum::fromIgdbId($p->igdb_id);
                                $hasEnum = $enum !== null;
                                $hasValidName = $p->name !== 'Unknown Platform' && !empty($p->name);
                                return $hasEnum || $hasValidName;
                            })
                            : collect();
                    @endphp
                    @if($displayPlatforms->count() > 0)
                        <div class="flex flex-wrap gap-2 mb-4">
                            @foreach($displayPlatforms as $plat)
                                @php
                                    $enum = \App\Enums\PlatformEnum::fromIgdbId($plat->igdb_id);
                                    $colorClass = match($enum?->color() ?? 'gray') {
                                        'blue' => 'bg-blue-600',
                                        'green' => 'bg-green-600',
                                        'red' => 'bg-red-600',
                                        'gray' => 'bg-gray-600',
                                        default => 'bg-gray-600',
                                    };
                                @endphp
                                <span class="px-3 py-1.5 {{ $colorClass }} text-white font-semibold rounded-md text-sm shadow-lg">
                                    {{ $enum?->label() ?? $plat->name }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    <!-- Game Type Badge -->
                    @if(true)
                        <div class="mb-6">
                            <span class="{{ $game->getGameTypeEnum()->colorClass() }} px-2 py-0.5 text-xs font-medium rounded text-white">
                                {{ $game->getGameTypeEnum()->label() }}
                            </span>
                        </div>
                    @endif

                    <!-- Wishlist Count -->
                    @if($game->steam_data['wishlist_formatted'] ?? null)
                        <p class="text-2xl md:text-3xl font-bold text-orange-400 drop-shadow-lg text-left">
                            🔥 {{ $game->steam_data['wishlist_formatted'] }} wishlists on Steam
                        </p>
                    @endif

                </div>
            </div>
        </div>

        <div class="container mx-auto px-8 py-12">
            <!-- Main Content + Sidebar Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-10">
                <!-- Main Content (left 3/4) -->
                <div class="lg:col-span-3 space-y-12">
                    <!-- Summary -->
                    <section>
                        <h2 class="text-3xl font-bold mb-6 flex items-center">
                            <svg class="w-8 h-8 mr-3 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                <path fill-rule="evenodd"
                                      d="M2 10a8 8 0 1116 0 8 8 0 01-16 0zm1 0a7 7 0 1014 0 7 7 0 00-14 0z"
                                      clip-rule="evenodd"/>
                            </svg>
                            About
                        </h2>
                        <p class="text-lg text-gray-300 leading-relaxed">{{ $game->summary ?? 'No summary available.' }}</p>
                    </section>

                    <!-- Steam Reviews -->
                    @if($game->steam_data['reviews_summary']['rating'] ?? null)
                        <section class="bg-gray-800 p-6 rounded-xl">
                            <h3 class="text-2xl font-bold mb-4">Steam Reviews</h3>
                            <div class="flex items-center gap-6">
                                <div class="text-5xl font-black text-green-400">
                                    {{ $game->steam_data['reviews_summary']['percentage'] ?? 'N/A' }}%
                                </div>
                                <div>
                                    <p class="text-xl font-bold">{{ $game->steam_data['reviews_summary']['rating'] }}</p>
                                    <p class="text-gray-400">
                                        from {{ number_format($game->steam_data['reviews_summary']['total'] ?? 0) }}
                                        reviews</p>
                                </div>
                            </div>
                        </section>
                    @endif

                    <!-- Trailers -->
                    @if($game->trailers && count($game->trailers) > 0)
                        <section>
                            <h2 class="text-3xl font-bold mb-6 flex items-center">
                                <svg class="w-8 h-8 mr-3 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/>
                                </svg>
                                Trailers
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                @foreach(collect($game->trailers)->take(4) as $trailer)
                                    @if(!empty($trailer['video_id']))
                                        <div
                                            class="rounded-xl overflow-hidden shadow-2xl aspect-video relative group cursor-pointer"
                                            x-data="{ playing: false }"
                                            @click="playing = true"
                                        >
                                            <template x-if="!playing">
                                                <div class="w-full h-full relative">
                                                    <img
                                                        src="{{ $game->getYouTubeThumbnailUrl($trailer['video_id']) }}"
                                                        alt="{{ $trailer['name'] ?? 'Trailer' }}"
                                                        class="w-full h-full object-cover"
                                                        loading="lazy"
                                                    >
                                                    <div class="absolute inset-0 bg-black/30 group-hover:bg-black/40 transition-colors flex items-center justify-center">
                                                        <div class="w-16 h-16 md:w-20 md:h-20 bg-red-600 rounded-full flex items-center justify-center group-hover:scale-110 transition-transform shadow-lg">
                                                            <svg class="w-8 h-8 md:w-10 md:h-10 text-white ml-1" fill="currentColor" viewBox="0 0 20 20">
                                                                <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/>
                                                            </svg>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                            <template x-if="playing">
                                                <iframe
                                                    src="{{ $game->getYouTubeEmbedUrl($trailer['video_id']) }}&autoplay=1"
                                                    class="w-full h-full"
                                                    frameborder="0"
                                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                    allowfullscreen>
                                                </iframe>
                                            </template>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <!-- Screenshots -->
                    @if($game->screenshots && count($game->screenshots) > 0)
                        <section>
                            <h2 class="text-3xl font-bold mb-6 flex items-center">
                                <svg class="w-8 h-8 mr-3 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                                </svg>
                                Screenshots
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                @foreach(collect($game->screenshots)->take(6) as $shot)
                                    <div
                                        class="rounded-xl overflow-hidden shadow-2xl hover:scale-105 transition-transform cursor-pointer">
                                        <img
                                            src="{{ app(\App\Services\IgdbService::class)->getScreenshotUrl($shot['image_id'], 'screenshot_big') }}"
                                            class="w-full h-auto"
                                            loading="lazy"
                                            alt="Screenshot">
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                </div>

                <!-- Sidebar -->
                <div class="space-y-8">
                    <!-- Collection Actions (Desktop) -->
                    <div class="hidden md:block">
                        <x-game-detail-collection :game="$game" />
                    </div>

                    <!-- System Lists (Admin Only) -->
                    <x-add-to-list :game="$game" />

                    <!-- Release Dates -->
                    <div class="bg-gray-800 p-6 rounded-xl">
                        <h3 class="text-xl font-bold mb-4">Release Dates</h3>
                        @php
                            $activePlatformIds = $platformEnums->keys()->toArray();

                            // Get release dates from relationship and filter for active platforms only
                            $filteredDates = $game->releaseDates
                                ->filter(function ($rd) use ($activePlatformIds) {
                                    return $rd->platform_id && in_array($rd->platform->igdb_id ?? null, $activePlatformIds);
                                })
                                ->map(function ($rd) {
                                    // Transform to array format for compatibility with existing view logic
                                    return [
                                        'platform' => $rd->platform->igdb_id ?? null,
                                        'date' => $rd->date?->timestamp,
                                        'date_only' => $rd->date?->format('Y-m-d'),
                                        'release_date' => $rd->formatted_date,
                                        'status' => $rd->status?->igdb_id,
                                        'status_name' => $rd->status_name,
                                        'status_abbreviation' => $rd->status_abbreviation,
                                        'human' => $rd->human_readable,
                                        'region' => $rd->region,
                                    ];
                                });

                            // Filter out dates without status when there's a date with status for the same platform/day
                            $filteredDates = $filteredDates->groupBy(function($rd) {
                                return $rd['platform'] . '_' . $rd['date_only'];
                            })->map(function($group) {
                                // If any date in this platform/day group has a status, only keep those with status
                                $withStatus = $group->filter(fn($rd) => $rd['status_name'] !== null);
                                return $withStatus->isNotEmpty() ? $withStatus : $group;
                            })->flatten(1);

                            // Group by platform and sort each group by date
                            $groupedByPlatform = $filteredDates
                                ->groupBy('platform')
                                ->map(function ($dates) {
                                    return $dates->sortBy('date')->values();
                                })
                                ->sortBy(function ($dates) {
                                    // Sort platforms by earliest date
                                    return $dates->first()['date'] ?? PHP_INT_MAX;
                                });
                        @endphp
                        @if($groupedByPlatform->count() > 0)
                            <div class="space-y-3" x-data="{ expandedPlatforms: {} }">
                                @foreach($groupedByPlatform as $platformId => $releaseDates)
                                    @php
                                        $platformEnum = $platformEnums[$platformId] ?? null;
                                        $platformName = $platformEnum?->label() ?? 'Unknown Platform';
                                        $earliestDate = $releaseDates->first();
                                        $hasMultipleDates = $releaseDates->count() > 1;
                                        $additionalCount = $releaseDates->count() - 1;
                                        $platformKey = "platform_{$platformId}";

                                        // Get platform color for border
                                        $platformColor = $platformEnum?->color() ?? 'gray';
                                        $borderColorClass = match($platformColor) {
                                            'blue' => 'border-blue-500',
                                            'green' => 'border-green-500',
                                            'red' => 'border-red-500',
                                            'gray' => 'border-gray-500',
                                            default => 'border-gray-500',
                                        };
                                    @endphp

                                    <div class="border-l-2 {{ $borderColorClass }} pl-3">
                                        {{-- Platform Row (always visible) --}}
                                        <div
                                            @class([
                                                'flex items-center justify-between',
                                                'cursor-pointer hover:bg-gray-700/30 -ml-3 pl-3 -mr-6 pr-6 py-1.5 rounded transition-colors' => $hasMultipleDates
                                            ])
                                            @if($hasMultipleDates)
                                                @click="expandedPlatforms['{{ $platformKey }}'] = !expandedPlatforms['{{ $platformKey }}']"
                                            @endif
                                        >
                                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                                {{-- Expand/Collapse Icon --}}
                                                @if($hasMultipleDates)
                                                    <svg
                                                        class="w-4 h-4 text-gray-400 transition-transform flex-shrink-0"
                                                        :class="{ 'rotate-90': expandedPlatforms['{{ $platformKey }}'] }"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                    </svg>
                                                @else
                                                    <div class="w-4 flex-shrink-0"></div>
                                                @endif

                                                <span class="text-gray-300 font-medium truncate">{{ $platformName }}</span>
                                            </div>

                                            <div class="flex items-center gap-2 flex-shrink-0">
                                                    <span class="text-gray-300 font-medium text-sm">
                                                        {{ $earliestDate['release_date'] ?? 'TBA' }}
                                                    </span>

                                                @if($hasMultipleDates)
                                                    <span class="bg-blue-500/20 text-blue-400 text-xs px-2 py-0.5 rounded-full font-medium">
                                                            +{{ $additionalCount }}
                                                        </span>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Expanded Dates (collapsible) --}}
                                        @if($hasMultipleDates)
                                            <div
                                                x-show="expandedPlatforms['{{ $platformKey }}']"
                                                x-collapse
                                                class="mt-2 space-y-1.5 ml-6"
                                            >
                                                @foreach($releaseDates as $index => $rd)
                                                    @php
                                                        $releaseDate = $rd['release_date'] ?? 'TBA';
                                                        $statusAbbr = $rd['status_abbreviation'] ?? $rd['status_name'] ?? '';

                                                        // Color coding for statuses
                                                        $statusColor = match($rd['status'] ?? null) {
                                                            1 => 'text-yellow-400',      // Alpha
                                                            2 => 'text-orange-400',      // Beta
                                                            3 => 'text-blue-400',        // Early Access
                                                            4 => 'text-gray-500',        // Offline
                                                            5 => 'text-red-400',         // Cancelled
                                                            6 => 'text-green-400',       // Full Release
                                                            34 => 'text-purple-400',     // Advanced Access
                                                            35 => 'text-cyan-400',       // Digital Compatibility
                                                            36 => 'text-indigo-400',     // Next-Gen Optimization
                                                            default => 'text-gray-300'
                                                        };

                                                        $statusBgColor = match($rd['status'] ?? null) {
                                                            1 => 'bg-yellow-500/10',
                                                            2 => 'bg-orange-500/10',
                                                            3 => 'bg-blue-500/10',
                                                            4 => 'bg-gray-500/10',
                                                            5 => 'bg-red-500/10',
                                                            6 => 'bg-green-500/10',
                                                            34 => 'bg-purple-500/10',
                                                            35 => 'bg-cyan-500/10',
                                                            36 => 'bg-indigo-500/10',
                                                            default => 'bg-gray-500/10'
                                                        };
                                                    @endphp

                                                    <div class="flex items-center justify-between text-sm py-1">
                                                        <div class="flex items-center gap-2">
                                                            <svg class="w-3 h-3 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                                                <circle cx="10" cy="10" r="3"/>
                                                            </svg>
                                                            <span class="text-gray-400">{{ $releaseDate }}</span>
                                                        </div>

                                                        @if($statusAbbr)
                                                            <span class="{{ $statusColor }} {{ $statusBgColor }} text-xs px-2 py-0.5 rounded font-medium">
                                                                    {{ $statusAbbr }}
                                                                </span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-400">
                                {{ $game->first_release_date?->format('d/m/Y') ?? 'TBA' }}
                            </p>
                        @endif
                    </div>

                    <div class="bg-gray-800 p-6 rounded-xl">
                        <h3 class="text-xl font-bold mb-4">Game Info</h3>
                        <!-- Game Genres -->
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-5">Genres</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($game->genres as $genre)
                                <span class="px-3 py-1.5 bg-purple-700 rounded-md text-sm">{{ $genre->name }}</span>
                            @endforeach
                        </div>
                        <!-- Game Modes -->
                        <p class="text-xs text-gray-400 uppercase tracking-wide mt-5 mb-5">Modes</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($game->gameModes as $mode)
                                @php
                                    // Select icon based on IGDB game mode ID
                                    $iconSvg = match($mode->igdb_id) {
                                        1 => '<path d="M10 9a3 3 0 100-6 3 3 0 000 6zM10 11a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6z"/>', // Single player - User icon
                                        2 => '<path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>', // Multiplayer - Users icon
                                        3 => '<path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM16 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 15v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 8v1a4 4 0 01-4 4h-3a4 4 0 01-4-4V8a3.005 3.005 0 013.75-2.906A5.972 5.972 0 006 12v3h10z"/>', // Co-operative - Users together icon
                                        4 => '<path fill-rule="evenodd" d="M3 4a1 1 0 011-1h5a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm8 0a1 1 0 011-1h5a1 1 0 011 1v12a1 1 0 01-1 1h-5a1 1 0 01-1-1V4z" clip-rule="evenodd"/>', // Split screen - Two rectangles/screens icon
                                        5 => '<path fill-rule="evenodd" d="M2 5a2 2 0 012-2h8a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm3 1h6v4H5V6zm6 6H5v2h6v-2z" clip-rule="evenodd"/><path d="M15 7h1a2 2 0 012 2v5.5a1.5 1.5 0 01-3 0V7z"/>', // MMO - Network/computer icon
                                        6 => '<path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>', // Battle Royale - Trophy/badge icon
                                        default => '<path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>', // Default - Users icon
                                    };
                                @endphp
                                <span class="px-3 py-1.5 bg-indigo-700 rounded-md text-sm flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        {!! $iconSvg !!}
                                    </svg>
                                    {{ $mode->name }}
                                </span>
                            @endforeach
                        </div>
                        <!-- Game Engine -->
                        <p class="text-xs text-gray-400 uppercase tracking-wide mt-5 mb-5">Engine</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($game->gameEngines as $engine)
                                <span class="px-3 py-1.5 bg-cyan-700 rounded-md text-sm">{{ $engine->name }}</span>
                            @endforeach
                        </div>
                        <!-- Player Perspectives -->
                        <p class="text-xs text-gray-400 uppercase tracking-wide mt-5 mb-5">Player Perspectives</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($game->playerPerspectives as $perspective)
                                <span class="px-3 py-1.5 bg-teal-700 rounded-md text-sm">{{ $perspective->name }}</span>
                            @endforeach
                        </div>
                    </div>

                </div>
            </div>

            <!-- Full-Width Similar Games Section (spans both columns) -->
            <div class="mt-10 -mx-8 px-8 bg-gray-800/50 rounded-xl py-3" id="similar-games-container">
                <div class="container mx-auto">
                    <!-- Loading state -->
                    <div id="similar-games-loading" class="text-center py-8">
                        <svg class="inline-block w-8 h-8 animate-spin text-orange-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-gray-400 mt-2">Loading similar games...</p>
                    </div>
                    <!-- Content will be loaded here via AJAX -->
                    <div id="similar-games-content" style="display: none;"></div>
                </div>
            </div>
        </div>

        <!-- Mobile Sticky Collection Bar -->
        <x-game-detail-collection-mobile :game="$game" />
        @endsection

        @push('scripts')
            <script>
                // Lazy load similar games after page load
                document.addEventListener('DOMContentLoaded', function () {
                    const gameSlug = '{{ $game->slug }}';
                    const loadingEl = document.getElementById('similar-games-loading');
                    const contentEl = document.getElementById('similar-games-content');

                    if (!loadingEl || !contentEl || !gameSlug) {
                        if (loadingEl) loadingEl.style.display = 'none';
                        return;
                    }

                    // Load similar games HTML via AJAX after a short delay to prioritize main content
                    setTimeout(() => {
                        fetch(`/game/${gameSlug}/similar-games-html`)
                            .then(r => {
                                if (!r.ok) throw new Error('Failed to fetch');
                                return r.text();
                            })
                            .then(html => {
                                loadingEl.style.display = 'none';
                                contentEl.innerHTML = html;
                                contentEl.style.display = 'block';
                            })
                            .catch(error => {
                                console.error('Failed to load similar games:', error);
                                loadingEl.style.display = 'none';
                                contentEl.innerHTML = '<p class="text-gray-400 text-center py-8">No similar games available.</p>';
                                contentEl.style.display = 'block';
                            });
                    }, 500); // Small delay to let main content render first
                });
            </script>
    @endpush
