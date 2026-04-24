@props(['video', 'variant' => 'corner'])

@if ($video->category)
    <span class="neon-category-pill neon-category-pill--{{ $variant }}"
          style="--c: {{ $video->category->color ?? '#b581ff' }}">
        {{ strtoupper($video->category->name) }}
    </span>
@endif
