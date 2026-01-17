@extends('layouts.app')

@section('title', 'Highlights' . ($highlightsList ? ' - ' . $highlightsList->name : ''))

@section('content')
    <!-- Releases Navigation Menu -->
    <x-releases-nav active="highlights" />

    <div class="container mx-auto px-4 py-8">
        @if($highlightsList)
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">
                    {{ $highlightsList->name }}
                </h1>
                @if($highlightsList->description)
                    <p class="text-gray-600 dark:text-gray-400 mt-2">
                        {{ $highlightsList->description }}
                    </p>
                @endif
            </div>

            @if($platformGroups->isNotEmpty())
                <!-- Platform Group Filter Tabs -->
                <div class="mb-8" x-data="{ activeGroup: '{{ $defaultGroup }}' }">
                    <!-- Tab Navigation -->
                    <div class="flex flex-wrap gap-2 mb-6 border-b border-gray-200 dark:border-gray-700 pb-4">
                        @foreach($platformGroups as $group)
                            <button
                                @click="activeGroup = '{{ $group->value }}'"
                                :class="activeGroup === '{{ $group->value }}'
                                    ? '{{ $group->colorClass() }} text-white'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                                class="px-4 py-2 rounded-lg font-medium transition-colors"
                            >
                                {{ $group->label() }}
                                <span class="ml-1 text-sm opacity-80">({{ $groupCounts[$group->value] ?? 0 }})</span>
                            </button>
                        @endforeach
                    </div>

                    <!-- Games Grid for Each Platform Group -->
                    @foreach($platformGroups as $group)
                        <div x-show="activeGroup === '{{ $group->value }}'" x-cloak>
                            <!-- Group Header -->
                            <div class="flex items-center gap-3 mb-6">
                                <span class="px-3 py-1 rounded text-white font-medium {{ $group->colorClass() }}">
                                    {{ $group->label() }}
                                </span>
                                <span class="text-gray-600 dark:text-gray-400">
                                    {{ $groupCounts[$group->value] ?? 0 }} {{ Str::plural('game', $groupCounts[$group->value] ?? 0) }}
                                </span>
                            </div>

                            <!-- Games by Month -->
                            @if(!empty($gamesByGroup[$group->value]))
                                <div>
                                    @foreach($gamesByGroup[$group->value] as $monthKey => $monthData)
                                        <div class="mt-10">
                                            <!-- Month Header -->
                                            <div class="flex items-center justify-center gap-4 mb-8">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-2 h-2 rounded-full bg-orange-400"></span>
                                                    <span class="w-2 h-2 rounded-full bg-orange-400"></span>
                                                    <span class="w-2 h-2 rounded-full bg-orange-400"></span>
                                                </div>
                                                <div class="text-center px-4">
                                                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100">
                                                        {{ $monthData['label'] }}
                                                    </h3>
                                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                                        {{ count($monthData['games']) }} {{ Str::plural('game', count($monthData['games'])) }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-2 h-2 rounded-full bg-orange-400"></span>
                                                    <span class="w-2 h-2 rounded-full bg-orange-400"></span>
                                                    <span class="w-2 h-2 rounded-full bg-orange-400"></span>
                                                </div>
                                            </div>

                                            <!-- Games Grid for this Month -->
                                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-8">
                                                @foreach($monthData['games'] as $game)
                                                    @php
                                                        $pivotReleaseDate = $game->pivot->release_date ?? null;
                                                        if ($pivotReleaseDate && is_string($pivotReleaseDate)) {
                                                            $pivotReleaseDate = \Carbon\Carbon::parse($pivotReleaseDate);
                                                        }
                                                        $displayDate = $pivotReleaseDate ?? $game->first_release_date;
                                                    @endphp
                                                    <x-game-card
                                                        :game="$game"
                                                        :displayReleaseDate="$displayDate"
                                                        variant="glassmorphism"
                                                        layout="overlay"
                                                        aspectRatio="3/4"
                                                        :platformEnums="$platformEnums" />
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                                    <p class="text-gray-600 dark:text-gray-400">
                                        No games in this category.
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                    <p class="text-xl text-gray-600 dark:text-gray-400">
                        No games in the highlights list yet.
                    </p>
                </div>
            @endif
        @else
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100 mb-4">
                    Highlights
                </h1>
                <p class="text-xl text-gray-600 dark:text-gray-400">
                    No active highlights list at the moment.
                </p>
            </div>
        @endif
    </div>
@endsection
