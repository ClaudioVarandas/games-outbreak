@props(['game', 'backlogList', 'wishlistList'])

@auth
    @php
        $isInBacklog = $backlogList && $backlogList->games->contains('id', $game->id);
        $isInWishlist = $wishlistList && $wishlistList->games->contains('id', $game->id);
    @endphp

    <div class="absolute inset-0 flex flex-col items-center justify-center z-20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-300"
         x-data="{
             backlogLoading: false,
             wishlistLoading: false,
             isInBacklog: {{ $isInBacklog ? 'true' : 'false' }},
             isInWishlist: {{ $isInWishlist ? 'true' : 'false' }},
             async toggleBacklog() {
                 if (this.backlogLoading) return;
                 this.backlogLoading = true;
                 try {
                     const url = this.isInBacklog
                         ? '{{ route('user.lists.games.remove', [auth()->user()->username, 'backlog', $game]) }}'
                         : '{{ route('user.lists.games.add', [auth()->user()->username, 'backlog']) }}';
                     const method = this.isInBacklog ? 'DELETE' : 'POST';
                     const formData = new FormData();
                     formData.append('_token', '{{ csrf_token() }}');
                     
                     if (method === 'POST') {
                         formData.append('game_id', '{{ $game->id }}');
                     } else {
                         formData.append('_method', 'DELETE');
                     }
                     
                     const response = await fetch(url, {
                         method: 'POST',
                         headers: {
                             'X-Requested-With': 'XMLHttpRequest',
                             'Accept': 'application/json',
                         },
                         body: formData,
                     });
                     
                     const data = await response.json();
                     if (data.success || data.info) {
                         this.isInBacklog = !this.isInBacklog;
                     }
                 } catch (error) {
                     console.error('Error toggling backlog:', error);
                 } finally {
                     this.backlogLoading = false;
                 }
             },
             async toggleWishlist() {
                 if (this.wishlistLoading) return;
                 this.wishlistLoading = true;
                 try {
                     const url = this.isInWishlist
                         ? '{{ route('user.lists.games.remove', [auth()->user()->username, 'wishlist', $game]) }}'
                         : '{{ route('user.lists.games.add', [auth()->user()->username, 'wishlist']) }}';
                     const method = this.isInWishlist ? 'DELETE' : 'POST';
                     const formData = new FormData();
                     formData.append('_token', '{{ csrf_token() }}');
                     
                     if (method === 'POST') {
                         formData.append('game_id', '{{ $game->id }}');
                     } else {
                         formData.append('_method', 'DELETE');
                     }
                     
                     const response = await fetch(url, {
                         method: 'POST',
                         headers: {
                             'X-Requested-With': 'XMLHttpRequest',
                             'Accept': 'application/json',
                         },
                         body: formData,
                     });
                     
                     const data = await response.json();
                     if (data.success || data.info) {
                         this.isInWishlist = !this.isInWishlist;
                     }
                 } catch (error) {
                     console.error('Error toggling wishlist:', error);
                 } finally {
                     this.wishlistLoading = false;
                 }
             }
         }"
         @click.stop>
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/15 backdrop-blur-sm rounded-xl"></div>
        
        <!-- Game Title -->
        <div class="relative mb-4 px-4">
            <h3 class="text-white font-semibold text-lg text-center line-clamp-2 drop-shadow-lg">
                {{ $game->name }}
            </h3>
        </div>
        
        <!-- Action Buttons -->
        <div class="relative flex items-center gap-4">
            <!-- Backlog Button -->
            @if($backlogList)
                <button @click.stop.prevent="toggleBacklog()"
                        :disabled="backlogLoading"
                        class="group/btn w-14 h-14 rounded-full bg-transparent hover:bg-white/10 text-white hover:scale-110 transition-all duration-200 flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                        :class="isInBacklog ? 'border-2 border-white/50' : ''"
                        title="{{ $isInBacklog ? 'Remove from Backlog' : 'Add to Backlog' }}">
                    <!-- Icon container with state-based visibility -->
                    <div class="relative w-7 h-7" :class="{ 'hidden': backlogLoading }">
                        <!-- Solid version (when in backlog) -->
                        <svg x-show="isInBacklog" class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M5.625 3.75a2.625 2.625 0 1 0 0 5.25h12.75a2.625 2.625 0 0 0 0-5.25H5.625ZM3.75 11.25a.75.75 0 0 0 0 1.5h16.5a.75.75 0 0 0 0-1.5H3.75ZM3 15.75a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75ZM3.75 18.75a.75.75 0 0 0 0 1.5h16.5a.75.75 0 0 0 0-1.5H3.75Z" />
                        </svg>
                        <!-- When not in backlog: outline default, solid on hover -->
                        <template x-if="!isInBacklog">
                            <div class="relative w-7 h-7">
                                <!-- Outline version (default) -->
                                <svg class="absolute inset-0 w-7 h-7 group-hover/btn:opacity-0 transition-opacity" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                                </svg>
                                <!-- Solid version (on hover) -->
                                <svg class="absolute inset-0 w-7 h-7 opacity-0 group-hover/btn:opacity-100 transition-opacity" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M5.625 3.75a2.625 2.625 0 1 0 0 5.25h12.75a2.625 2.625 0 0 0 0-5.25H5.625ZM3.75 11.25a.75.75 0 0 0 0 1.5h16.5a.75.75 0 0 0 0-1.5H3.75ZM3 15.75a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75ZM3.75 18.75a.75.75 0 0 0 0 1.5h16.5a.75.75 0 0 0 0-1.5H3.75Z" />
                                </svg>
                            </div>
                        </template>
                    </div>
                    <svg x-show="backlogLoading" class="w-7 h-7 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>
            @endif
            
            <!-- Wishlist Button -->
            @if($wishlistList)
                <button @click.stop.prevent="toggleWishlist()"
                        :disabled="wishlistLoading"
                        class="group/btn w-14 h-14 rounded-full bg-transparent hover:bg-white/10 text-white hover:scale-110 transition-all duration-200 flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                        :class="isInWishlist ? 'border-2 border-white/50' : ''"
                        title="{{ $isInWishlist ? 'Remove from Wishlist' : 'Add to Wishlist' }}">
                    <!-- Icon container with state-based visibility -->
                    <div class="relative w-7 h-7" :class="{ 'hidden': wishlistLoading }">
                        <!-- Solid version (when in wishlist) -->
                        <svg x-show="isInWishlist" class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/>
                        </svg>
                        <!-- When not in wishlist: outline default, solid on hover -->
                        <template x-if="!isInWishlist">
                            <div class="relative w-7 h-7">
                                <!-- Outline version (default) -->
                                <svg class="absolute inset-0 w-7 h-7 group-hover/btn:opacity-0 transition-opacity" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                                </svg>
                                <!-- Solid version (on hover) -->
                                <svg class="absolute inset-0 w-7 h-7 opacity-0 group-hover/btn:opacity-100 transition-opacity" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/>
                                </svg>
                            </div>
                        </template>
                    </div>
                    <svg x-show="wishlistLoading" class="w-7 h-7 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>
            @endif
        </div>
    </div>
@endauth

@guest
    <div class="absolute inset-0 flex flex-col items-center justify-center z-20 opacity-0 group-hover/card:opacity-100 transition-opacity duration-300"
         x-data="{}"
         @click.stop>
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/15 backdrop-blur-sm rounded-xl"></div>
        
        <!-- Game Title -->
        <div class="relative mb-4 px-4">
            <h3 class="text-white font-semibold text-lg text-center line-clamp-2 drop-shadow-lg">
                {{ $game->name }}
            </h3>
        </div>
        
        <!-- Action Buttons for Guests -->
        <div class="relative flex items-center gap-4">
            <!-- Backlog Button -->
            <button @click.stop.prevent="$dispatch('open-modal', 'login-modal')"
                    class="group/btn relative w-14 h-14 rounded-full bg-transparent hover:bg-white/10 text-white opacity-90 hover:opacity-100 hover:scale-110 transition-all duration-200 flex items-center justify-center border border-white/30 hover:border-white/50 hover:shadow-lg hover:shadow-white/20"
                    title="Login to add to Backlog">
                <!-- Icon container with hover switching -->
                <div class="relative w-7 h-7">
                    <!-- Outline version (default) -->
                    <svg class="absolute inset-0 w-7 h-7 group-hover/btn:opacity-0 transition-opacity" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                    </svg>
                    <!-- Solid version (on hover) -->
                    <svg class="absolute inset-0 w-7 h-7 opacity-0 group-hover/btn:opacity-100 transition-opacity" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M5.625 3.75a2.625 2.625 0 1 0 0 5.25h12.75a2.625 2.625 0 0 0 0-5.25H5.625ZM3.75 11.25a.75.75 0 0 0 0 1.5h16.5a.75.75 0 0 0 0-1.5H3.75ZM3 15.75a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75ZM3.75 18.75a.75.75 0 0 0 0 1.5h16.5a.75.75 0 0 0 0-1.5H3.75Z" />
                    </svg>
                </div>
                <!-- User Icon Badge -->
                <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-orange-500 rounded-full flex items-center justify-center shadow-lg">
                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z" />
                    </svg>
                </div>
            </button>
            
            <!-- Wishlist Button -->
            <button @click.stop.prevent="$dispatch('open-modal', 'login-modal')"
                    class="group/btn relative w-14 h-14 rounded-full bg-transparent hover:bg-white/10 text-white opacity-90 hover:opacity-100 hover:scale-110 transition-all duration-200 flex items-center justify-center border border-white/30 hover:border-white/50 hover:shadow-lg hover:shadow-white/20"
                    title="Login to add to Wishlist">
                <!-- Icon container with hover switching -->
                <div class="relative w-7 h-7">
                    <!-- Outline version (default) -->
                    <svg class="absolute inset-0 w-7 h-7 group-hover/btn:opacity-0 transition-opacity" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                    </svg>
                    <!-- Solid version (on hover) -->
                    <svg class="absolute inset-0 w-7 h-7 opacity-0 group-hover/btn:opacity-100 transition-opacity" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/>
                    </svg>
                </div>
                <!-- User Icon Badge -->
                <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-orange-500 rounded-full flex items-center justify-center shadow-lg">
                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z" />
                    </svg>
                </div>
            </button>
        </div>
    </div>
@endguest

