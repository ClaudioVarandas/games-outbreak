@props([
    'games',
    'platformEnums',
])

<section class="neon-section-frame grid gap-5">
    <x-homepage.section-heading icon="sparkles" :title="__('Latest Added Games')" />

    @if($games->isNotEmpty())
        <div class="neon-panel neon-latest-table">
            <div class="neon-latest-table__header hidden grid-cols-[minmax(0,2.4fr)_minmax(0,1.4fr)_minmax(120px,0.8fr)_minmax(100px,0.7fr)] gap-4 px-5 py-4 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-400 md:grid">
                <div>{{ __('Game') }}</div>
                <div>{{ __('Platforms') }}</div>
                <div>{{ __('Release Date') }}</div>
                <div>{{ __('Added') }}</div>
            </div>

            @foreach($games as $game)
                @php
                    $coverUrl = $game->cover_image_id
                        ? $game->getCoverUrl('cover_small')
                        : ($game->steam_data['header_image'] ?? null);
                    $linkUrl = $game->slug
                        ? route('game.show', $game)
                        : route('game.show.igdb', $game->igdb_id);
                    $validPlatformIds = $platformEnums->keys()->toArray();
                    $sortedPlatforms = $game->platforms
                        ? $game->platforms->filter(fn($platform) => in_array($platform->igdb_id, $validPlatformIds))
                            ->sortBy(fn($platform) => \App\Enums\PlatformEnum::getPriority($platform->igdb_id))
                            ->values()
                        : collect();
                @endphp

                <div class="neon-latest-table__row">
                    <a href="{{ $linkUrl }}" class="flex min-w-0 items-center gap-3">
                        <div class="h-16 w-12 overflow-hidden rounded-xl bg-slate-950/70">
                            @if($coverUrl)
                                <img src="{{ $coverUrl }}" alt="{{ $game->name }}" class="h-full w-full object-cover" loading="lazy">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-400">
                                    {{ __('TBA') }}
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold uppercase tracking-[0.04em] text-slate-100">{{ $game->name }}</p>
                            <span class="neon-type-pill {{ $game->getGameTypeEnum()->neonColorClass() }} mt-2">
                                {{ $game->getGameTypeEnum()->label() }}
                            </span>
                        </div>
                    </a>

                    <div class="hidden flex-wrap gap-2 md:flex">
                        @foreach($sortedPlatforms->take(4) as $platform)
                            @php
                                $enum = $platformEnums[$platform->igdb_id] ?? null;
                            @endphp
                            <span class="neon-platform-pill">
                                {{ $enum?->label() ?? str()->limit($platform->name, 6) }}
                            </span>
                        @endforeach
                    </div>

                    <div class="hidden text-sm uppercase tracking-[0.05em] text-slate-300 md:block">
                        {{ $game->first_release_date?->format('d M Y') ?? __('TBA') }}
                    </div>

                    <div class="hidden text-sm uppercase tracking-[0.05em] text-slate-400 md:block">
                        {{ $game->created_at->diffForHumans() }}
                    </div>
                </div>
            @endforeach

            <div class="neon-mobile-stack p-4">
                @foreach($games as $game)
                    @php
                        $coverUrl = $game->cover_image_id
                            ? $game->getCoverUrl('cover_small')
                            : ($game->steam_data['header_image'] ?? null);
                        $linkUrl = $game->slug
                            ? route('game.show', $game)
                            : route('game.show.igdb', $game->igdb_id);
                    @endphp

                    <a href="{{ $linkUrl }}" class="neon-card flex items-center gap-3 p-3">
                        <div class="h-16 w-12 overflow-hidden rounded-xl bg-slate-950/70">
                            @if($coverUrl)
                                <img src="{{ $coverUrl }}" alt="{{ $game->name }}" class="h-full w-full object-cover" loading="lazy">
                            @endif
                        </div>

                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold uppercase tracking-[0.04em] text-slate-100">{{ $game->name }}</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.08em] text-slate-400">{{ $game->first_release_date?->format('d M Y') ?? __('TBA') }}</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.08em] text-slate-500">{{ $game->created_at->diffForHumans() }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @else
        <div class="neon-panel p-8 text-center text-sm uppercase tracking-[0.08em] text-slate-400">
            {{ __('No games added yet.') }}
        </div>
    @endif
</section>
