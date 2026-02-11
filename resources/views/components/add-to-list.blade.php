@props(['game'])

@auth
    @php
        $user = auth()->user();

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

    @if($user->isAdmin() && $systemListsByType->isNotEmpty())
        <div class="bg-gray-800 p-6 rounded-xl" x-data="{
            open: false,
            selectedSystemListId: null,
            systemListLoading: false,
            notification: { show: false, message: '', type: 'success' },
            showNotification(message, type = 'success') {
                this.notification = { show: true, message, type };
                setTimeout(() => { this.notification.show = false; }, 3000);
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
                <h3 class="text-xl font-bold">System Lists</h3>
                <button @click="open = !open"
                        class="text-purple-400 hover:text-purple-300 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                </button>
            </div>

            <div x-show="open" x-transition class="space-y-4">
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
        </div>
    @endif
@endauth