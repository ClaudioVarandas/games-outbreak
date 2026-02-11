@props(['game'])

@auth
    @php
        $componentId = 'game-collection-' . $game->id . '-' . uniqid();
    @endphp

    <div class="absolute inset-0 flex flex-col items-center justify-center z-20 opacity-0 group-hover/card:opacity-100 pointer-events-none group-hover/card:pointer-events-auto transition-opacity duration-300"
         id="{{ $componentId }}"
         x-data="gameCollectionActions({{ $game->id }}, '{{ $game->uuid }}')"
         @click.stop>
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/15 backdrop-blur-sm rounded-xl"></div>

        <!-- Game Title -->
        <div class="relative mb-3 px-4">
            <h3 class="text-white font-semibold text-base text-center line-clamp-2 drop-shadow-lg">
                {{ $game->name }}
            </h3>
        </div>

        <!-- Status Icons (when not in collection OR quick add) -->
        <div x-show="!showPopover" class="relative flex items-center gap-3">
            <!-- Playing -->
            <button @click.stop.prevent="quickAction('playing')"
                    :disabled="actionLoading"
                    class="group/btn w-11 h-11 rounded-full transition-all duration-200 flex items-center justify-center disabled:opacity-50"
                    :class="currentStatus === 'playing' ? 'bg-green-500/30 ring-2 ring-green-400 text-green-400' : 'bg-white/10 hover:bg-white/20 text-white hover:scale-110'"
                    title="Playing">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.491 48.491 0 01-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 01-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 00-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 01-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 00.657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 01-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 005.427-.63 48.05 48.05 0 00.582-4.717.532.532 0 00-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.96.401v0a.656.656 0 00.658-.663 48.422 48.422 0 00-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 01-.61-.58v0z" />
                </svg>
            </button>

            <!-- Played (Trophy) -->
            <button @click.stop.prevent="quickAction('played')"
                    :disabled="actionLoading"
                    class="group/btn w-11 h-11 rounded-full transition-all duration-200 flex items-center justify-center disabled:opacity-50"
                    :class="currentStatus === 'played' ? 'bg-blue-500/30 ring-2 ring-blue-400 text-blue-400' : 'bg-white/10 hover:bg-white/20 text-white hover:scale-110'"
                    title="Played">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0016.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.023 6.023 0 01-2.77.896m5.25-5.624V2.721" />
                </svg>
            </button>

            <!-- Backlog (Clock) -->
            <button @click.stop.prevent="quickAction('backlog')"
                    :disabled="actionLoading"
                    class="group/btn w-11 h-11 rounded-full transition-all duration-200 flex items-center justify-center disabled:opacity-50"
                    :class="currentStatus === 'backlog' ? 'bg-yellow-500/30 ring-2 ring-yellow-400 text-yellow-400' : 'bg-white/10 hover:bg-white/20 text-white hover:scale-110'"
                    title="Backlog">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </button>

            <!-- Wishlist (Heart) -->
            <button @click.stop.prevent="quickAction('wishlist')"
                    :disabled="actionLoading"
                    class="group/btn w-11 h-11 rounded-full transition-all duration-200 flex items-center justify-center disabled:opacity-50"
                    :class="isWishlisted ? 'bg-red-500/30 ring-2 ring-red-400 text-red-400' : 'bg-white/10 hover:bg-white/20 text-white hover:scale-110'"
                    title="Wishlist">
                <svg class="w-5 h-5" :fill="isWishlisted ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                </svg>
            </button>
        </div>

        <!-- Loading spinner -->
        <div x-show="actionLoading" class="relative">
            <svg class="w-6 h-6 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
    </div>
@endauth

@guest
    <div class="absolute inset-0 flex flex-col items-center justify-center z-20 opacity-0 group-hover/card:opacity-100 pointer-events-none group-hover/card:pointer-events-auto transition-opacity duration-300"
         x-data="{}" @click.stop>
        <div class="absolute inset-0 bg-black/15 backdrop-blur-sm rounded-xl"></div>
        <div class="relative mb-3 px-4">
            <h3 class="text-white font-semibold text-base text-center line-clamp-2 drop-shadow-lg">{{ $game->name }}</h3>
        </div>
        <div class="relative flex items-center gap-3">
            @foreach(['Playing' => 'M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.491 48.491 0 01-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 01-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 00-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 01-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 00.657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 01-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 005.427-.63 48.05 48.05 0 00.582-4.717.532.532 0 00-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.96.401v0a.656.656 0 00.658-.663 48.422 48.422 0 00-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 01-.61-.58v0z',
                      'Played' => 'M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0016.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.023 6.023 0 01-2.77.896m5.25-5.624V2.721',
                      'Backlog' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
                      'Wishlist' => 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z'] as $label => $path)
                <button @click.stop.prevent="$dispatch('open-modal', 'login-modal')"
                        class="group/btn relative w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white hover:scale-110 transition-all duration-200 flex items-center justify-center border border-white/20"
                        title="Login to add to {{ $label }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/>
                    </svg>
                </button>
            @endforeach
        </div>
    </div>
@endguest