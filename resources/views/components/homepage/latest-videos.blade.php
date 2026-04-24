@props([
    'featured' => null,
    'videos',
])

@if($featured || $videos->isNotEmpty())
<section class="neon-section-frame grid gap-5">
    <x-homepage.section-heading
        icon="video"
        :title="__('Latest Game Videos')"
        :linkText="__('See all')"
        :href="route('videos.index')" />

    <div class="grid gap-4 @if($featured && $videos->isNotEmpty()) lg:grid-cols-[1.6fr_1fr] @endif">
        @if($featured)
            <x-videos.hero-tile :video="$featured" />
        @endif

        @if($videos->isNotEmpty())
            <div class="grid gap-2.5 self-start">
                @foreach($videos as $v)
                    <x-videos.list-row :video="$v" />
                @endforeach
            </div>
        @endif
    </div>
</section>
@endif
