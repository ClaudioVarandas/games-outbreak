@props([
    'banner' => [],
    'size' => 'md',
])

@php
    $isUpcoming = ($banner['status'] ?? 'upcoming') === 'upcoming';

    $imageHeight = match ($size) {
        'lg' => 'h-[220px] sm:h-[300px] lg:h-[380px]',
        'md' => 'h-[182px]',
        default => 'h-[150px]',
    };
@endphp

<a href="{{ $banner['link'] ?? '#' }}" class="neon-card group block p-[9px]">
    {{-- Image with status + time overlays --}}
    <div class="relative [transform:translateZ(0)] overflow-hidden rounded-[14px] {{ $imageHeight }}">
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
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/65 via-transparent to-transparent"></div>

        {{-- Status badge — top-left overlay --}}
        <span @class([
            'absolute left-2 top-2 inline-flex items-center gap-1 rounded-full border px-2 py-[3px] text-[0.66rem] font-bold uppercase tracking-[0.05em]',
            'border-cyan-400/70 bg-cyan-950/85 text-cyan-300' => $isUpcoming,
            'border-orange-400/70 bg-orange-950/85 text-orange-300' => ! $isUpcoming,
        ])>
            <span @class([
                'inline-block h-[5px] w-[5px] shrink-0 rounded-full',
                'bg-cyan-400' => $isUpcoming,
                'bg-orange-400' => ! $isUpcoming,
            ])></span>
            {{ $isUpcoming ? 'Upcoming' : 'Past Event' }}
        </span>

        {{-- Event time — bottom-left overlay --}}
        @if(!empty($banner['date']))
            <span class="absolute bottom-2 left-2 inline-flex items-center gap-1 rounded-md bg-black/65 px-2 py-[3px] text-[0.66rem] font-semibold tracking-[0.03em] text-slate-100 backdrop-blur-sm">
                <svg class="h-3 w-3 text-orange-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                {{ $banner['date'] }}@if(!empty($banner['time'])) <span class="text-slate-300">· {{ $banner['time'] }}</span>@endif
            </span>
        @endif
    </div>

    {{-- Title — relative so it stacks above neon-card::before gradient overlay --}}
    <h3 @class([
        'relative mt-[14px] px-1 pb-1 font-bold uppercase leading-snug tracking-[0.04em] text-slate-100 line-clamp-2',
        'text-base' => $size === 'lg',
        'text-[0.8rem]' => $size !== 'lg',
    ])>
        {{ $banner['alt'] ?? 'Gaming Event' }}
    </h3>
</a>
