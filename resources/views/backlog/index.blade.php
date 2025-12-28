@extends('layouts.app')

@section('title', 'My Backlog')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold mb-4 text-gray-800 dark:text-gray-100">
                My Backlog
            </h1>
            
            @if($totalResults > 0)
                <p class="text-gray-600 dark:text-gray-400">
                    {{ $totalResults }} {{ Str::plural('game', $totalResults) }}
                </p>
            @endif
        </div>

        @if($totalResults > 0)
            <!-- View Toggle and Controls -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-2">
                    <button
                        onclick="window.location.href='{{ route('backlog', ['view' => 'grid', 'page' => $currentPage]) }}'"
                        class="px-4 py-2 rounded-lg transition {{ $viewMode === 'grid' ? 'bg-orange-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}"
                    >
                        <svg class="w-5 h-5 inline-block mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                        Grid
                    </button>
                    <button
                        onclick="window.location.href='{{ route('backlog', ['view' => 'list', 'page' => $currentPage]) }}'"
                        class="px-4 py-2 rounded-lg transition {{ $viewMode === 'list' ? 'bg-orange-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}"
                    >
                        <svg class="w-5 h-5 inline-block mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                        </svg>
                        List
                    </button>
                </div>
            </div>

            <!-- Grid View -->
            @if($viewMode === 'grid')
                <x-featured-games-glassmorphism 
                    :games="$games"
                    :platformEnums="$platformEnums"
                    :initialLimit="999"
                    emptyMessage="No games in your backlog."
                />
            @else
                <!-- List View -->
                <x-search-results-list 
                    :games="$games"
                    :platformEnums="$platformEnums"
                    emptyMessage="No games in your backlog."
                />
            @endif

            <!-- Pagination -->
            @if($totalPages > 1)
                <div class="mt-8 flex items-center justify-center gap-2">
                    @if($currentPage > 1)
                        <a href="{{ route('backlog', ['view' => $viewMode, 'page' => $currentPage - 1]) }}" 
                           class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                            Previous
                        </a>
                    @endif
                    
                    <span class="px-4 py-2 text-gray-700 dark:text-gray-300">
                        Page {{ $currentPage }} @if($hasMore) of {{ $totalPages }}+ @else of {{ $totalPages }} @endif
                    </span>
                    
                    @if($hasMore)
                        <a href="{{ route('backlog', ['view' => $viewMode, 'page' => $currentPage + 1]) }}" 
                           class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                            Next
                        </a>
                    @endif
                </div>
            @endif
        @else
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                <p class="text-lg text-gray-600 dark:text-gray-400 mb-4">
                    Your backlog is empty.
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-500">
                    Start adding games to your backlog from game cards or game detail pages.
                </p>
            </div>
        @endif
    </div>
@endsection







