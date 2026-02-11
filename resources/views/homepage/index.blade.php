@extends('layouts.app')

@section('title', 'Curated Game lists')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-4xl font-bold text-center mb-10 text-gray-800 dark:text-gray-100 flex items-center justify-center gap-4">
            <div class="flex gap-2">
                <div class="w-2 h-2 rounded-full bg-orange-400"></div>
                <div class="w-2 h-2 rounded-full bg-orange-500"></div>
                <div class="w-2 h-2 rounded-full bg-orange-400"></div>
            </div>
            Events
            <div class="flex gap-2">
                <div class="w-2 h-2 rounded-full bg-orange-400"></div>
                <div class="w-2 h-2 rounded-full bg-orange-500"></div>
                <div class="w-2 h-2 rounded-full bg-orange-400"></div>
            </div>
        </h1>

        <!-- Official Events Section -->
        <section class="mb-12">
            <x-seasonal-banners :banners="$eventBanners"/>
        </section>

        <h1 class="text-2xl md:text-4xl font-bold text-center mb-10 text-gray-800 dark:text-gray-100 flex items-center justify-center gap-2 md:gap-4">
            <div class="hidden sm:flex gap-2">
                <div class="w-2 h-2 rounded-full bg-orange-400"></div>
                <div class="w-2 h-2 rounded-full bg-orange-500"></div>
                <div class="w-2 h-2 rounded-full bg-orange-400"></div>
            </div>
            Game Releases
            <div class="hidden sm:flex gap-2">
                <div class="w-2 h-2 rounded-full bg-orange-400"></div>
                <div class="w-2 h-2 rounded-full bg-orange-500"></div>
                <div class="w-2 h-2 rounded-full bg-orange-400"></div>
            </div>
        </h1>

        <!-- This Week's Choices Section -->
        <section class="mb-12">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl md:text-3xl font-bold flex items-center text-gray-800 dark:text-gray-100">
                    <svg class="w-6 h-6 md:w-8 md:h-8 mr-2 md:mr-3 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    This Week's Choices
                </h2>
                <a href="{{ route('releases.year.month', [$currentYear, $currentMonth]) }}"
                   class="text-sm md:text-base text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300 font-medium transition whitespace-nowrap">
                    See all ->
                </a>
            </div>

            @if($thisWeekGames->count() > 0)
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-6">
                    @foreach($thisWeekGames as $game)
                        <x-game-card
                            :game="$game"
                            :displayReleaseDate="$game->pivot->release_date ? \Carbon\Carbon::parse($game->pivot->release_date) : $game->first_release_date"
                            variant="default"
                            layout="overlay"
                            aspectRatio="3/4"
                            :platformEnums="$platformEnums" />
                    @endforeach
                </div>
            @else
                <div class="text-center py-8 bg-white dark:bg-gray-800 rounded-lg">
                    <p class="text-gray-600 dark:text-gray-400">
                        No curated releases this week.
                    </p>
                    <a href="{{ route('releases.year', $currentYear) }}"
                       class="inline-block mt-2 text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300 font-medium transition">
                        Browse all {{ $currentYear }} releases ->
                    </a>
                </div>
            @endif
        </section>

        <!-- Weekly Upcoming Games Section -->
        <section class="mb-12">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl md:text-3xl font-bold flex items-center text-gray-800 dark:text-gray-100">
                    <svg class="w-6 h-6 md:w-8 md:h-8 mr-2 md:mr-3 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                              clip-rule="evenodd"/>
                    </svg>
                    All Upcoming Releases
                </h2>
                <a href="{{ route('upcoming') }}"
                   class="text-sm md:text-base text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300 font-medium transition whitespace-nowrap">
                    See all ->
                </a>
            </div>

            <x-game-carousel
                :games="$weeklyUpcomingGames"
                :platformEnums="$platformEnums"
                emptyMessage="No games releasing this week."
            />
        </section>

        <h1 class="text-2xl md:text-4xl font-bold text-center mb-10 text-gray-800 dark:text-gray-100 flex items-center justify-center gap-2 md:gap-4">
            <div class="hidden sm:flex gap-2">
                <div class="w-2 h-2 rounded-full bg-orange-400"></div>
                <div class="w-2 h-2 rounded-full bg-orange-500"></div>
                <div class="w-2 h-2 rounded-full bg-orange-400"></div>
            </div>
            Seasoned lists
            <div class="hidden sm:flex gap-2">
                <div class="w-2 h-2 rounded-full bg-orange-400"></div>
                <div class="w-2 h-2 rounded-full bg-orange-500"></div>
                <div class="w-2 h-2 rounded-full bg-orange-400"></div>
            </div>
        </h1>

        <!-- Seasonal Events Section -->
        <section class="mb-12">
            <x-seasonal-banners :banners="[
                [
                    'image' => '/images/best_of_2025.png',
                    'link' => route('lists.show', ['seasoned', 'best-games-of-2025']),
                    'alt' => 'best games of 2025 banner'
                ],
                [
                    'image' => '/images/most_wanted_2026.png',
                    'link' => route('lists.show', ['seasoned', 'most-wanted-2026']),
                    'alt' => 'Most Wanted Games 2026 banner'
                ]
            ]"/>
        </section>

        <h1 class="text-2xl md:text-4xl font-bold text-center mb-10 text-gray-800 dark:text-gray-100 flex items-center justify-center gap-2 md:gap-4">
            <div class="hidden sm:flex gap-2">
                <div class="w-2 h-2 rounded-full bg-orange-400"></div>
                <div class="w-2 h-2 rounded-full bg-orange-500"></div>
                <div class="w-2 h-2 rounded-full bg-orange-400"></div>
            </div>
            Latest Added Games
            <div class="hidden sm:flex gap-2">
                <div class="w-2 h-2 rounded-full bg-orange-400"></div>
                <div class="w-2 h-2 rounded-full bg-orange-500"></div>
                <div class="w-2 h-2 rounded-full bg-orange-400"></div>
            </div>
        </h1>

        <x-latest-added-games :games="$latestAddedGames" :platformEnums="$platformEnums" />
    </div>
@endsection
