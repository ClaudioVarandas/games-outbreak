@props([
    'allGenres',
    'allGameModes',
    'allGameTypes',
    'platformEnums',
    'activeFilters',
    'startDate',
    'endDate',
    'today',
    'maxDate',
])

@php
    $activeFilterCount = 0;
    if ($activeFilters['start_date'] || $activeFilters['end_date']) $activeFilterCount++;
    if (!empty($activeFilters['genres'])) $activeFilterCount += count($activeFilters['genres']);
    if (!empty($activeFilters['platforms'])) $activeFilterCount += count($activeFilters['platforms']);
    if (!empty($activeFilters['game_modes'])) $activeFilterCount += count($activeFilters['game_modes']);
    if (!empty($activeFilters['game_types'])) $activeFilterCount += count($activeFilters['game_types']);
@endphp

<div x-data="{ open: false }" class="relative">
    <!-- Filter Toggle Button -->
    <button 
        @click="open = !open"
        class="flex items-center gap-2 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition relative">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
        </svg>
        <span>Filters</span>
        @if($activeFilterCount > 0)
            <span class="absolute -top-1 -right-1 bg-orange-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
                {{ $activeFilterCount }}
            </span>
        @endif
    </button>

    <!-- Overlay -->
    <div 
        x-show="open"
        x-transition:enter="transition-opacity ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="open = false"
        class="fixed inset-0 bg-black/50 z-40"
        style="display: none;">
    </div>

    <!-- Drawer -->
    <div 
        x-show="open"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        @click.away="open = false"
        class="fixed right-0 top-0 bottom-0 w-full max-w-md bg-gray-800 text-white shadow-2xl z-50 overflow-y-auto"
        style="display: none;">
        
        <div class="p-6">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold">Filters</h2>
                <button @click="open = false" class="text-gray-400 hover:text-white transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Filter Form -->
            <form method="GET" action="{{ route('upcoming') }}" id="filter-form" @submit="open = false">
                <!-- Date Range -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-3">Date Range</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">Start Date</label>
                            <input 
                                type="date" 
                                name="start_date" 
                                value="{{ $activeFilters['start_date'] ?? $today->format('Y-m-d') }}"
                                min="{{ $today->format('Y-m-d') }}"
                                max="{{ $maxDate->format('Y-m-d') }}"
                                class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">End Date</label>
                            <input 
                                type="date" 
                                name="end_date" 
                                value="{{ $activeFilters['end_date'] ?? $maxDate->format('Y-m-d') }}"
                                min="{{ $today->format('Y-m-d') }}"
                                max="{{ $maxDate->format('Y-m-d') }}"
                                class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
                        </div>
                    </div>
                </div>

                <!-- Platforms -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-3">Platforms</h3>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        @foreach($platformEnums as $igdbId => $enum)
                            <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-700 p-2 rounded">
                                <input 
                                    type="checkbox" 
                                    name="platforms[]" 
                                    value="{{ $igdbId }}"
                                    {{ in_array($igdbId, $activeFilters['platforms'] ?? []) ? 'checked' : '' }}
                                    class="w-4 h-4 text-orange-600 bg-gray-700 border-gray-600 rounded focus:ring-orange-500">
                                <span class="text-sm">{{ $enum->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Genres -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-3">Genres</h3>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        @foreach($allGenres as $genre)
                            <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-700 p-2 rounded">
                                <input 
                                    type="checkbox" 
                                    name="genres[]" 
                                    value="{{ $genre->id }}"
                                    {{ in_array($genre->id, $activeFilters['genres'] ?? []) ? 'checked' : '' }}
                                    class="w-4 h-4 text-orange-600 bg-gray-700 border-gray-600 rounded focus:ring-orange-500">
                                <span class="text-sm">{{ $genre->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Game Modes -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-3">Game Modes</h3>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        @foreach($allGameModes as $mode)
                            <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-700 p-2 rounded">
                                <input 
                                    type="checkbox" 
                                    name="game_modes[]" 
                                    value="{{ $mode->id }}"
                                    {{ in_array($mode->id, $activeFilters['game_modes'] ?? []) ? 'checked' : '' }}
                                    class="w-4 h-4 text-orange-600 bg-gray-700 border-gray-600 rounded focus:ring-orange-500">
                                <span class="text-sm">{{ $mode->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Game Types -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-3">Game Types</h3>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        @foreach($allGameTypes as $gameType)
                            <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-700 p-2 rounded">
                                <input 
                                    type="checkbox" 
                                    name="game_types[]" 
                                    value="{{ $gameType->value }}"
                                    {{ in_array($gameType->value, $activeFilters['game_types'] ?? []) ? 'checked' : '' }}
                                    class="w-4 h-4 text-orange-600 bg-gray-700 border-gray-600 rounded focus:ring-orange-500">
                                <span class="text-sm">{{ $gameType->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-3 pt-4 border-t border-gray-700">
                    <button 
                        type="submit"
                        class="flex-1 px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white font-semibold rounded-lg transition">
                        Apply Filters
                    </button>
                    <a 
                        href="{{ route('upcoming') }}"
                        class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white font-semibold rounded-lg transition">
                        Clear All
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

