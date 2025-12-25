@extends('layouts.app')

@section('title', 'Edit List')

@section('content')
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <h1 class="text-4xl font-bold mb-10 text-gray-800 dark:text-gray-100">
            Edit List
        </h1>

        <form action="{{ route('lists.update', $gameList) }}" method="POST" class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            @csrf
            @method('PATCH')

            <div class="mb-6">
                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    List Name <span class="text-red-500">*</span>
                </label>
                <input type="text" 
                       name="name" 
                       id="name" 
                       value="{{ old('name', $gameList->name) }}"
                       required
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Description
                </label>
                <textarea name="description" 
                          id="description" 
                          rows="4"
                          class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent dark:bg-gray-700 dark:text-white">{{ old('description', $gameList->description) }}</textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label class="flex items-center">
                    <input type="checkbox" 
                           name="is_public" 
                           value="1"
                           {{ old('is_public', $gameList->is_public) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Make this list public</span>
                </label>
            </div>

            @if($canCreateSystem)
                <div class="mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Admin Options</h3>
                    
                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" 
                                   name="is_system" 
                                   value="1"
                                   {{ old('is_system', $gameList->is_system) ? 'checked' : '' }}
                                   id="is_system_checkbox"
                                   class="rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                            <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">System List</span>
                        </label>
                        <p class="text-xs text-gray-600 dark:text-gray-400 ml-6 mt-1">System lists are featured lists that can be accessed via a public URL.</p>
                    </div>
                    
                    <div id="system_fields" class="{{ old('is_system', $gameList->is_system) ? '' : 'hidden' }}">
                        <div class="mb-4">
                            <label for="slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                URL Slug
                            </label>
                            <input type="text" 
                                   name="slug" 
                                   id="slug" 
                                   value="{{ old('slug', $gameList->slug) }}"
                                   pattern="[a-z0-9-]+"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            <p class="mt-1 text-xs text-gray-500">Only lowercase letters, numbers, and hyphens. Auto-generated if left empty.</p>
                            @error('slug')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       name="is_active" 
                                       value="1"
                                       {{ old('is_active', $gameList->is_active) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="start_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Start Date (optional)
                        </label>
                        <input type="date" 
                               name="start_at" 
                               id="start_at" 
                               value="{{ old('start_at', $gameList->start_at?->format('Y-m-d')) }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                        <p class="mt-1 text-xs text-gray-500">List will be active starting from this date.</p>
                        @error('start_at')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="mb-4">
                        <label for="end_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            End Date (optional)
                        </label>
                        <input type="date" 
                               name="end_at" 
                               id="end_at" 
                               value="{{ old('end_at', $gameList->end_at?->format('Y-m-d')) }}"
                               min="{{ $gameList->start_at ? $gameList->start_at->format('Y-m-d') : date('Y-m-d', strtotime('+1 day')) }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                        <p class="mt-1 text-xs text-gray-500">List will be automatically deactivated after this date.</p>
                        @error('end_at')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endif

            <div class="flex gap-4">
                <button type="submit" class="flex-1 bg-teal-600 hover:bg-teal-700 text-white px-6 py-3 rounded-lg transition">
                    Update List
                </button>
                <a href="{{ route('lists.show', $gameList) }}" class="flex-1 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 px-6 py-3 rounded-lg text-center transition">
                    Cancel
                </a>
            </div>
        </form>

        <!-- Games in List Section -->
        <div class="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-bold mb-4 text-gray-800 dark:text-gray-100">
                Games in List ({{ $gameList->games->count() }})
            </h2>

            @if($gameList->games->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    @foreach($gameList->games as $game)
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg shadow overflow-hidden">
                            <!-- Cover Image -->
                            <div class="relative aspect-[3/4] bg-gray-200 dark:bg-gray-600">
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

                                <!-- Remove Button -->
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
                            </div>

                            <!-- Game Info -->
                            <div class="p-4">
                                <h3 class="font-semibold text-sm text-gray-900 dark:text-white line-clamp-2 mb-1">
                                    {{ $game->name }}
                                </h3>
                                @if($game->first_release_date)
                                    <p class="text-xs text-gray-600 dark:text-gray-400">
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
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <p class="text-lg text-gray-600 dark:text-gray-400">
                        This list is empty. Add games from the list view.
                    </p>
                    <a href="{{ route('lists.show', $gameList) }}" class="mt-4 inline-block bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-lg transition">
                        Go to List
                    </a>
                </div>
            @endif
        </div>
    </div>

    @if($canCreateSystem)
        <script>
            document.getElementById('is_system_checkbox').addEventListener('change', function() {
                document.getElementById('system_fields').classList.toggle('hidden', !this.checked);
            });
        </script>
    @endif
@endsection

