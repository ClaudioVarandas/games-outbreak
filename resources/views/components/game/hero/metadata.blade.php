@props(['game'])

@php
    $parts = collect()
        ->merge($game->genres->pluck('name'))
        ->merge($game->gameModes->pluck('name'))
        ->merge($game->playerPerspectives->pluck('name'))
        ->filter()
        ->take(6);
@endphp

@if($parts->isNotEmpty())
    <p class="text-xs text-slate-400">{{ $parts->join(' · ') }}</p>
@endif
