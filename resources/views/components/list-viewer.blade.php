@props([
    'list',
    'platformEnums',
    'showHeader' => true
])

@if($list)
    @if($showHeader)
        <div class="mb-6">
            <h2 class="text-3xl font-bold text-gray-800 dark:text-gray-100">
                {{ $list->name }}
            </h2>
            @if($list->description)
                <p class="text-gray-600 dark:text-gray-400 mt-2">
                    {{ $list->description }}
                </p>
            @endif
            @if($list->start_at && $list->end_at)
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ $list->start_at->format('M d, Y') }} - {{ $list->end_at->format('M d, Y') }}
                </p>
            @endif
        </div>
    @endif

    @if($list->games->count() > 0)
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-8">
            @foreach($list->games as $game)
                <x-game-card
                    :game="$game"
                    variant="glassmorphism"
                    layout="overlay"
                    aspectRatio="3/4"
                    :platformEnums="$platformEnums" />
            @endforeach
        </div>
    @else
        <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
            <p class="text-xl text-gray-600 dark:text-gray-400">
                No games in this list.
            </p>
        </div>
    @endif
@else
    <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
        <p class="text-xl text-gray-600 dark:text-gray-400">
            No active list found.
        </p>
    </div>
@endif
