@props(['game'])

@auth
    @php
        $user = auth()->user();
        $user->ensureSpecialLists();
        $backlogList = $user->gameLists()->backlog()->with('games')->first();
        $wishlistList = $user->gameLists()->wishlist()->with('games')->first();
        $regularLists = $user->gameLists()->userLists()->regular()->with('games')->get();

        $isInBacklog = $backlogList && $backlogList->games->contains('id', $game->id);
        $isInWishlist = $wishlistList && $wishlistList->games->contains('id', $game->id);

        $systemListsByType = collect();
        if ($user->isAdmin()) {
            $systemListsByType = \App\Models\GameList::where('is_system', true)
                ->where('is_active', true)
                ->whereIn('list_type', [
                    \App\Enums\ListTypeEnum::YEARLY->value,
                    \App\Enums\ListTypeEnum::SEASONED->value,
                    \App\Enums\ListTypeEnum::EVENTS->value,
                ])
                ->with('games')
                ->orderBy('name')
                ->get()
                ->groupBy('list_type');
        }
    @endphp

    <div class="bg-gray-800 p-6 rounded-xl" x-data="{
        open: false,
        backlogLoading: false,
        wishlistLoading: false,
        isInBacklog: {{ $isInBacklog ? 'true' : 'false' }},
        isInWishlist: {{ $isInWishlist ? 'true' : 'false' }},
        selectedListId: null,
        addToListLoading: false,
        selectedSystemListId: null,
        systemListLoading: false,
        notification: { show: false, message: '', type: 'success' },
        showNotification(message, type = 'success') {
            this.notification = { show: true, message, type };
            setTimeout(() => { this.notification.show = false; }, 3000);
        },
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
                    formData.append('game_uuid', '{{ $game->uuid }}');
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
                    formData.append('game_uuid', '{{ $game->uuid }}');
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
        },
        async addToCustomList() {
            if (!this.selectedListId || this.addToListLoading) return;
            this.addToListLoading = true;
            try {
                // Parse type:slug format
                const [type, slug] = this.selectedListId.split(':');

                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('game_uuid', '{{ $game->uuid }}');

                const response = await fetch(`/u/{{ auth()->user()->username }}/${slug}/games`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                const data = await response.json();
                if (data.success) {
                    // Reset dropdown
                    this.selectedListId = null;
                    this.showNotification('Game added to list successfully!', 'success');
                } else if (data.info) {
                    this.showNotification(data.info, 'info');
                } else {
                    this.showNotification(data.error || data.message || 'Failed to add game to list', 'error');
                }
            } catch (error) {
                console.error('Error adding to custom list:', error);
                this.showNotification('Failed to add game to list', 'error');
            } finally {
                this.addToListLoading = false;
            }
        },
        async addToSystemList() {
            if (!this.selectedSystemListId || this.systemListLoading) return;
            this.systemListLoading = true;
            try {
                const [type, slug] = this.selectedSystemListId.split(':');

                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('game_id', '{{ $game->igdb_id }}');

                const response = await fetch(`/admin/system-lists/${type}/${slug}/games`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                const data = await response.json();
                if (data.success) {
                    this.selectedSystemListId = null;
                    this.showNotification('Game added to system list successfully!', 'success');
                } else if (data.info) {
                    this.showNotification(data.info, 'info');
                } else {
                    this.showNotification(data.error || data.message || 'Failed to add game to system list', 'error');
                }
            } catch (error) {
                console.error('Error adding to system list:', error);
                this.showNotification('Failed to add game to system list', 'error');
            } finally {
                this.systemListLoading = false;
            }
        }
    }">
        <!-- Notification Toast -->
        <div x-show="notification.show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed bottom-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg max-w-sm"
             :class="{
                 'bg-green-600 text-white': notification.type === 'success',
                 'bg-blue-600 text-white': notification.type === 'info',
                 'bg-red-600 text-white': notification.type === 'error'
             }"
             style="display: none;">
            <p x-text="notification.message"></p>
        </div>
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold">Add to List</h3>
            <button @click="open = !open"
                    class="text-orange-400 hover:text-orange-300 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
            </button>
        </div>

        <div x-show="open"
             x-transition
             class="space-y-6">
            <!-- Special Lists Section -->
            @if($backlogList || $wishlistList)
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-4">Backlog and Wishlist</p>
                    <div class="flex items-center justify-center gap-4">
                        <!-- Backlog Button -->
                        @if($backlogList)
                            <button @click="toggleBacklog()"
                                    :disabled="backlogLoading"
                                    class="group/btn w-14 h-14 rounded-full bg-transparent hover:bg-white/10 text-white hover:scale-110 transition-all duration-200 flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                                    :class="isInBacklog ? 'border-2 border-white/50' : ''"
                                    title="Backlog">
                                <!-- Solid version (when in backlog) -->
                                <svg x-show="!backlogLoading && isInBacklog" class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M5.625 3.75a2.625 2.625 0 1 0 0 5.25h12.75a2.625 2.625 0 0 0 0-5.25H5.625ZM3.75 11.25a.75.75 0 0 0 0 1.5h16.5a.75.75 0 0 0 0-1.5H3.75ZM3 15.75a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75ZM3.75 18.75a.75.75 0 0 0 0 1.5h16.5a.75.75 0 0 0 0-1.5H3.75Z" />
                                </svg>
                                <!-- Outline version (default, when not in backlog) -->
                                <svg x-show="!backlogLoading && !isInBacklog" class="w-7 h-7 group-hover/btn:hidden" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                                </svg>
                                <!-- Solid version (on hover, when not in backlog) -->
                                <svg x-show="!backlogLoading && !isInBacklog" class="w-7 h-7 hidden group-hover/btn:block" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M5.625 3.75a2.625 2.625 0 1 0 0 5.25h12.75a2.625 2.625 0 0 0 0-5.25H5.625ZM3.75 11.25a.75.75 0 0 0 0 1.5h16.5a.75.75 0 0 0 0-1.5H3.75ZM3 15.75a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75ZM3.75 18.75a.75.75 0 0 0 0 1.5h16.5a.75.75 0 0 0 0-1.5H3.75Z" />
                                </svg>
                                <svg x-show="backlogLoading" class="w-7 h-7 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </button>
                        @endif

                        <!-- Wishlist Button -->
                        @if($wishlistList)
                            <button @click="toggleWishlist()"
                                    :disabled="wishlistLoading"
                                    class="group/btn w-14 h-14 rounded-full bg-transparent hover:bg-white/10 text-white hover:scale-110 transition-all duration-200 flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                                    :class="isInWishlist ? 'border-2 border-white/50' : ''"
                                    title="Wishlist">
                                <!-- Solid version (when in wishlist) -->
                                <svg x-show="!wishlistLoading && isInWishlist" class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/>
                                </svg>
                                <!-- Outline version (default, when not in wishlist) -->
                                <svg x-show="!wishlistLoading && !isInWishlist" class="w-7 h-7 group-hover/btn:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                                </svg>
                                <!-- Solid version (on hover, when not in wishlist) -->
                                <svg x-show="!wishlistLoading && !isInWishlist" class="w-7 h-7 hidden group-hover/btn:block" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/>
                                </svg>
                                <svg x-show="wishlistLoading" class="w-7 h-7 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Custom Lists Section -->
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide mb-4">Custom Lists</p>
                <div class="flex items-stretch gap-2">
                    <select x-model="selectedListId"
                            :disabled="addToListLoading || {{ $regularLists->count() === 0 ? 'true' : 'false' }}"
                            class="flex-1 px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed">
                        @if($regularLists->count() > 0)
                            <option value="">Select a list...</option>
                            @foreach($regularLists as $list)
                                @php
                                    $isInList = $list->games->contains('id', $game->id);
                                @endphp
                                <option value="{{ $list->list_type->toSlug() }}:{{ $list->slug }}" {{ $isInList ? 'disabled' : '' }}>
                                    {{ $list->name }}{{ $isInList ? ' (already added)' : '' }}
                                </option>
                            @endforeach
                        @else
                            <option value="">No custom lists available</option>
                        @endif
                    </select>
                    <button @click="addToCustomList()"
                            :disabled="!selectedListId || addToListLoading || {{ $regularLists->count() === 0 ? 'true' : 'false' }}"
                            class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center shrink-0">
                        <svg x-show="!addToListLoading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <svg x-show="addToListLoading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                </div>
                @if($regularLists->count() === 0)
                    <p class="text-xs text-gray-400 mt-2">Create a custom list first to add games to it.</p>
                @endif
            </div>

            <!-- System Lists Section (Admin Only) -->
            @if($user->isAdmin() && $systemListsByType->isNotEmpty())
                <div class="pt-4 border-t border-gray-700">
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-4">System Lists</p>
                    <div class="flex items-stretch gap-2">
                        <select x-model="selectedSystemListId"
                                :disabled="systemListLoading"
                                class="flex-1 min-w-0 px-3 py-2 bg-gray-700 text-white text-sm rounded-lg border border-gray-600 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed">
                            <option value="">Select a system list...</option>
                            @foreach($systemListsByType as $listType => $lists)
                                @php
                                    $typeEnum = \App\Enums\ListTypeEnum::fromValue($listType);
                                    $typeSlug = $typeEnum?->toSlug() ?? $listType;
                                    $typeLabel = $typeEnum?->label() ?? ucfirst($listType);
                                @endphp
                                <optgroup label="{{ $typeLabel }}">
                                    @foreach($lists as $list)
                                        @php
                                            $isInList = $list->games->contains('id', $game->id);
                                        @endphp
                                        <option value="{{ $typeSlug }}:{{ $list->slug }}" {{ $isInList ? 'disabled' : '' }}>
                                            {{ $list->name }}{{ $isInList ? ' (already added)' : '' }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <button @click="addToSystemList()"
                                :disabled="!selectedSystemListId || systemListLoading"
                                class="px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center shrink-0">
                            <svg x-show="!systemListLoading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            <svg x-show="systemListLoading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            @endif

            <!-- Create New List Link -->
            <div class="pt-2 border-t border-gray-700">
                <a href="{{ route('user.lists.lists.create', ['user' => auth()->user()->username]) }}"
                   class="block text-center text-sm text-orange-400 hover:text-orange-300 transition">
                    + Create New List
                </a>
            </div>
        </div>
    </div>
@endauth
