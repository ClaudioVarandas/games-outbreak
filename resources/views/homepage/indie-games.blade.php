@extends('layouts.app')

@section('title', 'Indie Games')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-10">
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">
                Indie Games
            </h1>
        </div>

        @if($indieGamesLists->count() > 0)
            @foreach($indieGamesLists as $list)
                <section class="mb-12">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">
                            {{ $list->name }}
                        </h2>
                        @if($list->slug)
                            <a href="{{ route('system-list.show', $list->slug) }}"
                               class="text-sm md:text-base text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300 font-medium transition whitespace-nowrap">
                                See full list →
                            </a>
                        @endif
                    </div>

                    @if($list->description)
                        <p class="text-gray-600 dark:text-gray-400 mb-6">
                            {{ $list->description }}
                        </p>
                    @endif

                    @if($list->games->count() > 0)
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-8">
                            @foreach($list->games->take(10) as $game)
                                <x-game-card
                                    :game="$game"
                                    variant="glassmorphism"
                                    layout="overlay"
                                    aspectRatio="3/4"
                                    :platformEnums="$platformEnums" />
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 bg-white dark:bg-gray-800 rounded-lg">
                            <p class="text-gray-600 dark:text-gray-400">
                                No games in this list yet.
                            </p>
                        </div>
                    @endif
                </section>
            @endforeach
        @else
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                <p class="text-xl text-gray-600 dark:text-gray-400">
                    No indie games lists available yet. Check back soon!
                </p>
            </div>
        @endif
    </div>
@endsection
