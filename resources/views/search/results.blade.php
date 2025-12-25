@extends('layouts.app')

@section('title', $query ? 'Search: ' . $query : 'Search Results')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold mb-4 text-gray-800 dark:text-gray-100">
                @if($query)
                    Search Results for "{{ $query }}"
                @else
                    Search Results
                @endif
            </h1>
            
            @if($totalResults > 0)
                <p class="text-gray-600 dark:text-gray-400">
                    Found {{ $totalResults }} {{ Str::plural('result', $totalResults) }}
                </p>
            @endif
        </div>

        @if($query && strlen($query) >= 2)
            <!-- View Toggle and Controls -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-2">
                    <button
                        onclick="window.location.href='{{ route('search', ['q' => $query, 'view' => 'grid']) }}'"
                        class="px-4 py-2 rounded-lg transition {{ $viewMode === 'grid' ? 'bg-orange-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}"
                    >
                        <svg class="w-5 h-5 inline-block mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                        Grid
                    </button>
                    <button
                        onclick="window.location.href='{{ route('search', ['q' => $query, 'view' => 'list']) }}'"
                        class="px-4 py-2 rounded-lg transition {{ $viewMode === 'list' ? 'bg-orange-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}"
                    >
                        <svg class="w-5 h-5 inline-block mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                        </svg>
                        List
                    </button>
                </div>
            </div>

            @if($games->count() > 0)
                <!-- Grid View -->
                @if($viewMode === 'grid')
                    <x-featured-games-glassmorphism 
                        :games="$games"
                        :platformEnums="$platformEnums"
                        :initialLimit="999"
                        emptyMessage="No games found."
                    />
                @else
                    <!-- List View -->
                    <x-search-results-list 
                        :games="$games"
                        :platformEnums="$platformEnums"
                        emptyMessage="No games found."
                    />
                @endif

                <!-- Pagination -->
                @if($totalPages > 1)
                    <div class="mt-8 flex items-center justify-center gap-2">
                        @if($currentPage > 1)
                            <a href="{{ route('search', ['q' => $query, 'view' => $viewMode, 'page' => $currentPage - 1]) }}" 
                               class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                                Previous
                            </a>
                        @endif
                        
                        <span class="px-4 py-2 text-gray-700 dark:text-gray-300">
                            Page {{ $currentPage }} @if($hasMore) of {{ $totalPages }}+ @endif
                        </span>
                        
                        @if($hasMore)
                            <a href="{{ route('search', ['q' => $query, 'view' => $viewMode, 'page' => $currentPage + 1]) }}" 
                               class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                                Next
                            </a>
                        @endif
                    </div>
                @endif
            @else
                <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                    <p class="text-lg text-gray-600 dark:text-gray-400">
                        No games found for "{{ $query }}"
                    </p>
                </div>
            @endif
        @else
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                <p class="text-lg text-gray-600 dark:text-gray-400">
                    Please enter a search query (at least 2 characters).
                </p>
            </div>
        @endif
    </div>
@endsection

