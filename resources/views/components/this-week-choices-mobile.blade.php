@props(['games', 'platformEnums' => null])

@php
    $platformEnums = $platformEnums ?? \App\Enums\PlatformEnum::getActivePlatforms();
@endphp

<div class="flex flex-col gap-3">
    @foreach($games as $game)
        @php
            $releaseDate = $game->pivot->release_date
                ? \Carbon\Carbon::parse($game->pivot->release_date)
                : $game->first_release_date;

            $displayPlatforms = $game->pivot->platforms ?? null;

            $validPlatformIds = $platformEnums->keys()->toArray();

            if ($displayPlatforms) {
                $decodedPlatformIds = is_string($displayPlatforms) ? json_decode($displayPlatforms, true) : $displayPlatforms;
                $filteredPlatformIds = array_filter($decodedPlatformIds ?? [], fn($id) => in_array($id, $validPlatformIds));
                $sortedPlatforms = collect($filteredPlatformIds)->map(function($igdbId) use ($game, $platformEnums) {
                    $gamePlatform = $game->platforms?->first(fn($p) => $p->igdb_id === $igdbId);
                    if ($gamePlatform) {
                        return $gamePlatform;
                    }
                    $enum = $platformEnums[$igdbId] ?? null;
                    if ($enum) {
                        return (object) ['igdb_id' => $igdbId, 'name' => $enum->label()];
                    }
                    return null;
                })->filter()->sortBy(fn($p) => \App\Enums\PlatformEnum::getPriority($p->igdb_id))->values();
            } else {
                $sortedPlatforms = $game->platforms
                    ? $game->platforms->filter(fn($p) => in_array($p->igdb_id, $validPlatformIds))
                        ->sortBy(fn($p) => \App\Enums\PlatformEnum::getPriority($p->igdb_id))->values()
                    : collect();
            }

            $coverUrl = $game->cover_image_id
                ? $game->getCoverUrl('cover_big')
                : ($game->steam_data['header_image'] ?? null);
            $linkUrl = $game->slug
                ? route('game.show', $game)
                : route('game.show.igdb', $game->igdb_id);
        @endphp

        <div class="flex gap-3 bg-gray-800 rounded-xl overflow-hidden shadow-lg">
            {{-- Cover Image --}}
            <a href="{{ $linkUrl }}" class="flex-shrink-0 w-28">
                <div class="aspect-[3/4] bg-gray-700 overflow-hidden">
                    @if($coverUrl)
                        <img src="{{ $coverUrl }}"
                             alt="{{ $game->name }} cover"
                             class="w-full h-full object-cover"
                             loading="lazy">
                    @else
                        <x-game-cover-placeholder :gameName="$game->name" class="w-full h-full" />
                    @endif
                </div>
            </a>

            {{-- Info --}}
            <div class="flex-1 min-w-0 py-3 pr-3 flex flex-col justify-between">
                {{-- Title --}}
                <a href="{{ $linkUrl }}">
                    <h3 class="font-bold text-white text-sm leading-tight line-clamp-2 hover:text-orange-400 transition-colors">
                        {{ $game->name }}
                    </h3>
                </a>

                {{-- Platforms + Release Date --}}
                <div class="mt-2">
                    @if($sortedPlatforms->count() > 0)
                        <div class="flex flex-wrap gap-1 mb-1.5">
                            @foreach($sortedPlatforms as $platform)
                                @php
                                    $enum = $platformEnums[$platform->igdb_id] ?? null;
                                @endphp
                                <span class="px-1.5 py-0.5 text-xs font-bold text-white rounded shadow bg-{{ $enum?->color() ?? 'gray' }}-600">
                                    {{ $enum?->label() ?? \Illuminate\Support\Str::limit($platform->name, 6) }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                    <p class="text-xs font-medium text-cyan-400">
                        {{ $releaseDate?->format('M j, Y') ?? 'TBA' }}
                    </p>
                </div>

                {{-- Actions: Backlog + Wishlist only --}}
                <div class="mt-2">
                    @auth
                        <div class="flex items-center gap-3"
                             x-data="gameCollectionActions({{ $game->id }}, '{{ $game->uuid }}')"
                             @click.stop>
                            {{-- Backlog --}}
                            <button @click.stop.prevent="quickAction('backlog')"
                                    :disabled="actionLoading"
                                    class="w-11 h-11 rounded-full transition-all duration-200 flex items-center justify-center disabled:opacity-50"
                                    :class="currentStatus === 'backlog' ? 'bg-yellow-500/30 ring-2 ring-yellow-400 text-yellow-400' : 'bg-white/10 hover:bg-white/20 text-white hover:scale-110'"
                                    title="Backlog">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </button>

                            {{-- Wishlist --}}
                            <button @click.stop.prevent="quickAction('wishlist')"
                                    :disabled="actionLoading"
                                    class="w-11 h-11 rounded-full transition-all duration-200 flex items-center justify-center disabled:opacity-50"
                                    :class="isWishlisted ? 'bg-red-500/30 ring-2 ring-red-400 text-red-400' : 'bg-white/10 hover:bg-white/20 text-white hover:scale-110'"
                                    title="Wishlist">
                                <svg class="w-5 h-5" :fill="isWishlisted ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                                </svg>
                            </button>
                        </div>
                    @endauth
                    @guest
                        <div class="flex items-center gap-3" x-data="{}" @click.stop>
                            <button @click.stop.prevent="$dispatch('open-modal', 'login-modal')"
                                    class="w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white hover:scale-110 transition-all duration-200 flex items-center justify-center"
                                    title="Login to add to Backlog">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </button>
                            <button @click.stop.prevent="$dispatch('open-modal', 'login-modal')"
                                    class="w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white hover:scale-110 transition-all duration-200 flex items-center justify-center"
                                    title="Login to add to Wishlist">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                                </svg>
                            </button>
                        </div>
                    @endguest
                </div>
            </div>
        </div>
    @endforeach
</div>
