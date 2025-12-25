@props([
    'games',
    'title' => null,
    'titleIcon' => null,
    'emptyMessage' => 'No games available.',
    'carouselId' => 'game-carousel-' . uniqid(),
    'platformEnums' => null,
])

<section class="my-6">
    @if($title)
        <h2 class="text-3xl font-bold mb-8 flex items-center text-gray-800 dark:text-gray-100">
            @if($titleIcon)
                {!! $titleIcon !!}
            @endif
            {{ $title }}
        </h2>
    @endif

    <div class="relative group">
        @if($games && $games->count() > 0)
            <!-- Left Arrow -->
            <button
                onclick="document.getElementById('{{ $carouselId }}').scrollBy({left: -400, behavior: 'smooth'})"
                class="absolute left-4 top-1/2 -translate-y-1/2 z-50 bg-black/70 hover:bg-black/90 text-white p-4 rounded-full
                   opacity-0 group-hover:opacity-100 transition-all duration-300 shadow-2xl
                   pointer-events-auto">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>

            <!-- Right Arrow -->
            <button
                onclick="document.getElementById('{{ $carouselId }}').scrollBy({left: 400, behavior: 'smooth'})"
                class="absolute right-4 top-1/2 -translate-y-1/2 z-50 bg-black/70 hover:bg-black/90 text-white p-4 rounded-full
                   opacity-0 group-hover:opacity-100 transition-all duration-300 shadow-2xl
                   pointer-events-auto">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <!-- Left Fade Overlay -->
            <div
                class="absolute left-0 top-0 bottom-0 w-32 bg-gradient-to-r from-gray-900 dark:from-gray-900 to-transparent z-10 pointer-events-none"></div>
            <!-- Right Fade Overlay -->
            <div
                class="absolute right-0 top-0 bottom-0 w-32 bg-gradient-to-l from-gray-900 dark:from-gray-900 to-transparent z-10 pointer-events-none"></div>

            <!-- Scrollable Carousel -->
            <div id="{{ $carouselId }}" class="carousel-inner overflow-x-auto scrollbar-hide scroll-smooth"
                 onwheel="this.scrollLeft += event.deltaY * 2">
                <div class="flex gap-6 py-4">
                    @foreach($games as $carouselGame)
                        @php
                            $coverUrl = $carouselGame->cover_image_id
                                ? $carouselGame->getCoverUrl('cover_big')
                                : ($carouselGame->steam_data['header_image'] ?? null);
                            $linkUrl = route('game.show', $carouselGame);
                            $platformEnums = $platformEnums ?? \App\Enums\PlatformEnum::getActivePlatforms();
                        @endphp

                        <a href="{{ $linkUrl }}"
                           class="flex-shrink-0 w-64 group/card block transform transition-all duration-300 hover:scale-105 hover:z-30">
                            <div
                                class="bg-gray-800 dark:bg-gray-800 rounded-xl overflow-hidden shadow-lg hover:shadow-2xl transition-all">
                                <div class="aspect-[3/4] relative overflow-hidden">
                                    @if($coverUrl)
                                        <img src="{{ $coverUrl }}"
                                             alt="{{ $carouselGame->name }}"
                                             class="w-full h-full object-cover group-hover/card:scale-110 transition-transform duration-500"
                                             onerror="this.onerror=null; this.replaceWith(this.nextElementSibling);">
                                        <x-game-cover-placeholder :gameName="$carouselGame->name" class="w-full h-full" style="display: none;" />
                                    @else
                                        <x-game-cover-placeholder :gameName="$carouselGame->name" class="w-full h-full" />
                                    @endif
                                    
                                    @php
                                        $validPlatformIds = $platformEnums->keys()->toArray();
                                        $displayPlatforms = $carouselGame->platforms 
                                            ? $carouselGame->platforms->filter(fn($p) => in_array($p->igdb_id, $validPlatformIds))->take(2)
                                            : collect();
                                    @endphp
                                    @if($displayPlatforms->count() > 0)
                                        <div class="absolute top-2 left-2 flex flex-wrap gap-1 z-10">
                                            @foreach($displayPlatforms as $platform)
                                                @php
                                                    $enum = $platformEnums[$platform->igdb_id] ?? null;
                                                @endphp
                                                <span class="px-2 py-1 text-xs font-bold text-white rounded shadow-lg
                                                    @if($enum)
                                                        bg-{{ $enum->color() }}-600
                                                    @else
                                                        bg-gray-600
                                                    @endif">
                                                    {{ $enum?->label() ?? \Illuminate\Support\Str::limit($platform->name, 6) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                    
                                    <div
                                        class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent opacity-0 group-hover/card:opacity-100 transition-opacity"></div>
                                </div>
                                <div class="p-4">
                                    <h3 class="font-bold text-lg text-white truncate group-hover/card:text-teal-400">
                                        {{ $carouselGame->name }}
                                    </h3>
                                    @if($carouselGame->first_release_date)
                                        <p class="text-sm text-gray-400 mt-1">
                                            {{ $carouselGame->first_release_date->format('d/m/Y') }}
                                        </p>
                                    @else
                                        <p class="text-sm text-gray-400 mt-1">TBA</p>
                                    @endif
                                    @if(true)
                                        <div class="mt-1">
                                            <span class="{{ $carouselGame->getGameTypeEnum()->colorClass() }} px-2 py-0.5 text-xs font-medium rounded text-white">
                                                {{ $carouselGame->getGameTypeEnum()->label() }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @else
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
                <p class="text-lg text-gray-600 dark:text-gray-400">
                    {{ $emptyMessage }}
                </p>
            </div>
        @endif
    </div>
</section>


