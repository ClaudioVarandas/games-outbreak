@extends('layouts.app')

@section('title', ($canManage ? 'Manage ' : '') . $user->name . "'s Backlog")

@section('content')
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-4xl font-bold mb-2 text-gray-800 dark:text-gray-100">
                    {{ $user->name }}'s Backlog
                    @if($canManage)
                        <span class="text-sm text-orange-600 dark:text-orange-400 font-normal ml-2">(Managing)</span>
                    @endif
                </h1>

                @if($list->games->count() > 0)
                    <p class="text-gray-600 dark:text-gray-400">
                        {{ $list->games->count() }} {{ Str::plural('game', $list->games->count()) }}
                    </p>
                @endif
            </div>

            @if($canManage)
                <!-- View Toggle -->
                <div class="flex items-center gap-2">
                    <button
                        onclick="toggleViewMode('grid')"
                        class="px-4 py-2 rounded-lg transition {{ $viewMode === 'grid' ? 'bg-orange-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}"
                    >
                        <svg class="w-5 h-5 inline-block mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                        Grid
                    </button>
                    <button
                        onclick="toggleViewMode('list')"
                        class="px-4 py-2 rounded-lg transition {{ $viewMode === 'list' ? 'bg-orange-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}"
                    >
                        <svg class="w-5 h-5 inline-block mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                        </svg>
                        List
                    </button>
                </div>
            @endif
        </div>

        @if($canManage)
            <!-- Game Search (Management Mode) -->
            <x-user-lists.game-search :user="$user" type="backlog" />
        @endif

        @if($list->games->count() > 0)
            <!-- Game Grid/List -->
            @if($canManage)
                <x-user-lists.game-grid :games="$list->games" :user="$user" type="backlog" :viewMode="$viewMode" />
            @else
                <!-- Public View (Read-only) -->
                @if($viewMode === 'grid')
                    <x-featured-games-glassmorphism
                        :games="$list->games"
                        :initialLimit="999"
                        emptyMessage="No games in this backlog."
                    />
                @else
                    <x-search-results-list
                        :games="$list->games"
                        emptyMessage="No games in this backlog."
                    />
                @endif
            @endif
        @else
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                <p class="text-lg text-gray-600 dark:text-gray-400 mb-4">
                    @if($canManage)
                        Your backlog is empty.
                    @else
                        This backlog is empty.
                    @endif
                </p>
                @if($canManage)
                    <p class="text-sm text-gray-500 dark:text-gray-500">
                        Use the search above to add games to your backlog.
                    </p>
                @endif
            </div>
        @endif
    </div>

    @if($canManage)
        <script>
            function toggleViewMode(mode) {
                fetch('{{ route('user.lists.toggle-view') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ mode: mode })
                }).then(() => {
                    window.location.reload();
                });
            }
        </script>
    @endif
@endsection
