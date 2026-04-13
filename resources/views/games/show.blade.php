@extends('layouts.app')

@section('title', $game->name)

@section('body-class', 'neon-body')

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
<div class="theme-neon overflow-x-hidden">

    {{-- ─── HERO ─────────────────────────────────────────────────────────── --}}
    <div class="relative overflow-hidden" style="height:320px">

        {{-- Background image --}}
        @if($game->steam_data['header_image'] ?? null)
            <img src="{{ $game->steam_data['header_image'] }}"
                 class="absolute inset-0 w-full h-full object-cover"
                 onerror="this.onerror=null;this.style.display='none'">
        @elseif($game->hero_image_id)
            <img src="{{ $game->getHeroImageUrl() }}"
                 class="absolute inset-0 w-full h-full object-cover"
                 loading="eager"
                 fetchpriority="high"
                 onerror="this.onerror=null;this.style.display='none'">
        @else
            <div class="absolute inset-0 bg-gradient-to-br from-slate-800 to-[#121522]"></div>
        @endif

        {{-- Gradient overlays --}}
        <div class="absolute inset-0 bg-gradient-to-t from-[#121522] via-[#121522]/65 to-transparent"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-[#121522]/80 via-[#121522]/20 to-transparent"></div>

        {{-- Hero content --}}
        <div class="absolute bottom-0 left-0 right-0 pb-7">
            <div class="page-shell">
                <div class="flex items-end gap-5">

                    {{-- Cover art --}}
                    @if($game->cover_image_id)
                        <div class="hidden sm:block shrink-0 [transform:translateZ(0)] overflow-hidden rounded-xl border border-white/10 shadow-xl"
                             style="width:100px;height:133px">
                            <img src="{{ $game->getCoverUrl('cover_big') }}"
                                 alt="{{ $game->name }}"
                                 class="h-full w-full object-cover">
                        </div>
                    @endif

                    {{-- Title & meta --}}
                    <div class="flex-1 min-w-0">

                        {{-- Developers / Publishers --}}
                        @if($game->getDevelopers()->count() > 0 || $game->getPublishers()->count() > 0)
                            <p class="mb-1.5 text-[0.72rem] font-semibold uppercase tracking-[0.08em] text-slate-400">
                                @if($game->getDevelopers()->count() > 0)
                                    <span class="text-orange-400">{{ $game->getDevelopers()->pluck('name')->join(', ') }}</span>
                                @endif
                                @if($game->getDevelopers()->count() > 0 && $game->getPublishers()->count() > 0)
                                    <span class="text-white/20 mx-1">·</span>
                                @endif
                                @if($game->getPublishers()->count() > 0)
                                    <span class="text-slate-500">{{ $game->getPublishers()->pluck('name')->join(', ') }}</span>
                                @endif
                            </p>
                        @endif

                        <h1 class="text-3xl md:text-4xl font-black text-white tracking-tight leading-tight mb-3 drop-shadow-2xl">
                            {{ $game->name }}
                        </h1>

                        {{-- Platforms --}}
                        @php
                            $displayPlatforms = $game->platforms
                                ? $game->platforms->filter(function($p) {
                                    $enum = \App\Enums\PlatformEnum::fromIgdbId($p->igdb_id);
                                    return $enum !== null || ($p->name !== 'Unknown Platform' && !empty($p->name));
                                })
                                : collect();
                        @endphp
                        @if($displayPlatforms->count() > 0)
                            <div class="flex flex-wrap gap-1.5 mb-3">
                                @foreach($displayPlatforms as $plat)
                                    @php $enum = \App\Enums\PlatformEnum::fromIgdbId($plat->igdb_id); @endphp
                                    <span class="neon-platform-pill">{{ $enum?->label() ?? $plat->name }}</span>
                                @endforeach
                            </div>
                        @endif

                        {{-- Game type + wishlist --}}
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="{{ $game->getGameTypeEnum()->neonColorClass() }} px-2.5 py-0.5 text-xs font-semibold rounded-full">
                                {{ $game->getGameTypeEnum()->label() }}
                            </span>
                            @if($game->steam_data['wishlist_formatted'] ?? null)
                                <span class="text-orange-400 text-sm font-bold">
                                    🔥 {{ $game->steam_data['wishlist_formatted'] }} wishlists on Steam
                                </span>
                            @endif
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── MAIN CONTENT ────────────────────────────────────────────────── --}}
    <div class="page-shell py-8">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

            {{-- ── Left column (3/4) ─────────────────────────────────── --}}
            <div class="lg:col-span-3 space-y-6">

                {{-- About --}}
                <div class="neon-section-frame">
                    <x-homepage.section-heading icon="about" title="About" />
                    <p class="mt-4 text-[0.9rem] leading-relaxed text-slate-300">
                        {{ $game->summary ?? 'No summary available.' }}
                    </p>
                </div>

                {{-- Steam Reviews --}}
                @if($game->steam_data['reviews_summary']['rating'] ?? null)
                    <div class="neon-section-frame">
                        <x-homepage.section-heading icon="star" title="Steam Reviews" />
                        <div class="mt-4 flex items-center gap-6">
                            <div class="text-5xl font-black text-green-400 drop-shadow-[0_0_12px_rgba(74,222,128,0.5)]">
                                {{ $game->steam_data['reviews_summary']['percentage'] ?? 'N/A' }}%
                            </div>
                            <div>
                                <p class="text-lg font-bold text-slate-100">
                                    {{ $game->steam_data['reviews_summary']['rating'] }}
                                </p>
                                <p class="text-sm text-slate-400">
                                    from {{ number_format($game->steam_data['reviews_summary']['total'] ?? 0) }} reviews
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Trailers --}}
                @if($game->trailers && count($game->trailers) > 0)
                    <div class="neon-section-frame">
                        <x-homepage.section-heading icon="video" title="Trailers" />
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach(collect($game->trailers)->take(4) as $trailer)
                                @if(!empty($trailer['video_id']))
                                    <div class="overflow-hidden rounded-xl aspect-video relative group cursor-pointer border border-white/[0.06]"
                                         x-data="{ playing: false }"
                                         @click="playing = true">
                                        <template x-if="!playing">
                                            <div class="w-full h-full relative">
                                                <img src="{{ $game->getYouTubeThumbnailUrl($trailer['video_id']) }}"
                                                     alt="{{ $trailer['name'] ?? 'Trailer' }}"
                                                     class="w-full h-full object-cover"
                                                     loading="lazy">
                                                <div class="absolute inset-0 bg-black/30 group-hover:bg-black/40 transition-colors flex items-center justify-center">
                                                    <div class="w-14 h-14 bg-red-600 rounded-full flex items-center justify-center group-hover:scale-110 transition-transform shadow-lg">
                                                        <svg class="w-7 h-7 text-white ml-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/>
                                                        </svg>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="playing">
                                            <iframe src="{{ $game->getYouTubeEmbedUrl($trailer['video_id']) }}&autoplay=1"
                                                    class="w-full h-full"
                                                    frameborder="0"
                                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                    allowfullscreen></iframe>
                                        </template>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Screenshots --}}
                @if($game->screenshots && count($game->screenshots) > 0)
                    <div class="neon-section-frame">
                        <x-homepage.section-heading icon="photo" title="Screenshots" />
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach(collect($game->screenshots)->take(6) as $shot)
                                <div class="overflow-hidden rounded-xl border border-white/[0.06] group cursor-pointer">
                                    <img src="{{ app(\App\Services\IgdbService::class)->getScreenshotUrl($shot['image_id'], 'screenshot_big') }}"
                                         class="w-full h-auto transition-transform duration-500 group-hover:scale-105"
                                         loading="lazy"
                                         alt="Screenshot">
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>

            {{-- ── Sidebar (1/4) ─────────────────────────────────────── --}}
            <div class="space-y-4">

                {{-- Collection Actions (Desktop) --}}
                @auth
                    <div class="hidden md:block">
                        <x-game-detail-collection :game="$game" />
                    </div>
                @endauth

                {{-- System Lists (Admin Only) --}}
                <x-add-to-list :game="$game" />

                {{-- Release Dates --}}
                <div class="neon-section-frame">
                    <p class="mb-4 text-[0.65rem] font-bold uppercase tracking-[0.12em] text-cyan-400">Release Dates</p>
                    @php
                        $activePlatformIds = $platformEnums->keys()->toArray();

                        $filteredDates = $game->releaseDates
                            ->filter(function ($rd) use ($activePlatformIds) {
                                return $rd->platform_id && in_array($rd->platform->igdb_id ?? null, $activePlatformIds);
                            })
                            ->map(function ($rd) {
                                return [
                                    'platform'             => $rd->platform->igdb_id ?? null,
                                    'date'                 => $rd->date?->timestamp,
                                    'date_only'            => $rd->date?->format('Y-m-d'),
                                    'release_date'         => $rd->formatted_date,
                                    'status'               => $rd->status?->igdb_id,
                                    'status_name'          => $rd->status_name,
                                    'status_abbreviation'  => $rd->status_abbreviation,
                                    'human'                => $rd->human_readable,
                                    'region'               => $rd->region,
                                ];
                            });

                        $filteredDates = $filteredDates->groupBy(function($rd) {
                            return $rd['platform'] . '_' . $rd['date_only'];
                        })->map(function($group) {
                            $withStatus = $group->filter(fn($rd) => $rd['status_name'] !== null);
                            return $withStatus->isNotEmpty() ? $withStatus : $group;
                        })->flatten(1);

                        $groupedByPlatform = $filteredDates
                            ->groupBy('platform')
                            ->map(fn($dates) => $dates->sortBy('date')->values())
                            ->sortBy(fn($dates) => $dates->first()['date'] ?? PHP_INT_MAX);
                    @endphp

                    @if($groupedByPlatform->count() > 0)
                        <div class="space-y-2" x-data="{ expandedPlatforms: {} }">
                            @foreach($groupedByPlatform as $platformId => $releaseDates)
                                @php
                                    $platformEnum    = $platformEnums[$platformId] ?? null;
                                    $platformName    = $platformEnum?->label() ?? 'Unknown Platform';
                                    $earliestDate    = $releaseDates->first();
                                    $hasMultipleDates = $releaseDates->count() > 1;
                                    $additionalCount = $releaseDates->count() - 1;
                                    $platformKey     = "platform_{$platformId}";
                                @endphp

                                <div class="border-l-2 border-cyan-400/30 pl-3">
                                    <div
                                        @class([
                                            'flex items-center justify-between',
                                            'cursor-pointer hover:bg-white/[0.03] -ml-3 pl-3 -mr-3 pr-3 py-1 rounded transition-colors' => $hasMultipleDates,
                                        ])
                                        @if($hasMultipleDates)
                                            @click="expandedPlatforms['{{ $platformKey }}'] = !expandedPlatforms['{{ $platformKey }}']"
                                        @endif
                                    >
                                        <div class="flex items-center gap-2 flex-1 min-w-0">
                                            @if($hasMultipleDates)
                                                <svg class="w-3.5 h-3.5 text-slate-500 transition-transform flex-shrink-0"
                                                     :class="{ 'rotate-90': expandedPlatforms['{{ $platformKey }}'] }"
                                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            @else
                                                <div class="w-3.5 flex-shrink-0"></div>
                                            @endif
                                            <span class="text-[0.8rem] text-slate-300 truncate">{{ $platformName }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            <span class="text-[0.8rem] text-slate-400">
                                                {{ $earliestDate['release_date'] ?? 'TBA' }}
                                            </span>
                                            @if($hasMultipleDates)
                                                <span class="bg-cyan-400/10 text-cyan-400 text-[0.65rem] px-1.5 py-0.5 rounded-full font-semibold">
                                                    +{{ $additionalCount }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    @if($hasMultipleDates)
                                        <div x-show="expandedPlatforms['{{ $platformKey }}']"
                                             x-collapse
                                             class="mt-1.5 space-y-1 ml-5">
                                            @foreach($releaseDates as $rd)
                                                @php
                                                    $releaseDate = $rd['release_date'] ?? 'TBA';
                                                    $statusAbbr  = $rd['status_abbreviation'] ?? $rd['status_name'] ?? '';
                                                    $statusColor = match($rd['status'] ?? null) {
                                                        1  => 'text-yellow-400 bg-yellow-500/10',
                                                        2  => 'text-orange-400 bg-orange-500/10',
                                                        3  => 'text-blue-400 bg-blue-500/10',
                                                        4  => 'text-slate-500 bg-slate-500/10',
                                                        5  => 'text-red-400 bg-red-500/10',
                                                        6  => 'text-green-400 bg-green-500/10',
                                                        34 => 'text-purple-400 bg-purple-500/10',
                                                        35 => 'text-cyan-400 bg-cyan-500/10',
                                                        36 => 'text-indigo-400 bg-indigo-500/10',
                                                        default => 'text-slate-400 bg-slate-500/10',
                                                    };
                                                @endphp
                                                <div class="flex items-center justify-between text-[0.75rem] py-0.5">
                                                    <span class="text-slate-400">{{ $releaseDate }}</span>
                                                    @if($statusAbbr)
                                                        <span class="{{ $statusColor }} px-1.5 py-0.5 rounded text-[0.65rem] font-semibold">
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
                        <p class="text-sm text-slate-400">{{ $game->first_release_date?->format('d/m/Y') ?? 'TBA' }}</p>
                    @endif
                </div>

                {{-- Game Info --}}
                <div class="neon-section-frame space-y-4">
                    <p class="text-[0.65rem] font-bold uppercase tracking-[0.12em] text-cyan-400">Game Info</p>

                    @if($game->genres->count() > 0)
                        <div>
                            <p class="mb-2 text-[0.65rem] uppercase tracking-[0.1em] text-cyan-400/60">Genres</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($game->genres as $genre)
                                    <span class="neon-platform-pill">{{ $genre->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($game->gameModes->count() > 0)
                        <div>
                            <p class="mb-2 text-[0.65rem] uppercase tracking-[0.1em] text-cyan-400/60">Modes</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($game->gameModes as $mode)
                                    @php
                                        $iconSvg = match($mode->igdb_id) {
                                            1 => '<path d="M10 9a3 3 0 100-6 3 3 0 000 6zM10 11a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6z"/>',
                                            2 => '<path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>',
                                            3 => '<path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM16 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 15v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 8v1a4 4 0 01-4 4h-3a4 4 0 01-4-4V8a3.005 3.005 0 013.75-2.906A5.972 5.972 0 006 12v3h10z"/>',
                                            4 => '<path fill-rule="evenodd" d="M3 4a1 1 0 011-1h5a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm8 0a1 1 0 011-1h5a1 1 0 011 1v12a1 1 0 01-1 1h-5a1 1 0 01-1-1V4z" clip-rule="evenodd"/>',
                                            5 => '<path fill-rule="evenodd" d="M2 5a2 2 0 012-2h8a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm3 1h6v4H5V6zm6 6H5v2h6v-2z" clip-rule="evenodd"/><path d="M15 7h1a2 2 0 012 2v5.5a1.5 1.5 0 01-3 0V7z"/>',
                                            6 => '<path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>',
                                            default => '<path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>',
                                        };
                                    @endphp
                                    <span class="neon-platform-pill inline-flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">{!! $iconSvg !!}</svg>
                                        {{ $mode->name }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($game->gameEngines->count() > 0)
                        <div>
                            <p class="mb-2 text-[0.65rem] uppercase tracking-[0.1em] text-cyan-400/60">Engine</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($game->gameEngines as $engine)
                                    <span class="neon-platform-pill">{{ $engine->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($game->playerPerspectives->count() > 0)
                        <div>
                            <p class="mb-2 text-[0.65rem] uppercase tracking-[0.1em] text-cyan-400/60">Player Perspectives</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($game->playerPerspectives as $perspective)
                                    <span class="neon-platform-pill">{{ $perspective->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

            </div>
        </div>

        {{-- ── Similar Games ──────────────────────────────────────────── --}}
        <div class="mt-6" id="similar-games-container">
            <div id="similar-games-loading" class="neon-section-frame flex items-center gap-3 py-6 justify-center">
                <svg class="w-6 h-6 animate-spin text-cyan-400/60" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-sm text-slate-500">Loading similar games…</p>
            </div>
            <div id="similar-games-content" style="display: none;"></div>
        </div>

    </div>

    {{-- Mobile Sticky Collection Bar --}}
    <x-game-detail-collection-mobile :game="$game" />

</div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const gameSlug  = '{{ $game->slug }}';
            const loadingEl = document.getElementById('similar-games-loading');
            const contentEl = document.getElementById('similar-games-content');

            if (!loadingEl || !contentEl || !gameSlug) {
                if (loadingEl) { loadingEl.style.display = 'none'; }
                return;
            }

            setTimeout(() => {
                fetch(`/game/${gameSlug}/similar-games-html`)
                    .then(r => {
                        if (!r.ok) { throw new Error('Failed to fetch'); }
                        return r.text();
                    })
                    .then(html => {
                        loadingEl.style.display = 'none';
                        contentEl.innerHTML = html;
                        contentEl.style.display = 'block';
                    })
                    .catch(() => {
                        loadingEl.style.display = 'none';
                        contentEl.innerHTML = '<p class="text-sm text-slate-500 text-center py-6">No similar games available.</p>';
                        contentEl.style.display = 'block';
                    });
            }, 500);
        });
    </script>
@endpush
