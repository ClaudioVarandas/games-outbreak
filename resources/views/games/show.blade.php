@extends('layouts.app')

@section('title', $game->name)

@section('content')
    <div class="min-h-screen bg-gray-900 text-white">
        <!-- Hero with Trailer or Header -->
        <div class="relative h-96 overflow-hidden">
            {{--@if($game->trailers && count($game->trailers) > 0)
                <iframe src="{{ $game->getPrimaryTrailerEmbed() }}" class="absolute inset-0 w-full h-full" frameborder="0" allowfullscreen></iframe>
            @else--}}
            @if($game->steam_data['header_image'] ?? null)
                <img src="{{ $game->steam_data['header_image'] }}" 
                     class="absolute inset-0 w-full h-full object-cover"
                     onerror="this.onerror=null; this.replaceWith(this.nextElementSibling);">
                <x-game-cover-placeholder :gameName="$game->name" class="absolute inset-0 w-full h-full" style="display: none;" />
            @elseif($game->cover_image_id)
                <img src="{{ $game->getCoverUrl('1080p') }}" 
                     class="absolute inset-0 w-full h-full object-cover"
                     onerror="this.onerror=null; this.replaceWith(this.nextElementSibling);">
                <x-game-cover-placeholder :gameName="$game->name" class="absolute inset-0 w-full h-full" style="display: none;" />
            @else
                <x-game-cover-placeholder :gameName="$game->name" class="absolute inset-0 w-full h-full" />
            @endif

            <!-- Dark Overlay for Readability -->
            <div class="absolute inset-0 bg-gradient-to-t from-black via-black/70 to-transparent"></div>

            <!-- Left-Aligned Container -->
            <div class="absolute inset-0 flex items-center justify-start">
                <div class="container mx-auto px-8 w-full">
                    <h1 class="text-5xl md:text-5xl font-black mb-6 drop-shadow-2xl text-white text-left">
                        {{ $game->name }}
                    </h1>

                    <!-- Platforms -->
                    @php
                        $validPlatformIds = $platformEnums->keys()->toArray();
                        $displayPlatforms = $game->platforms 
                            ? $game->platforms->filter(fn($p) => in_array($p->igdb_id, $validPlatformIds))
                            : collect();
                    @endphp
                    @if($displayPlatforms->count() > 0)
                        <div class="flex flex-wrap gap-2 mb-4">
                            @foreach($displayPlatforms as $plat)
                                @php $enum = $platformEnums[$plat->igdb_id] ?? null @endphp
                                <span
                                    class="px-3 py-1.5 bg-{{ $enum?->color() ?? 'gray' }}-600 text-white font-semibold rounded-md text-sm shadow-lg">
                            {{ $enum?->label() ?? $plat->name }}
                        </span>
                            @endforeach
                        </div>
                    @endif

                    <!-- Game Type Badge -->
                    @if(true)
                        <div class="mb-6">
                            <span class="{{ $game->getGameTypeEnum()->colorClass() }} px-2 py-0.5 text-xs font-medium rounded text-white">
                                {{ $game->getGameTypeEnum()->label() }}
                            </span>
                        </div>
                    @endif

                    <!-- Wishlist Count -->
                    @if($game->steam_data['wishlist_formatted'] ?? null)
                        <p class="text-2xl md:text-3xl font-bold text-teal-400 drop-shadow-lg text-left">
                            🔥 {{ $game->steam_data['wishlist_formatted'] }} wishlists on Steam
                        </p>
                    @endif

                    <!-- Release Date -->
                    @if($game->first_release_date)
                        @php
                            $daysDiff = (int) round($game->first_release_date->diffInDays(now(), false));
                            $releaseText = '';
                            if ($daysDiff >= 0) {
                                // Past or today
                                if ($daysDiff < 1) {
                                    $releaseText = 'Released ' . $game->first_release_date->format('d/m/Y') . ' (Today)';
                                } else {
                                    $releaseText = 'Released ' . $game->first_release_date->format('d/m/Y') . " ({$daysDiff} " . ($daysDiff === 1 ? 'day' : 'days') . " ago)";
                                }
                            } else {
                                // Future
                                $daysUntil = abs($daysDiff);
                                $releaseText = 'Release date ' . $game->first_release_date->format('d/m/Y') . " (in {$daysUntil} " . ($daysUntil === 1 ? 'day' : 'days') . ")";
                            }
                        @endphp
                        <p class="text-lg text-gray-300 mt-4 text-left">
                            {{ $releaseText }}
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <div class="container mx-auto px-8 py-12">
            <!-- Main Content + Sidebar Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-10">
                <!-- Main Content (left 3/4) -->
                <div class="lg:col-span-3 space-y-12">
                    <!-- Summary -->
                    <section>
                        <h2 class="text-3xl font-bold mb-6 flex items-center">
                            <svg class="w-8 h-8 mr-3 text-teal-400" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                <path fill-rule="evenodd"
                                      d="M2 10a8 8 0 1116 0 8 8 0 01-16 0zm1 0a7 7 0 1014 0 7 7 0 00-14 0z"
                                      clip-rule="evenodd"/>
                            </svg>
                            About
                        </h2>
                        <p class="text-lg text-gray-300 leading-relaxed">{{ $game->summary ?? 'No summary available.' }}</p>
                    </section>

                    <!-- Steam Reviews -->
                    @if($game->steam_data['reviews_summary']['rating'] ?? null)
                        <section class="bg-gray-800 p-6 rounded-xl">
                            <h3 class="text-2xl font-bold mb-4">Steam Reviews</h3>
                            <div class="flex items-center gap-6">
                                <div class="text-5xl font-black text-green-400">
                                    {{ $game->steam_data['reviews_summary']['percentage'] ?? 'N/A' }}%
                                </div>
                                <div>
                                    <p class="text-xl font-bold">{{ $game->steam_data['reviews_summary']['rating'] }}</p>
                                    <p class="text-gray-400">
                                        from {{ number_format($game->steam_data['reviews_summary']['total'] ?? 0) }}
                                        reviews</p>
                                </div>
                            </div>
                        </section>
                    @endif

                    <!-- Screenshots -->
                    @if($game->screenshots && count($game->screenshots) > 0)
                        <section>
                            <h2 class="text-3xl font-bold mb-6">Screenshots</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                @foreach(collect($game->screenshots)->take(6) as $shot)
                                    <div
                                        class="rounded-xl overflow-hidden shadow-2xl hover:scale-105 transition-transform cursor-pointer">
                                        <img
                                            src="{{ app(\App\Services\IgdbService::class)->getScreenshotUrl($shot['image_id'], 'screenshot_big') }}"
                                            class="w-full h-auto" alt="">
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                </div>

                <!-- Sidebar -->
                <div class="space-y-8">
                    <!-- Release Dates -->
                    <div class="bg-gray-800 p-6 rounded-xl">
                        <h3 class="text-xl font-bold mb-4">Release Dates</h3>
                        @php
                            $activePlatformIds = $platformEnums->keys()->toArray();
                            $activeReleaseDates = collect($game->release_dates ?? [])
                                ->filter(function ($rd) use ($activePlatformIds) {
                                    return isset($rd['platform']) && in_array($rd['platform'], $activePlatformIds);
                                })
                                ->sortBy('date')
                                ->values();
                        @endphp
                        @if($activeReleaseDates->count() > 0)
                            <div class="space-y-2">
                                @foreach($activeReleaseDates as $rd)
                                    @php
                                        $platformEnum = $platformEnums[$rd['platform']] ?? null;
                                        $platformName = $platformEnum?->label() ?? ($rd['platform_name'] ?? 'Unknown Platform');
                                        $releaseDate = $rd['release_date'] ?? 'TBA';
                                    @endphp
                                    <p class="text-gray-300">
                                        {{ $platformName }} - {{ $releaseDate }}
                                    </p>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-400">
                                {{ $game->first_release_date?->format('d/m/Y') ?? 'TBA' }}
                            </p>
                        @endif
                    </div>

                    <!-- Where to Buy -->
                    <div class="bg-gray-800 p-6 rounded-xl space-y-4">
                        <h3 class="text-xl font-bold">Where to Buy</h3>
                        @if($game->steam_data['appid'] ?? null)
                            <a href="https://store.steampowered.com/app/{{ $game->steam_data['appid'] }}"
                               target="_blank"
                               class="block bg-red-600 hover:bg-red-700 text-white font-bold py-4 px-6 rounded-lg text-center transition">
                                <svg class="w-6 h-6 inline mr-2" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M11.5 0C5.1 0 0 5.1 0 11.5S5.1 23 11.5 23 23 17.9 23 11.5 17.9 0 11.5 0zm6.3 17.1c-.8.6-2 .7-3 .3-.7-.3-1.4-.8-2-1.4l2.8-1.2c.3.4.7.8 1.2 1 .7.3 1.5.2 2-.2.6-.5.8-1.3.6-2-.2-.8-.9-1.3-1.7-1.2l1.4-1.9c1 .4 1.7 1.3 1.9 2.4.3 1.3-.4 2.7-1.7 3.2zM6.9 13.6c-.3-.3-.4.1-.5.4-.1.3-.1.6 0 .9.2.5.6.9 1.1 1 .8.2 1.6-.2 1.9-1 .2-.6 0-1.2-.5-1.6-.5-.4-1.2-.4-1.8 0l1.9-1.4c-.6-1-1.7-1.5-2.8-1.3-1.3.2-2.3 1.3-2.5 2.6-.2 1.3.6 2.6 1.8 3 .8.3 1.7.2 2.4-.3.5-.4.8-.9 1-1.5l-2.5 1.1c-.1-.3-.3-.6-.5-.9z"/>
                                </svg>
                                Buy on Steam
                                @if($game->getSteamPrice())
                                    <span class="block text-sm mt-1">{{ $game->getSteamPrice() }}</span>
                                @endif
                            </a>
                        @else
                            <p class="text-gray-400">No store links available yet.</p>
                        @endif
                    </div>

                    <!-- Add to List -->
                    <x-add-to-list :game="$game" />

                    <!-- Genres & Modes -->
                    <div class="bg-gray-800 p-6 rounded-xl">
                        <h3 class="text-xl font-bold mb-4">Genres</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach($game->genres as $genre)
                                <span class="px-4 py-2 bg-purple-700 rounded-full text-sm">{{ $genre->name }}</span>
                            @endforeach
                        </div>
                    </div>

                    <div class="bg-gray-800 p-6 rounded-xl">
                        <h3 class="text-xl font-bold mb-4">Game Modes</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach($game->gameModes as $mode)
                                <span class="px-4 py-2 bg-indigo-700 rounded-full text-sm">{{ $mode->name }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Full-Width Similar Games Section (spans both columns) -->
            <div class="mt-10 -mx-8 px-8 bg-gray-800/50 rounded-xl py-3">
                <div class="container mx-auto">
                    @include('games.partials.similar-games', ['game' => $game, 'platformEnums' => $platformEnums])
                </div>
            </div>
        </div>
        @endsection

        @push('scripts')
            <script>
                /*        document.addEventListener('DOMContentLoaded', function () {
                            const iframe = document.querySelector('iframe[src*="youtube.com"]');
                            if (iframe) {
                                iframe.addEventListener('load', function () {
                                    // Unmute on first user interaction
                                    document.body.addEventListener('click', function unmute() {
                                        iframe.contentWindow.postMessage('{"event":"command","func":"unMute","args":""}', '*');
                                        document.body.removeEventListener('click', unmute);
                                    }, { once: true });
                                });
                            }
                        });*/
            </script>
    @endpush
