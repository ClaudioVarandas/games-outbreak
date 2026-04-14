@extends('layouts.app')

@section('title', 'News-First Release Radar')

@section('body-class', 'neon-body')

@section('content')
    <div class="theme-neon overflow-x-hidden">
        <div class="page-shell pt-2">
            @if($newsEnabled && $featuredNews)
                <x-homepage.hero :featured="$featuredNews" :items="$topNews" :newsLocale="$newsLocale" />
            @endif

            <main class="neon-section pb-4">
                <x-homepage.this-week-choices
                    :games="$thisWeekGames"
                    :platformEnums="$platformEnums"
                    :currentYear="$currentYear"
                    :currentMonth="$currentMonth" />

                <x-homepage.events-grid :banners="$eventBanners" />

                <x-homepage.upcoming-releases
                    :games="$weeklyUpcomingGames"
                    :platformEnums="$platformEnums" />

                <x-homepage.latest-added-table
                    :games="$latestAddedGames"
                    :platformEnums="$platformEnums" />
            </main>
        </div>
    </div>
@endsection
