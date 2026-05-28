@props(['game'])

@if($game->metacritic_score || $game->steam_review_percent !== null || $game->igdb_aggregated_rating)
    <div class="flex flex-wrap gap-2">
        <div class="rounded-lg border border-white/10 bg-white/[0.03] px-3 py-1.5">
            <p class="text-[0.6rem] uppercase tracking-wide text-slate-400">Steam</p>
            @if($game->steam_review_percent !== null)
                <p class="text-sm font-bold {{ $game->steamReviewSentiment()?->colorClass() ?? 'text-slate-100' }}">
                    {{ $game->steam_review_percent }}% · {{ $game->steamReviewSentiment()?->label() ?? 'Reviews' }}
                </p>
            @else
                <p class="text-sm font-bold text-slate-600">&mdash;</p>
            @endif
        </div>
        <div class="rounded-lg border border-white/10 bg-white/[0.03] px-3 py-1.5">
            <p class="text-[0.6rem] uppercase tracking-wide text-slate-400">Metacritic</p>
            <p class="text-sm font-bold {{ $game->metacritic_score ? $game->metacriticColorClass() : 'text-slate-600' }}">
                {{ $game->metacritic_score ?: '—' }}
            </p>
        </div>
        <div class="rounded-lg border border-white/10 bg-white/[0.03] px-3 py-1.5">
            <p class="text-[0.6rem] uppercase tracking-wide text-slate-400">IGDB Critics</p>
            <p class="text-sm font-bold {{ $game->igdb_aggregated_rating ? 'text-purple-400' : 'text-slate-600' }}">
                {{ $game->igdb_aggregated_rating ?: '—' }}
            </p>
        </div>
    </div>
@endif
