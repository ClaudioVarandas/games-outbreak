@props([
    'games',
    'platformEnums',
])

<section class="mb-12"
         x-data="{
             viewMode: localStorage.getItem('latest_added_view_mode') || 'list',
             setViewMode(mode) {
                 this.viewMode = mode;
                 localStorage.setItem('latest_added_view_mode', mode);
             }
         }">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl md:text-3xl font-bold flex items-center text-gray-800 dark:text-gray-100">
            <svg class="w-6 h-6 md:w-8 md:h-8 mr-2 md:mr-3 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z"
                      clip-rule="evenodd"/>
            </svg>
            Latest Added Games
        </h2>

        <div class="flex items-center gap-1 bg-gray-200 dark:bg-gray-700 rounded-lg p-1">
            <button @click="setViewMode('grid')"
                    :class="viewMode === 'grid' ? 'bg-orange-500 text-white' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-white'"
                    class="p-2 rounded transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                </svg>
            </button>
            <button @click="setViewMode('list')"
                    :class="viewMode === 'list' ? 'bg-orange-500 text-white' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-white'"
                    class="p-2 rounded transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                </svg>
            </button>
        </div>
    </div>

    @if($games->count() > 0)
        {{-- Grid View --}}
        <div x-show="viewMode === 'grid'" x-cloak class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-6">
            @foreach($games as $game)
                <x-game-card
                    :game="$game"
                    variant="default"
                    layout="overlay"
                    aspectRatio="3/4"
                    :platformEnums="$platformEnums" />
            @endforeach
        </div>

        {{-- Table View --}}
        <div x-show="viewMode === 'list'" class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="w-full text-left">
                <thead class="bg-gray-100 dark:bg-gray-800 text-xs uppercase text-gray-500 dark:text-gray-400 tracking-wider">
                    <tr>
                        <th class="px-4 py-3">Game</th>
                        <th class="px-4 py-3 hidden sm:table-cell">Platforms</th>
                        <th class="px-4 py-3 hidden md:table-cell">Release Date</th>
                        <th class="px-4 py-3 hidden lg:table-cell">Added</th>
                        <th class="px-4 py-3 w-10"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
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
                                ? $game->platforms->filter(fn($p) => in_array($p->igdb_id, $validPlatformIds))
                                    ->sortBy(fn($p) => \App\Enums\PlatformEnum::getPriority($p->igdb_id))
                                    ->values()
                                : collect();
                        @endphp
                        <tr class="bg-white dark:bg-gray-800/50 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            {{-- Game --}}
                            <td class="px-4 py-3">
                                <a href="{{ $linkUrl }}" class="flex items-center gap-3 group">
                                    <div class="w-10 h-14 rounded overflow-hidden bg-gray-200 dark:bg-gray-700 flex-shrink-0">
                                        @if($coverUrl)
                                            <img src="{{ $coverUrl }}" alt="{{ $game->name }}" class="w-full h-full object-cover" loading="lazy">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="font-semibold text-gray-900 dark:text-white truncate group-hover:text-orange-500 dark:group-hover:text-orange-400 transition-colors">
                                            {{ $game->name }}
                                        </h3>
                                        <span class="{{ $game->getGameTypeEnum()->colorClass() }} px-1.5 py-0.5 text-xs font-medium rounded inline-block mt-1">
                                            {{ $game->getGameTypeEnum()->label() }}
                                        </span>
                                    </div>
                                </a>
                            </td>

                            {{-- Platforms --}}
                            <td class="px-4 py-3 hidden sm:table-cell">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($sortedPlatforms->take(4) as $platform)
                                        @php $enum = $platformEnums[$platform->igdb_id] ?? null; @endphp
                                        <span class="px-1.5 py-0.5 text-xs font-bold text-white rounded bg-{{ $enum?->color() ?? 'gray' }}-600">
                                            {{ $enum?->label() ?? \Illuminate\Support\Str::limit($platform->name, 4) }}
                                        </span>
                                    @endforeach
                                    @if($sortedPlatforms->count() > 4)
                                        <span class="px-1.5 py-0.5 text-xs font-medium text-gray-500 dark:text-gray-400">
                                            +{{ $sortedPlatforms->count() - 4 }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Release Date --}}
                            <td class="px-4 py-3 hidden md:table-cell">
                                <span class="text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                    {{ $game->first_release_date?->format('M j, Y') ?? 'TBA' }}
                                </span>
                            </td>

                            {{-- Added --}}
                            <td class="px-4 py-3 hidden lg:table-cell">
                                <span class="text-sm text-gray-500 dark:text-gray-500 whitespace-nowrap">
                                    {{ $game->created_at->diffForHumans() }}
                                </span>
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 py-3">
                                <x-game-collection-actions-mobile :game="$game" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="text-center py-8 bg-white dark:bg-gray-800 rounded-lg">
            <p class="text-gray-600 dark:text-gray-400">
                No games added yet.
            </p>
        </div>
    @endif
</section>
