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
    <x-game.hero :game="$game" :summary="$releaseSummary" />

    {{-- ─── MAIN CONTENT ────────────────────────────────────────────────── --}}
    <div class="page-shell py-8">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

            {{-- ── Left column (3/4) ─────────────────────────────────── --}}
            <div class="lg:col-span-3 space-y-6">

                {{-- About --}}
                <div class="neon-section-frame" id="about">
                    <x-homepage.section-heading icon="about" title="About" />
                    <p class="mt-4 text-[0.9rem] leading-relaxed text-slate-300">
                        {{ $game->summary ?? 'No summary available.' }}
                    </p>
                </div>

                {{-- Critic & Review Scores --}}
                @if($game->metacritic_score || $game->steam_review_percent !== null || $game->igdb_aggregated_rating)
                    <div class="neon-section-frame scroll-mt-32" id="scores">
                        <x-homepage.section-heading icon="star" title="Scores" />
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-6">

                            {{-- Metacritic --}}
                            <div class="flex items-center gap-4">
                                @if($game->metacritic_score)
                                    <div class="text-5xl font-black {{ $game->metacriticColorClass() }}">{{ $game->metacritic_score }}</div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-100">Metacritic</p>
                                        @if($game->metacritic_url)
                                            <a href="{{ $game->metacritic_url }}" target="_blank" rel="noopener noreferrer"
                                               class="text-xs text-cyan-400 hover:underline">View reviews</a>
                                        @endif
                                    </div>
                                @else
                                    <div class="text-5xl font-black text-slate-700">&mdash;</div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-400">Metacritic</p>
                                        <p class="text-xs text-slate-500">No score</p>
                                    </div>
                                @endif
                            </div>

                            {{-- Steam reviews --}}
                            <div class="flex items-center gap-4">
                                @if($game->steam_review_percent !== null)
                                    <div class="text-5xl font-black {{ $game->steamReviewSentiment()?->colorClass() ?? 'text-slate-100' }}">{{ $game->steam_review_percent }}%</div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-100">{{ $game->steamReviewSentiment()?->label() ?? 'Steam' }}</p>
                                        <p class="text-xs text-slate-400">{{ number_format($game->steam_review_total ?? 0) }} Steam reviews</p>
                                    </div>
                                @else
                                    <div class="text-5xl font-black text-slate-700">&mdash;</div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-400">Steam</p>
                                        <p class="text-xs text-slate-500">No reviews</p>
                                    </div>
                                @endif
                            </div>

                            {{-- IGDB critics --}}
                            <div class="flex items-center gap-4">
                                @if($game->igdb_aggregated_rating)
                                    <div class="text-5xl font-black text-purple-400 drop-shadow-[0_0_12px_rgba(192,132,252,0.5)]">{{ $game->igdb_aggregated_rating }}</div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-100">IGDB Critics</p>
                                        <p class="text-xs text-slate-400">{{ number_format($game->igdb_aggregated_rating_count ?? 0) }} ratings</p>
                                    </div>
                                @else
                                    <div class="text-5xl font-black text-slate-700">&mdash;</div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-400">IGDB Critics</p>
                                        <p class="text-xs text-slate-500">Not rated</p>
                                    </div>
                                @endif
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
                    <div class="neon-section-frame scroll-mt-32" id="screenshots">
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
                @feature('game_user_actions')
                    @auth
                        <div class="hidden md:block">
                            <x-game-detail-collection :game="$game" />
                        </div>
                    @endauth
                @endfeature

                {{-- System Lists (Admin Only) --}}
                <x-add-to-list :game="$game" />

                {{-- Release Dates --}}
                <div class="neon-section-frame scroll-mt-32" id="release-dates">
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
        <div class="mt-6 scroll-mt-32" id="similar-games">
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
    @feature('game_user_actions')
        <x-game-detail-collection-mobile :game="$game" />
    @endfeature

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
