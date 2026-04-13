@props([
    'icon',
    'title',
    'href' => null,
    'linkText' => 'See all',
    'id' => null,
])

<div @if($id) id="{{ $id }}" @endif class="neon-section-heading">
    <h2 class="neon-section-heading__title">
        <span class="neon-section-heading__icon">
            @if($icon === 'controller')
                <x-heroicon-o-fire class="h-5 w-5" />
            @elseif($icon === 'broadcast')
                <x-heroicon-o-signal class="h-5 w-5" />
            @elseif($icon === 'rocket')
                <x-heroicon-o-rocket-launch class="h-5 w-5" />
            @elseif($icon === 'sparkles')
                <x-heroicon-o-sparkles class="h-5 w-5" />
            @elseif($icon === 'newspaper')
                <x-heroicon-o-newspaper class="h-5 w-5" />
            @elseif($icon === 'about')
                <x-heroicon-o-information-circle class="h-5 w-5" />
            @elseif($icon === 'star')
                <x-heroicon-o-star class="h-5 w-5" />
            @elseif($icon === 'video')
                <x-heroicon-o-video-camera class="h-5 w-5" />
            @elseif($icon === 'photo')
                <x-heroicon-o-photo class="h-5 w-5" />
            @endif
        </span>
        <span>{{ $title }}</span>
    </h2>

    @if($href)
        <a href="{{ $href }}" class="neon-link inline-flex items-center gap-1 transition">
            {{ $linkText }}
            <x-heroicon-o-arrow-right class="h-3.5 w-3.5" />
        </a>
    @endif
</div>
