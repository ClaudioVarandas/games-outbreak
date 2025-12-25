@extends('layouts.app')

@section('title', $gameList->name)

@section('content')
    @php
        $currentUser = auth()->user();
        $canEdit = false;
        if ($currentUser) {
            $canEdit = $gameList->canBeEditedBy($currentUser);
            // Debug: Uncomment to see values
            // \Log::info('Edit check', ['user_id' => $currentUser->id, 'is_admin' => $currentUser->is_admin, 'isAdmin()' => $currentUser->isAdmin(), 'list_is_system' => $gameList->is_system, 'canEdit' => $canEdit]);
        }
    @endphp
    <div class="container mx-auto px-4 py-8">
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100 mb-2">
                        {{ $gameList->name }}
                    </h1>
                    @if($gameList->is_system)
                        <span class="inline-block px-3 py-1 bg-orange-600 text-white rounded text-sm font-bold">System List</span>
                    @endif
                    @if($gameList->is_public)
                        <span class="inline-block px-3 py-1 bg-green-600 text-white rounded text-sm ml-2">Public</span>
                    @else
                        <span class="inline-block px-3 py-1 bg-gray-600 text-white rounded text-sm ml-2">Private</span>
                    @endif
                </div>
                @auth
                    @php
                        // Re-check for admin users viewing system lists
                        $authUser = auth()->user();
                        $canEditList = false;
                        if ($authUser) {
                            // Direct check: admins can always edit
                            if ($authUser->isAdmin()) {
                                $canEditList = true;
                            } else {
                                // Non-admins can only edit their own non-system lists
                                if (!$gameList->is_system && $gameList->user_id === $authUser->id) {
                                    $canEditList = true;
                                }
                            }
                        }
                    @endphp
                    @if($canEditList)
                        <div class="flex gap-2">
                            <a href="{{ route('lists.edit', $gameList) }}" class="bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 px-4 py-2 rounded-lg transition">
                                Edit
                            </a>
                            <form action="{{ route('lists.destroy', $gameList) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this list?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition">
                                    Delete
                                </button>
                            </form>
                        </div>
                    @endif
                @endauth
            </div>
            @if($gameList->description)
                <p class="text-lg text-gray-600 dark:text-gray-400 mb-4">
                    {{ $gameList->description }}
                </p>
            @endif
            @if($gameList->user)
                <p class="text-sm text-gray-500 dark:text-gray-500">
                    Created by {{ $gameList->user->name }}
                </p>
            @endif
            @if($gameList->end_at)
                <p class="text-sm text-gray-500 dark:text-gray-500">
                    Expires {{ $gameList->end_at->format('d/m/Y') }}
                </p>
            @endif
        </div>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                {{ session('success') }}
            </div>
        @endif

        <div class="mb-6">
            <p class="text-lg text-gray-700 dark:text-gray-300 mb-4">
                {{ $gameList->games->count() }} {{ Str::plural('game', $gameList->games->count()) }} in this list
            </p>
            @auth
                @if($canEdit)
                    <!-- Add Games Section -->
                    <div class="mb-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                        <h2 class="text-2xl font-bold mb-4 text-gray-800 dark:text-gray-100">Add Games to List</h2>
                        <div data-vue-component="add-game-to-list" data-list-id="{{ $gameList->id }}"></div>
                    </div>
                @endif
            @endauth
        </div>

        @if($gameList->games->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @foreach($gameList->games as $game)
                    <a href="{{ route('game.show', $game) }}" class="block">
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow duration-300">
                            <!-- Cover Image -->
                            <div class="relative aspect-[3/4] bg-gray-200 dark:bg-gray-700">
                                @php
                                    $coverUrl = $game->cover_image_id
                                        ? $game->getCoverUrl('cover_big')
                                        : ($game->steam_data['header_image'] ?? null);
                                @endphp
                                @if($coverUrl)
                                    <img src="{{ $coverUrl }}"
                                         alt="{{ $game->name }} cover"
                                         class="w-full h-full object-cover"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <x-game-cover-placeholder :gameName="$game->name" class="w-full h-full" style="display: none;" />
                                @else
                                    <x-game-cover-placeholder :gameName="$game->name" class="w-full h-full" />
                                @endif

                                <!-- Platform Badges -->
                                @php
                                    $validPlatformIds = $platformEnums->keys()->toArray();
                                    $filteredPlatforms = $game->platforms 
                                        ? $game->platforms->filter(fn($p) => in_array($p->igdb_id, $validPlatformIds))
                                        : collect();
                                    
                                    // Sort platforms using config-based priority: PC first, then consoles, then Linux/macOS
                                    $sortedPlatforms = $filteredPlatforms->sortBy(function($platform) {
                                        return \App\Enums\PlatformEnum::getPriority($platform->igdb_id);
                                    })->values();
                                    
                                    $displayPlatforms = $sortedPlatforms;
                                @endphp
                                @if($displayPlatforms->count() > 0)
                                    <div class="absolute top-2 left-2 flex flex-wrap gap-1">
                                        @foreach($displayPlatforms as $platform)
                                            @php
                                                $enum = $platformEnums[$platform->igdb_id] ?? null;
                                            @endphp
                                            <span class="px-2 py-1 text-xs font-bold text-white rounded
                                        @if($enum)
                                            bg-{{ $enum->color() }}-600
                                        @else
                                            bg-gray-600
                                        @endif">
                                        {{ $enum?->label() ?? Str::limit($platform->name, 8) }}
                                    </span>
                                        @endforeach
                                    </div>
                                @endif

                                @auth
                                    @if($canEdit)
                                        <form action="{{ route('lists.games.remove', ['gameList' => $gameList, 'game' => $game]) }}" 
                                              method="POST" 
                                              class="absolute top-2 right-2"
                                              onsubmit="return confirm('Remove this game from the list?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white p-2 rounded-full shadow-lg">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif
                                @endauth
                            </div>

                            <!-- Game Info -->
                            <div class="p-4">
                                <h3 class="font-semibold text-lg text-gray-900 dark:text-white line-clamp-2">
                                    {{ $game->name }}
                                </h3>

                                @if($game->first_release_date)
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        {{ $game->first_release_date->format('d/m/Y') }}
                                    </p>
                                @endif
                                <div class="mt-1">
                                    <span class="{{ $game->getGameTypeEnum()->colorClass() }} px-2 py-0.5 text-xs font-medium rounded text-white">
                                        {{ $game->getGameTypeEnum()->label() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                <p class="text-xl text-gray-600 dark:text-gray-400 mb-4">
                    This list is empty.
                </p>
                @auth
                    @if($canEdit)
                        <p class="text-gray-500 dark:text-gray-500">
                            Browse games and add them to this list.
                        </p>
                    @endif
                @endauth
            </div>
        @endif
    </div>

@endsection

