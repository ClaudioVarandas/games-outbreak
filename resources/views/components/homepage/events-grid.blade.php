@props([
    'banners' => [],
])

<section id="events" class="neon-section-frame grid gap-5 scroll-mt-32">
    <x-homepage.section-heading icon="broadcast" title="Events" :href="route('events')" linkText="See timeline" />

    @if(count($banners) > 0)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($banners as $banner)
                @php
                    $isUpcoming = ($banner['status'] ?? 'upcoming') === 'upcoming';
                @endphp
                <a href="{{ $banner['link'] ?? '#' }}" class="neon-card group block p-[9px]">
                    {{-- Image with status badge --}}
                    <div class="relative [transform:translateZ(0)] overflow-hidden rounded-[14px]" style="height:220px">
                        @if(!empty($banner['image']))
                            <img
                                src="{{ $banner['image'] }}"
                                alt="{{ $banner['alt'] ?? 'Event' }}"
                                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                loading="lazy"
                                onerror="this.src='/images/game-cover-placeholder.svg'">
                        @else
                            <div class="h-full w-full bg-gradient-to-br from-orange-500/20 to-violet-500/20"></div>
                        @endif

                        {{-- bottom gradient scrim --}}
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/55 via-transparent to-transparent"></div>

                        {{-- Status badge — top-left overlay --}}
                        <span class="absolute left-2 top-2 inline-flex items-center gap-1 rounded-full border px-2 py-[3px] text-[0.66rem] font-bold uppercase tracking-[0.05em]
                            {{ $isUpcoming
                                ? 'border-cyan-400/70 bg-cyan-950/85 text-cyan-300'
                                : 'border-orange-400/70 bg-orange-950/85 text-orange-300' }}">
                            <span class="inline-block h-[5px] w-[5px] shrink-0 rounded-full
                                {{ $isUpcoming
                                    ? 'bg-cyan-400'
                                    : 'bg-orange-400' }}"
                                style="box-shadow:0 0 6px {{ $isUpcoming ? 'var(--neon-cyan)' : 'var(--neon-orange)' }}"></span>
                            {{ $isUpcoming ? 'Upcoming' : 'Past Event' }}
                        </span>
                    </div>

                    {{-- Title — relative so it stacks above neon-card::before gradient overlay --}}
                    <h3 class="relative mt-[14px] px-1 pb-1 text-[0.88rem] font-bold uppercase leading-snug tracking-[0.04em] text-slate-100 line-clamp-2">
                        {{ $banner['alt'] ?? 'Gaming Event' }}
                    </h3>
                </a>
            @endforeach
        </div>
    @else
        <div class="neon-panel p-8 text-center text-sm uppercase tracking-[0.08em] text-slate-400">
            No active events right now.
        </div>
    @endif
</section>
