@props(['game'])

<div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
    {{-- Steam --}}
    <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3">
        <x-game.hero.brand-steam class="h-10 w-10" />
        <div class="min-w-0">
            <p class="text-[0.65rem] uppercase tracking-wide text-slate-400">Steam</p>
            @if($game->steam_review_percent !== null)
                <p class="text-sm font-bold {{ $game->steamReviewSentiment()?->colorClass() ?? 'text-slate-100' }}">
                    {{ $game->steam_review_percent }}% · {{ $game->steamReviewSentiment()?->label() ?? 'Reviews' }}
                </p>
            @else
                <p class="text-lg font-bold leading-none text-slate-600">&mdash;</p>
            @endif
        </div>
    </div>

    {{-- Metacritic --}}
    <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3">
        <x-game.hero.brand-metacritic class="h-10 w-10" />
        <div class="min-w-0">
            <p class="text-[0.65rem] uppercase tracking-wide text-slate-400">Metacritic</p>
            <p class="text-sm font-bold leading-none {{ $game->metacritic_score ? $game->metacriticColorClass() : 'text-slate-600' }}">
                {{ $game->metacritic_score ?: '—' }}
            </p>
        </div>
    </div>
</div>
