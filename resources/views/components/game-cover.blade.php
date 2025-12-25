@props([
    'game',
    'size' => 'cover_big',
    'alt' => null,
    'class' => 'w-full h-full object-cover',
])

@php
    $coverUrl = $game->cover_image_id
        ? $game->getCoverUrl($size)
        : ($game->steam_data['header_image'] ?? null);
    $altText = $alt ?? $game->name . ' cover';
    $showPlaceholder = !$coverUrl;
@endphp

@if($showPlaceholder)
    <x-game-cover-placeholder :gameName="$game->name" :class="$class" />
@else
    <div class="relative {{ $class }}">
        <img src="{{ $coverUrl }}"
             alt="{{ $altText }}"
             class="w-full h-full object-cover"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
        <x-game-cover-placeholder :gameName="$game->name" class="w-full h-full absolute inset-0" style="display: none;" />
    </div>
@endif

