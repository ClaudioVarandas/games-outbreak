@props(['games', 'list', 'viewMode' => 'grid'])

@php
    $isHighlights = $list->list_type === \App\Enums\ListTypeEnum::HIGHLIGHTS;
    $canHighlight = $list->canHaveHighlights();
    $canIndie = $list->canMarkAsIndie();
    $platformGroups = \App\Enums\PlatformGroupEnum::orderedCases();
@endphp

{{-- Vue mount point for game edit modals --}}
@php
    $activePlatforms = \App\Enums\PlatformEnum::getActivePlatforms()->map(fn($enum) => ['id' => $enum->value, 'label' => $enum->label(), 'color' => $enum->color()])->values();
    $systemGenres = \App\Models\Genre::visible()->where('is_pending_review', false)->ordered()->get(['id', 'name', 'slug']);
@endphp
<div id="game-edit-modals"
     data-list-type="{{ $list->list_type->toSlug() }}"
     data-list-slug="{{ $list->slug }}"
     data-list-name="{{ $list->name }}"
     data-toggle-highlight-url="{{ route('admin.system-lists.games.toggle-highlight', [$list->list_type->toSlug(), $list->slug, '__GAME_ID__']) }}"
     data-toggle-indie-url="{{ route('admin.system-lists.games.toggle-indie', [$list->list_type->toSlug(), $list->slug, '__GAME_ID__']) }}"
     data-get-genres-url="{{ route('admin.system-lists.games.genres', [$list->list_type->toSlug(), $list->slug, '__GAME_ID__']) }}"
     data-update-pivot-url="{{ route('admin.system-lists.games.update-pivot', [$list->list_type->toSlug(), $list->slug, '__GAME_ID__']) }}"
     data-csrf-token="{{ csrf_token() }}"
     data-platforms="{{ $activePlatforms->toJson() }}"
     data-system-genres="{{ $systemGenres->toJson() }}">
</div>

<div class="mt-6" x-data="gameGrid()"
    @if($viewMode === 'grid')
        <!-- Grid View -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4" id="game-grid">
            @foreach($games as $game)
                <div class="relative group bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition"
                     data-game-id="{{ $game->id }}"
                     data-is-highlight="{{ $game->pivot->is_highlight ? 'true' : 'false' }}"
                     data-is-indie="{{ $game->pivot->is_indie ? 'true' : 'false' }}"
                     data-primary-genre-id="{{ $game->pivot->primary_genre_id ?? '' }}">
                    <!-- Action Buttons Row -->
                    <div class="absolute top-2 left-2 right-2 z-10 flex items-center justify-between">
                        <!-- Left: Drag Handle -->
                        <div class="cursor-move drag-handle bg-gray-900/70 backdrop-blur-sm rounded-lg p-1.5">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-8a2 2 0 1 0-.001-4.001A2 2 0 0 0 13 6zm0 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"></path>
                            </svg>
                        </div>

                        <!-- Right: Action Buttons -->
                        <div class="flex items-center gap-1.5">
                            <!-- Edit Pivot Data Button -->
                            <button
                                @click="dispatchEditModal({{ $game->id }}, 'edit', 'false', '{{ addslashes($game->name) }}', '{{ $game->getCoverUrl('cover_big') }}', '{{ $game->pivot->release_date ? \Carbon\Carbon::parse($game->pivot->release_date)->format('M d, Y') : ($game->first_release_date?->format('M d, Y') ?? 'TBA') }}')"
                                class="p-1.5 rounded-lg shadow-lg transition bg-blue-500 hover:bg-blue-600"
                                title="Edit game data"
                            >
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </button>

                            @if($canHighlight)
                                <!-- Highlight Toggle Button -->
                                <button
                                    @click="dispatchEditModal({{ $game->id }}, 'highlight', '{{ $game->pivot->is_highlight ? 'true' : 'false' }}', '{{ addslashes($game->name) }}', '{{ $game->getCoverUrl('cover_big') }}', '{{ $game->pivot->release_date ? \Carbon\Carbon::parse($game->pivot->release_date)->format('M d, Y') : ($game->first_release_date?->format('M d, Y') ?? 'TBA') }}')"
                                    class="p-1.5 rounded-lg shadow-lg transition {{ $game->pivot->is_highlight ? 'bg-yellow-500 hover:bg-yellow-600' : 'bg-gray-700/70 hover:bg-yellow-500' }}"
                                    title="{{ $game->pivot->is_highlight ? 'Remove from highlights' : 'Add to highlights' }}"
                                >
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                    </svg>
                                </button>
                            @endif

                            @if($canIndie)
                                <!-- Indie Toggle Button (Plus Circle icon) -->
                                <button
                                    @click="dispatchEditModal({{ $game->id }}, 'indie', '{{ $game->pivot->is_indie ? 'true' : 'false' }}', '{{ addslashes($game->name) }}', '{{ $game->getCoverUrl('cover_big') }}', '{{ $game->pivot->release_date ? \Carbon\Carbon::parse($game->pivot->release_date)->format('M d, Y') : ($game->first_release_date?->format('M d, Y') ?? 'TBA') }}')"
                                    class="p-1.5 rounded-lg shadow-lg transition {{ $game->pivot->is_indie ? 'bg-purple-500 hover:bg-purple-600' : 'bg-gray-700/70 hover:bg-purple-500' }}"
                                    title="{{ $game->pivot->is_indie ? 'Remove indie status' : 'Mark as indie' }}"
                                >
                                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd"></path>
                                    </svg>
                                </button>
                            @endif

                            <!-- Remove Button -->
                            <button
                                @click="removeGame({{ $game->id }})"
                                class="p-1.5 rounded-lg shadow-lg transition bg-red-600 hover:bg-red-700"
                                title="Remove from list"
                            >
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    @if($isHighlights && $game->pivot->platform_group)
                        @php
                            $pgEnum = \App\Enums\PlatformGroupEnum::tryFrom($game->pivot->platform_group);
                        @endphp
                        <div class="absolute bottom-16 left-2 z-10">
                            <span class="px-2 py-0.5 text-xs font-medium text-white rounded {{ $pgEnum?->colorClass() ?? 'bg-gray-600' }}">
                                {{ $pgEnum?->label() ?? $game->pivot->platform_group }}
                            </span>
                        </div>
                    @endif

                    <!-- Game Card -->
                    <a href="{{ $game->slug ? route('game.show', $game) : route('game.show.igdb', $game->igdb_id) }}" class="block">
                        <div class="aspect-[3/4] bg-gray-200 dark:bg-gray-700">
                            @if($game->getCoverUrl('cover_big'))
                                <img src="{{ $game->getCoverUrl('cover_big') }}"
                                     alt="{{ $game->name }}"
                                     class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-400">
                                    <span class="text-sm text-center px-2">{{ $game->name }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="p-3">
                            <h4 class="font-bold text-sm text-gray-900 dark:text-white truncate">{{ $game->name }}</h4>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                {{ $game->pivot->release_date ? \Carbon\Carbon::parse($game->pivot->release_date)->format('d/m/Y') : ($game->first_release_date?->format('d/m/Y') ?? 'TBA') }}
                            </p>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @else
        <!-- List View -->
        <div class="space-y-2" id="game-list">
            @foreach($games as $game)
                <div class="flex items-center gap-4 bg-white dark:bg-gray-800 rounded-lg shadow p-4 hover:shadow-lg transition"
                     data-game-id="{{ $game->id }}"
                     data-is-highlight="{{ $game->pivot->is_highlight ? 'true' : 'false' }}"
                     data-is-indie="{{ $game->pivot->is_indie ? 'true' : 'false' }}"
                     data-primary-genre-id="{{ $game->pivot->primary_genre_id ?? '' }}">
                    <!-- Drag Handle -->
                    <div class="cursor-move drag-handle text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                        </svg>
                    </div>

                    <!-- Game Info -->
                    <a href="{{ $game->slug ? route('game.show', $game) : route('game.show.igdb', $game->igdb_id) }}" class="flex items-center gap-4 flex-1">
                        <div class="w-16 h-20 bg-gray-200 dark:bg-gray-700 rounded overflow-hidden flex-shrink-0">
                            @if($game->getCoverUrl('cover_small'))
                                <img src="{{ $game->getCoverUrl('cover_small') }}"
                                     alt="{{ $game->name }}"
                                     class="w-full h-full object-cover">
                            @endif
                        </div>
                        <div class="flex-1">
                            <h4 class="font-bold text-gray-900 dark:text-white">{{ $game->name }}</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $game->pivot->release_date ? \Carbon\Carbon::parse($game->pivot->release_date)->format('d/m/Y') : ($game->first_release_date?->format('d/m/Y') ?? 'TBA') }}
                            </p>
                        </div>
                    </a>

                    @if($isHighlights)
                        <!-- Platform Group Selector -->
                        <select
                            @change="updatePlatformGroup({{ $game->id }}, $event.target.value)"
                            class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                        >
                            @foreach($platformGroups as $pg)
                                <option value="{{ $pg->value }}" {{ $game->pivot->platform_group === $pg->value ? 'selected' : '' }}>
                                    {{ $pg->label() }}
                                </option>
                            @endforeach
                        </select>
                    @endif

                    <!-- Edit Pivot Data Button -->
                    <button
                        @click="dispatchEditModal({{ $game->id }}, 'edit', 'false', '{{ addslashes($game->name) }}', '{{ $game->getCoverUrl('cover_small') }}', '{{ $game->pivot->release_date ? \Carbon\Carbon::parse($game->pivot->release_date)->format('M d, Y') : ($game->first_release_date?->format('M d, Y') ?? 'TBA') }}')"
                        class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition"
                    >
                        Edit
                    </button>

                    @if($canHighlight)
                        <!-- Highlight Toggle Button -->
                        <button
                            @click="dispatchEditModal({{ $game->id }}, 'highlight', '{{ $game->pivot->is_highlight ? 'true' : 'false' }}', '{{ addslashes($game->name) }}', '{{ $game->getCoverUrl('cover_small') }}', '{{ $game->pivot->release_date ? \Carbon\Carbon::parse($game->pivot->release_date)->format('M d, Y') : ($game->first_release_date?->format('M d, Y') ?? 'TBA') }}')"
                            class="px-4 py-2 rounded-lg transition {{ $game->pivot->is_highlight ? 'bg-yellow-500 hover:bg-yellow-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700 dark:bg-gray-600 dark:hover:bg-gray-500 dark:text-gray-200' }}"
                        >
                            {{ $game->pivot->is_highlight ? 'Highlighted' : 'Highlight' }}
                        </button>
                    @endif

                    @if($canIndie)
                        <!-- Indie Toggle Button -->
                        <button
                            @click="dispatchEditModal({{ $game->id }}, 'indie', '{{ $game->pivot->is_indie ? 'true' : 'false' }}', '{{ addslashes($game->name) }}', '{{ $game->getCoverUrl('cover_small') }}', '{{ $game->pivot->release_date ? \Carbon\Carbon::parse($game->pivot->release_date)->format('M d, Y') : ($game->first_release_date?->format('M d, Y') ?? 'TBA') }}')"
                            class="px-4 py-2 rounded-lg transition {{ $game->pivot->is_indie ? 'bg-purple-500 hover:bg-purple-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700 dark:bg-gray-600 dark:hover:bg-gray-500 dark:text-gray-200' }}"
                        >
                            {{ $game->pivot->is_indie ? 'Indie' : 'Mark Indie' }}
                        </button>
                    @endif

                    <!-- Remove Button -->
                    <button
                        @click="removeGame({{ $game->id }})"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition"
                    >
                        Remove
                    </button>
                </div>
            @endforeach
        </div>
    @endif

</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
    function gameGrid() {
        return {
            init() {
                const container = document.querySelector('#game-grid') || document.querySelector('#game-list');
                if (container) {
                    Sortable.create(container, {
                        handle: '.drag-handle',
                        animation: 150,
                        onEnd: (evt) => this.reorderGames()
                    });
                }
            },

            dispatchEditModal(gameId, mode, isActive, gameName, gameCover, gameRelease) {
                window.dispatchEvent(new CustomEvent('open-game-edit-modal', {
                    detail: {
                        gameId,
                        mode,
                        isActive,
                        gameName,
                        gameCover,
                        gameRelease
                    }
                }));
            },

            async removeGame(gameId) {
                if (!confirm('Remove this game from the list?')) {
                    return;
                }

                try {
                    const response = await fetch('{{ route("admin.system-lists.games.remove", [$list->list_type->toSlug(), $list->slug, "__GAME_ID__"]) }}'.replace('__GAME_ID__', gameId), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: new FormData((() => {
                            const form = document.createElement('form');
                            const methodInput = document.createElement('input');
                            methodInput.name = '_method';
                            methodInput.value = 'DELETE';
                            form.appendChild(methodInput);
                            return form;
                        })())
                    });

                    const data = await response.json();
                    if (data.success) {
                        window.location.reload();
                    }
                } catch (error) {
                    console.error('Remove game error:', error);
                    alert('Failed to remove game. Please try again.');
                }
            },

            async reorderGames() {
                const container = document.querySelector('#game-grid') || document.querySelector('#game-list');
                const gameIds = Array.from(container.querySelectorAll('[data-game-id]'))
                    .map(el => parseInt(el.dataset.gameId));

                try {
                    const response = await fetch('{{ route("admin.system-lists.games.reorder", [$list->list_type->toSlug(), $list->slug]) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-HTTP-Method-Override': 'PATCH',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            game_ids: gameIds,
                            _method: 'PATCH'
                        })
                    });

                    const data = await response.json();
                    if (!data.success) {
                        console.error('Reorder failed');
                    }
                } catch (error) {
                    console.error('Reorder error:', error);
                }
            },

            @if($isHighlights)
            async updatePlatformGroup(gameId, platformGroup) {
                try {
                    const response = await fetch('{{ route("admin.system-lists.games.platform-group", [$list->list_type->toSlug(), $list->slug, "__GAME_ID__"]) }}'.replace('__GAME_ID__', gameId), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-HTTP-Method-Override': 'PATCH',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            platform_group: platformGroup,
                            _method: 'PATCH'
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        console.log('Platform group updated');
                    } else {
                        alert(data.error || 'Failed to update platform group');
                    }
                } catch (error) {
                    console.error('Update platform group error:', error);
                    alert('Failed to update platform group. Please try again.');
                }
            },
            @endif
        }
    }
</script>