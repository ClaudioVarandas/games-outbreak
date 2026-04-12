@props([
    'games',
    'title' => null,
    'titleIcon' => null,
    'emptyMessage' => 'No games available.',
    'carouselId' => 'game-carousel-' . uniqid(),
    'platformEnums' => null,
    'variant' => 'glassmorphism',
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

    <div class="relative group/carousel {{ $variant === 'neon' ? 'neon-carousel-shell' : '' }}">
        @if($games && $games->count() > 0)
            @if($variant === 'neon')
                <div class="neon-release-carousel-shell w-full max-w-full">
                    <div class="neon-release-carousel-viewport">
                        <button
                            type="button"
                            aria-label="Previous upcoming releases"
                            onclick="document.getElementById('{{ $carouselId }}').scrollBy({left: -400, behavior: 'smooth'})"
                            class="neon-carousel-arrow hidden md:flex items-center justify-center rounded-full border border-cyan-300/20 bg-slate-900/95 text-white shadow-2xl transition-all duration-300 hover:border-orange-300/40 hover:bg-slate-900 left-3">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>

                        <div class="neon-release-carousel-edge neon-release-carousel-edge--left hidden md:block"></div>
                        <div class="neon-release-carousel-edge neon-release-carousel-edge--right hidden md:block"></div>

                        <div id="{{ $carouselId }}" class="carousel-inner w-full max-w-full overflow-x-auto scrollbar-hide scroll-smooth snap-x snap-mandatory">
                            <div class="neon-release-carousel-track flex gap-4 py-4 px-2 md:gap-3 items-start">
                                @foreach($games as $carouselGame)
                                    @php
                                        $platformEnums = $platformEnums ?? \App\Enums\PlatformEnum::getActivePlatforms();

                                        $displayDate = $carouselGame->first_release_date;
                                        if (isset($carouselGame->pivot->release_date) && $carouselGame->pivot->release_date) {
                                            $displayDate = \Carbon\Carbon::parse($carouselGame->pivot->release_date);
                                        }
                                    @endphp
                                    <div class="snap-start neon-release-carousel-slide">
                                        <x-game-card
                                            :game="$carouselGame"
                                            :variant="$variant"
                                            layout="below"
                                            aspectRatio="3/4"
                                            :carousel="true"
                                            :platformEnums="$platformEnums"
                                            :displayReleaseDate="$displayDate"
                                            :displayPlatforms="isset($carouselGame->pivot) ? ($carouselGame->pivot->platforms ?? null) : null" />
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <button
                            type="button"
                            aria-label="Next upcoming releases"
                            onclick="document.getElementById('{{ $carouselId }}').scrollBy({left: 400, behavior: 'smooth'})"
                            class="neon-carousel-arrow hidden md:flex items-center justify-center rounded-full border border-cyan-300/20 bg-slate-900/95 text-white shadow-2xl transition-all duration-300 hover:border-orange-300/40 hover:bg-slate-900 right-3">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                </div>
            @else
                <div class="relative">
                    <div class="relative min-w-0">
                        <div class="hidden md:block absolute left-0 top-0 bottom-0 w-32 bg-gradient-to-r from-gray-900 dark:from-gray-900 to-transparent z-10 pointer-events-none"></div>
                        <div class="hidden md:block absolute right-0 top-0 bottom-0 w-32 bg-gradient-to-l from-gray-900 dark:from-gray-900 to-transparent z-10 pointer-events-none"></div>

                        <button
                            onclick="document.getElementById('{{ $carouselId }}').scrollBy({left: -400, behavior: 'smooth'})"
                            class="hidden md:block absolute left-4 top-1/2 -translate-y-1/2 z-50 bg-black/70 hover:bg-black/90 text-white p-4 rounded-full
                               opacity-0 group-hover/carousel:opacity-100 transition-all duration-300 shadow-2xl
                               pointer-events-auto">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>

                        <button
                            onclick="document.getElementById('{{ $carouselId }}').scrollBy({left: 400, behavior: 'smooth'})"
                            class="hidden md:block absolute right-4 top-1/2 -translate-y-1/2 z-50 bg-black/70 hover:bg-black/90 text-white p-4 rounded-full
                               opacity-0 group-hover/carousel:opacity-100 transition-all duration-300 shadow-2xl
                               pointer-events-auto">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>

                        <div id="{{ $carouselId }}" class="carousel-inner overflow-x-auto scrollbar-hide scroll-smooth snap-x snap-mandatory">
                            <div class="flex gap-4 md:gap-6 py-4 px-2 md:px-0">
                                @foreach($games as $carouselGame)
                                    @php
                                        $platformEnums = $platformEnums ?? \App\Enums\PlatformEnum::getActivePlatforms();

                                        $displayDate = $carouselGame->first_release_date;
                                        if (isset($carouselGame->pivot->release_date) && $carouselGame->pivot->release_date) {
                                            $displayDate = \Carbon\Carbon::parse($carouselGame->pivot->release_date);
                                        }
                                    @endphp
                                    <div class="snap-start">
                                        <x-game-card
                                            :game="$carouselGame"
                                            :variant="$variant"
                                            layout="overlay"
                                            aspectRatio="3/4"
                                            :carousel="true"
                                            :platformEnums="$platformEnums"
                                            :displayReleaseDate="$displayDate"
                                            :displayPlatforms="isset($carouselGame->pivot) ? ($carouselGame->pivot->platforms ?? null) : null" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Pagination Dots (Mobile only) -->
            <div class="flex md:hidden justify-center gap-2 mt-4" id="{{ $carouselId }}-dots">
                @foreach($games as $index => $game)
                    <button
                        onclick="scrollCarouselToIndex('{{ $carouselId }}', {{ $index }})"
                        class="carousel-dot w-2 h-2 rounded-full bg-gray-600 transition-all duration-300"
                        data-index="{{ $index }}">
                    </button>
                @endforeach
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

@push('scripts')
<script>
// Carousel pagination dots functionality
function scrollCarouselToIndex(carouselId, index) {
    const carousel = document.getElementById(carouselId);
    const cards = carousel.querySelectorAll('.snap-start');
    if (cards[index]) {
        cards[index].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Update active dot on scroll
    document.querySelectorAll('.carousel-inner').forEach(carousel => {
        const carouselId = carousel.id;
        const dotsContainer = document.getElementById(carouselId + '-dots');

        if (!dotsContainer) return;

        const dots = dotsContainer.querySelectorAll('.carousel-dot');
        const cards = carousel.querySelectorAll('.snap-start');

        function updateActiveDot() {
            const scrollLeft = carousel.scrollLeft;
            const cardWidth = cards[0]?.offsetWidth || 0;
            const gap = 16; // gap-4 = 16px on mobile
            const currentIndex = Math.round(scrollLeft / (cardWidth + gap));

            dots.forEach((dot, index) => {
                if (index === currentIndex) {
                    dot.classList.remove('bg-gray-600');
                    dot.classList.add('bg-orange-500', 'w-6');
                } else {
                    dot.classList.remove('bg-orange-500', 'w-6');
                    dot.classList.add('bg-gray-600');
                }
            });
        }

        carousel.addEventListener('scroll', updateActiveDot);
        updateActiveDot(); // Initialize on load
    });
});
</script>
@endpush
