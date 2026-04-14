@props([
    'games',
    'platformEnums',
])

@php($upcomingCarouselId = 'upcoming-releases-carousel')

<div class="flex flex-col min-w-0">
    <section class="neon-section-frame grid">
        <x-homepage.section-heading
            icon="rocket"
            :title="__('Upcoming Releases')"
            :href="route('upcoming')" />

        <x-game-carousel
            :games="$games"
            :platformEnums="$platformEnums"
            :carouselId="$upcomingCarouselId"
            :showDots="false"
            variant="neon"
            :emptyMessage="__('No games releasing this week.')" />
    </section>

    {{-- Dots outside section-frame so overflow:hidden never clips them --}}
    @if($games && $games->count() > 0)
        <div class="flex md:hidden justify-center gap-2 mt-3" id="{{ $upcomingCarouselId }}-dots">
            @foreach($games as $index => $game)
                <button
                    type="button"
                    onclick="scrollCarouselToIndex('{{ $upcomingCarouselId }}', {{ $index }})"
                    class="carousel-dot w-2.5 h-2.5 rounded-full bg-white/30 transition-all duration-300"
                    data-index="{{ $index }}">
                </button>
            @endforeach
        </div>
    @endif
</div>
