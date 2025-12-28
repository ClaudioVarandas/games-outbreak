@props([
    'banners' => [],
])

@php
    $bannerCount = count($banners);
@endphp

@if($bannerCount > 0)
    <section class="mb-12">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach($banners as $banner)
                <div class="{{ $bannerCount === 1 ? 'md:col-span-2' : '' }}">
                    <x-seasonal-banner 
                        :image="$banner['image'] ?? ''"
                        :link="$banner['link'] ?? '#'"
                        :title="$banner['title'] ?? null"
                        :description="$banner['description'] ?? null"
                        :alt="$banner['alt'] ?? null"
                    />
                </div>
            @endforeach
        </div>
    </section>
@endif



