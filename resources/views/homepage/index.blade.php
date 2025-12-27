@extends('layouts.app')

@section('title', 'Homepage')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-4xl font-bold text-center mb-10 text-gray-800 dark:text-gray-100 flex items-center justify-center gap-4">
            <div class="flex gap-2">
                <div class="w-2 h-2 rounded-full bg-orange-400"></div>
                <div class="w-2 h-2 rounded-full bg-orange-500"></div>
                <div class="w-2 h-2 rounded-full bg-orange-400"></div>
            </div>
            Game Releases
            <div class="flex gap-2">
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
                    'link' => route('system-list.show',['slug' => 'best-games-2025']),
                    'alt' => 'best games of 2025 banner'
                ],
                [
                    'image' => '/images/most_wanted_2026.png',
                    'link' => route('system-list.show',['slug' => 'most-wanted-2026']),
                    'alt' => 'Most Wanted Games 2026 banner'
                ]
            ]"/>
        </section>

        <!-- Featured Games Section -->
        <section class="mb-12">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-3xl font-bold flex items-center text-gray-800 dark:text-gray-100">
                    <svg class="w-8 h-8 mr-3 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    @if($activeList && $activeList->start_at)
                        {{ $activeList->start_at->format('F Y') }} Highlights
                    @else
                        Featured Games
                    @endif
                </h2>
                @if($activeList)
                    <a href="{{ route('monthly-releases') }}"
                       class="text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300 font-medium transition">
                        See all ->
                    </a>
                @endif
            </div>

            @if($activeList && $featuredGames->count() > 0)
                <x-featured-games-glassmorphism
                    :games="$featuredGames"
                    :platformEnums="$platformEnums"
                    emptyMessage="No featured games available."
                />
            @elseif($activeList && $featuredGames->count() === 0)
                <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                    <p class="text-lg text-gray-600 dark:text-gray-400 mb-4">
                        No featured games available.
                    </p>
                    <a href="{{ route('monthly-releases') }}"
                       class="inline-block text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300 font-medium transition">
                        See all ->
                    </a>
                </div>
            @else
                <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                    <p class="text-lg text-gray-600 dark:text-gray-400">
                        No active monthly list found.
                    </p>
                </div>
            @endif
        </section>

        <!-- Weekly Upcoming Games Section -->
        <section class="mb-12">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-3xl font-bold flex items-center text-gray-800 dark:text-gray-100">
                    <svg class="w-8 h-8 mr-3 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                              clip-rule="evenodd"/>
                    </svg>
                    Upcoming Releases
                </h2>
                <a href="{{ route('upcoming') }}"
                   class="text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300 font-medium transition">
                    See all ->
                </a>
            </div>

            <x-game-carousel
                :games="$weeklyUpcomingGames"
                :platformEnums="$platformEnums"
                emptyMessage="No games releasing this week."
            />
        </section>
    </div>
@endsection

