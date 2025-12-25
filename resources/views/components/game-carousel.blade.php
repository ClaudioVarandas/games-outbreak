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

    <div class="relative group/carousel">
        @if($games && $games->count() > 0)
            <!-- Left Arrow -->
            <button
                onclick="document.getElementById('{{ $carouselId }}').scrollBy({left: -400, behavior: 'smooth'})"
                class="absolute left-4 top-1/2 -translate-y-1/2 z-50 bg-black/70 hover:bg-black/90 text-white p-4 rounded-full
                   opacity-0 group-hover/carousel:opacity-100 transition-all duration-300 shadow-2xl
                   pointer-events-auto">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>

            <!-- Right Arrow -->
            <button
                onclick="document.getElementById('{{ $carouselId }}').scrollBy({left: 400, behavior: 'smooth'})"
                class="absolute right-4 top-1/2 -translate-y-1/2 z-50 bg-black/70 hover:bg-black/90 text-white p-4 rounded-full
                   opacity-0 group-hover/carousel:opacity-100 transition-all duration-300 shadow-2xl
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
            <div id="{{ $carouselId }}" class="carousel-inner overflow-x-auto scrollbar-hide scroll-smooth">
                <div class="flex gap-6 py-4">
                    @foreach($games as $carouselGame)
                        @php
                            $platformEnums = $platformEnums ?? \App\Enums\PlatformEnum::getActivePlatforms();
                        @endphp
                        <x-game-card 
                            :game="$carouselGame"
                            variant="glassmorphism"
                            layout="overlay"
                            aspectRatio="3/4"
                            :carousel="true"
                            :platformEnums="$platformEnums" />
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


