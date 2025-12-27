@props([
    'image' => '',
    'link' => '#',
    'title' => null,
    'description' => null,
    'alt' => null,
])

@php
    $altText = $alt ?? $title ?? 'Banner';
@endphp

<a 
    href="{{ $link }}" 
    class="group relative block w-full aspect-video overflow-hidden rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-[1.02]"
>
    <img 
        src="{{ $image }}" 
        alt="{{ $altText }}"
        class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110"
        onerror="this.src='/images/game-cover-placeholder.svg'"
    >
    
    @if($title || $description)
        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/60 to-transparent p-6">
            @if($title)
                <h3 class="text-2xl font-bold text-white mb-2">{{ $title }}</h3>
            @endif
            @if($description)
                <p class="text-sm text-gray-200">{{ $description }}</p>
            @endif
        </div>
    @endif
    
    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors duration-300"></div>
</a>

