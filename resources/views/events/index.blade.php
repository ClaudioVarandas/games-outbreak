@extends('layouts.app')

@section('title', 'Events')

@section('body-class', 'neon-body')

@section('content')
<div class="theme-neon overflow-x-hidden">
    <div class="page-shell py-10">

        {{-- Page heading --}}
        <div class="mb-10">
            <x-homepage.section-heading icon="broadcast" title="Events" />
        </div>

        {{-- Upcoming Events --}}
        <div class="mb-14">
            <h2 class="neon-eyebrow mb-8">Upcoming</h2>

            @if($upcoming->isNotEmpty())
                <div class="relative flex flex-col">
                    {{-- Single continuous line for entire section --}}
                    <div class="absolute left-[10px] top-0 bottom-8 w-px bg-cyan-400/35"></div>
                    <div class="absolute left-[10px] bottom-0 h-8 w-px bg-gradient-to-b from-cyan-400/35 to-transparent"></div>

                    @foreach($upcoming as $event)
                        @php
                            $eventTime = $event->getEventTime();
                            $about = $event->getEventAbout();
                            $detailUrl = route('lists.show', ['type' => 'events', 'slug' => $event->slug]);
                        @endphp

                        <div class="flex gap-5 pb-8 last:pb-0">
                            {{-- Rail: dot only (line is in parent) --}}
                            <div class="relative w-5 shrink-0">
                                <div class="absolute left-1/2 top-6 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full bg-cyan-400 ring-4 ring-cyan-400/20"
                                     style="box-shadow: 0 0 10px var(--neon-cyan)"></div>
                            </div>

                            {{-- Content --}}
                            <div class="flex-1 min-w-0">
                                {{-- Date label --}}
                                @if($eventTime)
                                    <p class="mb-3 text-[0.72rem] font-bold uppercase tracking-[0.08em] text-cyan-400">
                                        {{ $eventTime->format('M d, Y') }}
                                        @if($event->getEventTimezone())
                                            · {{ $eventTime->format('H:i') }} {{ $event->getEventTimezone() }}
                                        @endif
                                    </p>
                                @elseif($event->start_at)
                                    <p class="mb-3 text-[0.72rem] font-bold uppercase tracking-[0.08em] text-cyan-400">
                                        {{ $event->start_at->format('M d, Y') }}
                                    </p>
                                @endif

                                {{-- Event card --}}
                                <a href="{{ $detailUrl }}" class="neon-card group flex flex-col overflow-hidden p-[9px] sm:flex-row sm:items-start sm:gap-4">
                                    {{-- Image --}}
                                    <div class="relative [transform:translateZ(0)] shrink-0 overflow-hidden rounded-[14px] sm:w-64"
                                         style="height:160px">
                                        @if($event->og_image_path)
                                            <img src="{{ asset($event->og_image_path) }}"
                                                 alt="{{ $event->name }}"
                                                 class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                                 loading="lazy"
                                                 onerror="this.src='/images/game-cover-placeholder.svg'">
                                        @else
                                            <div class="h-full w-full bg-gradient-to-br from-orange-500/20 to-violet-500/20"></div>
                                        @endif
                                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
                                        <span class="absolute left-2 top-2 inline-flex items-center gap-1 rounded-full border border-cyan-400/70 bg-cyan-950/85 px-2 py-[3px] text-[0.66rem] font-bold uppercase tracking-[0.05em] text-cyan-300">
                                            <span class="inline-block h-[5px] w-[5px] shrink-0 rounded-full bg-cyan-400"
                                                  style="box-shadow:0 0 6px var(--neon-cyan)"></span>
                                            Upcoming
                                        </span>
                                    </div>

                                    {{-- Content --}}
                                    <div class="relative mt-3 flex flex-1 flex-col justify-between sm:mt-0 sm:py-1">
                                        <div>
                                            <h3 class="text-[0.95rem] font-bold uppercase leading-snug tracking-[0.04em] text-slate-100">
                                                {{ $event->name }}
                                            </h3>
                                            @if($about)
                                                <p class="mt-2 line-clamp-3 text-[0.8rem] leading-relaxed text-slate-400">
                                                    {{ $about }}
                                                </p>
                                            @endif
                                        </div>
                                        <p class="mt-3 inline-flex items-center gap-1 text-[0.75rem] font-semibold uppercase tracking-[0.06em] text-cyan-400">
                                            View Event
                                            <x-heroicon-o-arrow-right class="h-3.5 w-3.5" />
                                        </p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="neon-panel px-6 py-10 text-center text-sm uppercase tracking-[0.08em] text-slate-400">
                    No upcoming events right now.
                </div>
            @endif
        </div>

        {{-- Past Events --}}
        <div>
            <h2 class="neon-eyebrow neon-eyebrow--orange mb-8">Past Events</h2>

            @if($past->isNotEmpty())
                <div class="relative flex flex-col">
                    {{-- Single continuous line for entire section --}}
                    <div class="absolute left-[10px] top-0 bottom-8 w-px bg-orange-400/30"></div>
                    <div class="absolute left-[10px] bottom-0 h-8 w-px bg-gradient-to-b from-orange-400/30 to-transparent"></div>

                    @foreach($past as $event)
                        @php
                            $eventTime = $event->getEventTime();
                            $about = $event->getEventAbout();
                            $detailUrl = route('lists.show', ['type' => 'events', 'slug' => $event->slug]);
                        @endphp

                        <div class="flex gap-5 pb-8 last:pb-0">
                            {{-- Rail: dot only (line is in parent) --}}
                            <div class="relative w-5 shrink-0">
                                <div class="absolute left-1/2 top-6 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full bg-orange-400/70 ring-4 ring-orange-400/10"
                                     style="box-shadow: 0 0 8px var(--neon-orange)"></div>
                            </div>

                            {{-- Content --}}
                            <div class="flex-1 min-w-0">
                                {{-- Date label --}}
                                @if($eventTime)
                                    <p class="mb-3 text-[0.72rem] font-bold uppercase tracking-[0.08em] text-orange-400/80">
                                        {{ $eventTime->format('M d, Y') }}
                                    </p>
                                @elseif($event->start_at)
                                    <p class="mb-3 text-[0.72rem] font-bold uppercase tracking-[0.08em] text-orange-400/80">
                                        {{ $event->start_at->format('M d, Y') }}
                                    </p>
                                @endif

                                {{-- Event card --}}
                                <a href="{{ $detailUrl }}" class="neon-card group flex flex-col overflow-hidden p-[9px] opacity-80 transition-opacity hover:opacity-100 sm:flex-row sm:items-start sm:gap-4">
                                    {{-- Image --}}
                                    <div class="relative [transform:translateZ(0)] shrink-0 overflow-hidden rounded-[14px] sm:w-64"
                                         style="height:160px">
                                        @if($event->og_image_path)
                                            <img src="{{ asset($event->og_image_path) }}"
                                                 alt="{{ $event->name }}"
                                                 class="h-full w-full object-cover grayscale-[30%] transition-all duration-500 group-hover:scale-105 group-hover:grayscale-0"
                                                 loading="lazy"
                                                 onerror="this.src='/images/game-cover-placeholder.svg'">
                                        @else
                                            <div class="h-full w-full bg-gradient-to-br from-slate-700/40 to-slate-800/40"></div>
                                        @endif
                                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
                                        <span class="absolute left-2 top-2 inline-flex items-center gap-1 rounded-full border border-orange-400/70 bg-orange-950/85 px-2 py-[3px] text-[0.66rem] font-bold uppercase tracking-[0.05em] text-orange-300">
                                            <span class="inline-block h-[5px] w-[5px] shrink-0 rounded-full bg-orange-400"
                                                  style="box-shadow:0 0 6px var(--neon-orange)"></span>
                                            Past Event
                                        </span>
                                    </div>

                                    {{-- Content --}}
                                    <div class="relative mt-3 flex flex-1 flex-col justify-between sm:mt-0 sm:py-1">
                                        <div>
                                            <h3 class="text-[0.95rem] font-bold uppercase leading-snug tracking-[0.04em] text-slate-300">
                                                {{ $event->name }}
                                            </h3>
                                            @if($about)
                                                <p class="mt-2 line-clamp-3 text-[0.8rem] leading-relaxed text-slate-500">
                                                    {{ $about }}
                                                </p>
                                            @endif
                                        </div>
                                        <p class="mt-3 inline-flex items-center gap-1 text-[0.75rem] font-semibold uppercase tracking-[0.06em] text-orange-400/80">
                                            View Event
                                            <x-heroicon-o-arrow-right class="h-3.5 w-3.5" />
                                        </p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="neon-panel px-6 py-10 text-center text-sm uppercase tracking-[0.08em] text-slate-400">
                    No past events.
                </div>
            @endif
        </div>

    </div>
</div>
@endsection
