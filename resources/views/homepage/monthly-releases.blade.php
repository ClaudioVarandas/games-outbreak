@extends('layouts.app')

@section('title', $activeList ? $activeList->name . ' Releases' : 'Monthly Releases')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-10">
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">
                @if($activeList)
                    {{ $activeList->name }} Releases
                @else
                    Monthly Releases
                @endif
            </h1>
            @if($activeList)
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    @if($activeList->start_at && $activeList->end_at)
                        {{ $activeList->start_at->format('d/m/Y') }} - {{ $activeList->end_at->format('d/m/Y') }}
                    @endif
                </div>
            @endif
        </div>

        @if($activeList && $monthGames->count() > 0)
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-8">
                @foreach($monthGames as $game)
                    <x-game-card 
                        :game="$game"
                        variant="glassmorphism"
                        layout="overlay"
                        aspectRatio="3/4"
                        :platformEnums="$platformEnums" />
                @endforeach
            </div>
        @elseif($activeList && $monthGames->count() === 0)
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                <p class="text-xl text-gray-600 dark:text-gray-400">
                    No games found in this month's list.
                </p>
            </div>
        @else
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                <p class="text-xl text-gray-600 dark:text-gray-400">
                    No active monthly list found.
                </p>
            </div>
        @endif
    </div>
@endsection

