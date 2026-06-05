@props([
    'banners' => [],
])

@php
    $upcoming = array_slice($banners['upcoming'] ?? [], 0, 3);
    $past = array_slice($banners['past'] ?? [], 0, 4);
    $upcomingCount = count($upcoming);
@endphp

<section id="events" class="neon-section-frame grid gap-5 scroll-mt-32">
    <x-homepage.section-heading icon="broadcast" title="Events" :href="route('events')" linkText="See timeline" />

    @if($upcomingCount > 0 || count($past) > 0)
        @if($upcomingCount === 1)
            {{-- Single upcoming: full width --}}
            <x-homepage.event-card :banner="$upcoming[0]" size="lg" />
        @elseif($upcomingCount === 2)
            {{-- Two upcoming: 50/50 split --}}
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach($upcoming as $banner)
                    <x-homepage.event-card :banner="$banner" size="lg" />
                @endforeach
            </div>
        @elseif($upcomingCount >= 3)
            {{-- Three upcoming: featured (60%) on the left, the next 2 stacked (40%) on the right --}}
            <div class="grid gap-4 lg:grid-cols-5">
                <div class="lg:col-span-3">
                    <x-homepage.event-card :banner="$upcoming[0]" size="lg" />
                </div>

                <div class="grid gap-4 lg:col-span-2">
                    @foreach(array_slice($upcoming, 1, 2) as $banner)
                        <x-homepage.event-card :banner="$banner" size="md" />
                    @endforeach
                </div>
            </div>
        @endif

        @if(count($past) > 0)
            {{-- Past events row --}}
            <div class="grid gap-3">
                <h3 class="text-[0.7rem] font-bold uppercase tracking-[0.12em] text-slate-400">Past events</h3>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach($past as $banner)
                        <x-homepage.event-card :banner="$banner" size="sm" />
                    @endforeach
                </div>
            </div>
        @endif
    @else
        <div class="neon-panel p-8 text-center text-sm uppercase tracking-[0.08em] text-slate-400">
            No active events right now.
        </div>
    @endif
</section>
