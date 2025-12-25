@php
    $listRoute = isset($isSystem) && $isSystem ? route('system-list.show', $list->slug) : route('lists.show', $list);
    $games = $list->games->take(4); // Show up to 4 covers
@endphp

<div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-visible hover:shadow-xl transition-shadow duration-300">
    <div class="p-6 flex flex-col h-full">
        <!-- Header: Title | Expiration Badge | Active/Inactive Icon | Privacy Icon -->
        <div class="flex items-start justify-between gap-2 mb-4">
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-1">
                    <h3 class="font-semibold text-lg text-gray-900 dark:text-white">
                        <a href="{{ $listRoute }}" class="hover:text-orange-600">
                            {{ $list->name }}
                        </a>
                    </h3>
                    @if($list->isBacklog())
                        <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded text-xs font-medium flex items-center gap-1">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                            </svg>
                            Backlog
                        </span>
                    @elseif($list->isWishlist())
                        <span class="px-2 py-0.5 bg-pink-100 dark:bg-pink-900/30 text-pink-800 dark:text-pink-300 rounded text-xs font-medium flex items-center gap-1">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/>
                            </svg>
                            Wishlist
                        </span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                @if($list->end_at)
                    <span class="px-2 py-1 bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-300 rounded text-xs font-medium">
                        Expires {{ $list->end_at->format('d/m/Y') }}
                    </span>
                @endif
                @if($list->is_system)
                    @if($list->is_active)
                        <div class="relative group">
                            <svg class="w-5 h-5 text-green-600 dark:text-green-400 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 text-xs font-medium text-white bg-gray-900 dark:bg-gray-800 rounded-lg shadow-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-all duration-200 pointer-events-none z-50 transform group-hover:translate-y-0 translate-y-1">
                                Active
                                <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1">
                                    <div class="border-4 border-transparent border-t-gray-900 dark:border-t-gray-800"></div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="relative group">
                            <svg class="w-5 h-5 text-red-600 dark:text-red-400 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 text-xs font-medium text-white bg-gray-900 dark:bg-gray-800 rounded-lg shadow-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-all duration-200 pointer-events-none z-50 transform group-hover:translate-y-0 translate-y-1">
                                Inactive
                                <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1">
                                    <div class="border-4 border-transparent border-t-gray-900 dark:border-t-gray-800"></div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
                @if($list->is_public)
                    <div class="relative group">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 text-xs font-medium text-white bg-gray-900 dark:bg-gray-800 rounded-lg shadow-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-all duration-200 pointer-events-none z-50 transform group-hover:translate-y-0 translate-y-1">
                            Public
                            <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1">
                                <div class="border-4 border-transparent border-t-gray-900 dark:border-t-gray-800"></div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="relative group">
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                        </svg>
                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 text-xs font-medium text-white bg-gray-900 dark:bg-gray-800 rounded-lg shadow-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-all duration-200 pointer-events-none z-50 transform group-hover:translate-y-0 translate-y-1">
                            Private
                            <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1">
                                <div class="border-4 border-transparent border-t-gray-900 dark:border-t-gray-800"></div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Games Count -->
        <div class="text-sm text-gray-600 dark:text-gray-400 mb-3">
            {{ $list->games->count() }} {{ Str::plural('game', $list->games->count()) }}
        </div>

        <!-- Game Covers (Overlapping) - Always reserve space -->
        <div class="flex items-center mb-4 min-h-[7rem]" style="margin-left: -12px;">
            @if($games->count() > 0)
                @foreach($games as $index => $game)
                    <div class="relative" style="margin-left: {{ $index > 0 ? '-12px' : '0' }}; z-index: {{ 10 - $index }};">
                        @if($game->cover_image_id)
                            <img src="{{ $game->getCoverUrl('cover_small') }}"
                                 alt="{{ $game->name }} cover"
                                 class="w-20 h-28 object-cover rounded border-2 border-white dark:border-gray-800 shadow-sm">
                        @elseif($game->steam_data['header_image'] ?? null)
                            <img src="{{ $game->steam_data['header_image'] }}"
                                 alt="{{ $game->name }} header"
                                 class="w-20 h-28 object-cover rounded border-2 border-white dark:border-gray-800 shadow-sm">
                        @else
                            <div class="w-20 h-28 bg-gray-300 dark:bg-gray-600 rounded border-2 border-white dark:border-gray-800 shadow-sm flex items-center justify-center">
                                <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        @endif
                    </div>
                @endforeach
                @if($list->games->count() > 4)
                    <div class="relative ml-2 flex items-center justify-center w-20 h-28 bg-gray-200 dark:bg-gray-700 rounded border-2 border-white dark:border-gray-800 shadow-sm">
                        <span class="text-sm font-semibold text-gray-600 dark:text-gray-400">+{{ $list->games->count() - 4 }}</span>
                    </div>
                @endif
            @endif
        </div>

        <!-- Creator -->
        @if($list->user)
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                by {{ $list->user->name }}
            </div>
        @endif

        <!-- Actions (View/Edit/Delete) - Always last row -->
        @if(isset($showActions) && $showActions)
            @if($list->isSpecialList())
                <div class="flex gap-2 mt-auto">
                    <a href="{{ route('lists.show', $list) }}" class="flex-1 bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded text-center text-sm transition">
                        View
                    </a>
                    <a href="{{ route('lists.edit', $list) }}" class="flex-1 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 px-4 py-2 rounded text-center text-sm transition">
                        Edit
                    </a>
                </div>
            @else
                <div class="flex gap-2 mt-auto">
                    <a href="{{ route('lists.show', $list) }}" class="flex-1 bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded text-center text-sm transition">
                        View
                    </a>
                    <a href="{{ route('lists.edit', $list) }}" class="flex-1 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 px-4 py-2 rounded text-center text-sm transition">
                        Edit
                    </a>
                    <form action="{{ route('lists.destroy', $list) }}" method="POST" class="flex-1" onsubmit="return confirm('Are you sure you want to delete this list?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm transition">
                            Delete
                        </button>
                    </form>
                </div>
            @endif
        @endif
    </div>
</div>

