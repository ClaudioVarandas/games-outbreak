@props([
    'games',
    'platformEnums',
    'currentYear',
    'currentMonth',
])

<section class="neon-section-frame grid gap-5">
    <x-homepage.section-heading
        icon="controller"
        title="This Week's Choices"
        linkText="See monthly"
        :href="route('releases.year.month', [$currentYear, $currentMonth])" />

    @if($games->isNotEmpty())
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
            @foreach($games as $game)
                <x-game-card
                    :game="$game"
                    :displayReleaseDate="$game->pivot->release_date ? \Carbon\Carbon::parse($game->pivot->release_date) : $game->first_release_date"
                    :displayPlatforms="$game->pivot->platforms ?? null"
                    variant="neon"
                    layout="below"
                    aspectRatio="3/4"
                    :platformEnums="$platformEnums" />
            @endforeach
        </div>
    @else
        <div class="neon-panel p-8 text-center text-sm uppercase tracking-[0.08em] text-slate-400">
            No curated releases this week.
        </div>
    @endif
</section>
