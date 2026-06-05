@props([
    'banners' => [],
])

@php
    $upcoming = $banners['upcoming'] ?? [];
    $past = array_slice($banners['past'] ?? [], 0, 4);
    $featured = $upcoming[0] ?? null;
    $restUpcoming = array_slice($upcoming, 1, 2);
@endphp

<section id="events" class="neon-section-frame grid gap-5 scroll-mt-32">
    <x-homepage.section-heading icon="broadcast" title="Events" :href="route('events')" linkText="See timeline" />

    @if($featured || count($past) > 0)
        @if($featured)
            {{-- Upcoming row: featured on the left half, the next 2 side by side on the right half --}}
            <div class="grid gap-4 lg:grid-cols-2">
                <x-homepage.event-card :banner="$featured" size="lg" />

                @if(count($restUpcoming) > 0)
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach($restUpcoming as $banner)
                            <x-homepage.event-card :banner="$banner" size="md" />
                        @endforeach
                    </div>
                @endif
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
