@props(['game', 'summary'])

<div class="relative overflow-hidden min-h-[320px]">
    @if($game->steam_data['header_image'] ?? null)
        <img src="{{ $game->steam_data['header_image'] }}" class="absolute inset-0 w-full h-full object-cover" onerror="this.onerror=null;this.style.display='none'">
    @elseif($game->hero_image_id)
        <img src="{{ $game->getHeroImageUrl() }}" class="absolute inset-0 w-full h-full object-cover" loading="eager" fetchpriority="high" onerror="this.onerror=null;this.style.display='none'">
    @else
        <div class="absolute inset-0 bg-gradient-to-br from-slate-800 to-[#121522]"></div>
    @endif

    <div class="absolute inset-0 bg-gradient-to-t from-[#121522] via-[#121522]/75 to-[#121522]/20"></div>
    <div class="absolute inset-0 bg-gradient-to-r from-[#121522]/85 via-[#121522]/30 to-transparent"></div>

    <div class="relative">
        <div class="page-shell py-7">
            <div class="flex items-start gap-5">
                @if($game->cover_image_id)
                    <div class="hidden sm:block shrink-0 w-[120px] h-[160px] [transform:translateZ(0)] overflow-hidden rounded-xl border border-white/10 shadow-xl">
                        <img src="{{ $game->getCoverUrl('cover_big') }}" alt="{{ $game->name }}" class="h-full w-full object-cover">
                    </div>
                @endif

                <div class="flex-1 min-w-0 space-y-3">
                    <div>
                        @php
                            $devs = $game->getDevelopers()->pluck('name');
                            $pubs = $game->getPublishers()->pluck('name');
                            $maker = $devs->isNotEmpty() ? $devs->join(', ') : $pubs->join(', ');
                            $showPublisher = $devs->isNotEmpty() && $pubs->isNotEmpty() && $devs->sort()->values()->all() !== $pubs->sort()->values()->all();
                        @endphp
                        <p class="mb-1 text-[0.72rem] font-semibold uppercase tracking-[0.08em] text-slate-400">
                            @if($maker !== '')
                                <span class="text-orange-400">{{ $maker }}</span>
                            @endif
                            @if($showPublisher)
                                <span class="text-white/20 mx-1">·</span>
                                <span class="text-cyan-300 drop-shadow-[0_0_6px_rgba(99,243,255,0.45)]">Published by {{ $pubs->join(', ') }}</span>
                            @endif
                        </p>
                        <h1 class="text-3xl md:text-4xl font-black text-white tracking-tight leading-tight drop-shadow-2xl">{{ $game->name }}</h1>
                        <div class="mt-2">
                            <span class="neon-type-pill {{ $game->getGameTypeEnum()->neonColorClass() }}">{{ $game->getGameTypeEnum()->label() }}</span>
                        </div>
                    </div>

                    <x-game.hero.release-status :summary="$summary" />
                    <x-game.hero.score-snapshot :game="$game" />
                    <x-game.hero.metadata :game="$game" />
                    <x-game.hero.section-anchors :game="$game" />

                    @if($game->summary)
                        <p class="text-sm leading-relaxed text-slate-300 line-clamp-3 max-w-3xl">{{ $game->summary }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
