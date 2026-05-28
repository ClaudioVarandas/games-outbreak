@props(['game'])

@php
    $anchors = [
        'about' => 'About',
        'scores' => 'Scores',
        'screenshots' => 'Screenshots',
        'release-dates' => 'Release Dates',
        'similar-games' => 'Similar Games',
    ];
@endphp

<div class="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none]">
    @foreach($anchors as $id => $label)
        <a href="#{{ $id }}" class="neon-platform-pill whitespace-nowrap hover:border-cyan-400/40">{{ $label }}</a>
    @endforeach
</div>
