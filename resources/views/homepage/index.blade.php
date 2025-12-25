@extends('layouts.app')

@section('title', 'Homepage')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-4xl font-bold text-center mb-10 text-gray-800 dark:text-gray-100">
            Game Releases
        </h1>

        <!-- Featured Games Section -->
        <section class="mb-12">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                    Featured Games
                </h2>
                @if($activeList)
                    <a href="{{ route('monthly-releases') }}" class="text-teal-600 dark:text-teal-400 hover:text-teal-700 dark:hover:text-teal-300 font-medium transition">
                        Monthly Featured Games →
                    </a>
                @endif
            </div>

            @if($activeList && $featuredGames->count() > 0)
                <x-game-carousel 
                    :games="$featuredGames"
                    :platformEnums="$platformEnums"
                    emptyMessage="No featured games available."
                />
            @elseif($activeList && $featuredGames->count() === 0)
                <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                    <p class="text-lg text-gray-600 dark:text-gray-400 mb-4">
                        No featured games available.
                    </p>
                    <a href="{{ route('monthly-releases') }}" class="inline-block text-teal-600 dark:text-teal-400 hover:text-teal-700 dark:hover:text-teal-300 font-medium transition">
                        View Monthly Featured Games →
                    </a>
                </div>
            @else
                <x-game-carousel 
                    :games="collect()"
                    :platformEnums="$platformEnums"
                    emptyMessage="No active monthly list found."
                />
            @endif
        </section>

        <!-- Weekly Upcoming Games Section -->
        <section class="mb-12">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                    Upcoming Releases
                </h2>
                <a href="{{ route('upcoming') }}" class="text-teal-600 dark:text-teal-400 hover:text-teal-700 dark:hover:text-teal-300 font-medium transition">
                    View All Upcoming →
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

