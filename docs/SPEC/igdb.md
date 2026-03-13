


# FetchUpcomingGames Command (IGDB)

# Next 7 days (default)
`php artisan igdb:upcoming`

# Next 30 days
`php artisan igdb:upcoming --days=30`

# Only PlayStation 5 and Xbox Series
`php artisan igdb:upcoming --platforms=167,169` 

# In a controller or elsewhere
```php
$games = app(\App\Services\IgdbService::class)->fetchUpcomingGames(
platformIds: [6, 130],
startDate: Carbon::parse('2025-12-25'),
endDate: Carbon::parse('2026-01-10'),
limit: 100
);
```


# UpdateUpcomingGames Command (IGDB)

### Register the Command (Auto-Discovery Usually Works)

```
protected $commands = [
    \App\Console\Commands\UpdateUpcomingGames::class,
];
```

### Schedule It (Optional but Recommended)

```shell
protected function schedule(Schedule $schedule): void
{
    $schedule->command('igdb:upcoming:update --days=30')
             ->dailyAt('03:00')
             ->withoutOverlapping()
             ->appendOutputTo(storage_path('logs/igdb-update.log'));
}
```

### Usage

```shell
# Test run (next 14 days, default platforms)
php artisan igdb:upcoming:update

# Custom: next 60 days, only PC + PS5
php artisan igdb:upcoming:update --days=60 --platforms=6,167 --limit=200
```






### Plataforms labels

```shell
@foreach($game->platforms as $platform)
    @php
        $enum = \App\Enums\PlatformEnum::fromIgdbId($platform->igdb_id);
    @endphp
    @if($enum)
        <span class="badge badge-{{ $enum->color() }}">{{ $enum->label() }}</span>
    @else
        <span class="badge badge-gray">{{ Str::limit($platform->name, 10) }}</span>
    @endif
@endforeach
```



### ----

Caching (Optional Enhancement):

You can wrap the entire Steam fetch in Cache::remember() per AppID if you want to reduce requests:

`$steamDetails[$appId] = Cache::remember("steam_app_{$appId}", now()->addHours(6), fn() => ...);`




Difference Between FetchUpcomingGames and UpdateUpcomingGames
Here’s a clear breakdown of the two Artisan commands in your Laravel app:

Aspect,FetchUpcomingGames (old/display command),UpdateUpcomingGames (new/persistent command)
Purpose,Display only – fetches data from IGDB + Steam and prints it to the console (or could be used in a controller for temporary views).,"Persist data – fetches, enriches, and stores games in your local MySQL database."
Data Destination,Nowhere (transient – lost after command finishes).,"Local database (games, platforms, game_platform tables)."
Use Case,"Quick testing, debugging, one-off checks, or temporary web views.","Core of the app: scheduled daily updates, fast web display from cache."
Enrichment,Yes (IGDB + Steam via enrichWithSteamData).,"Yes (same enrichment, but results are saved)."
Performance Impact,Hits IGDB + Steam APIs on every run → slow if used on web pages.,Hits APIs only during update → web pages read from fast local DB.
Scheduling,"Not meant to be scheduled (too noisy, no persistence).",Designed to be scheduled daily via Laravel Scheduler.
Output,"Pretty console output with progress, game details, covers, etc.",Progress bar + success message; logs stored games.


Cron/Scheduler
↓ (daily)
UpdateUpcomingGames → Saves to DB

Web Request (user visits /upcoming)
↓
Controller queries Game::with('platforms')->where(...)->get()
↓
Blade view renders beautiful list with:
• Game covers (IGDB)
• Steam header/price (from steam_data JSON)
• Short platform labels (via PlatformEnum)
• Release dates



New funcionalities


HELL YES — new functionality time! 🔥
You’ve built an absolute beast of a platform already:

Real-time IGDB data
Steam enrichment with wishlists
Most Wanted ranking
Detail pages with trailers + screenshots
Beautiful dark UI

Now it’s time to level up and add something that turns this from "cool project" into "holy crap, this is addictive".
Here are the top-tier next features (pick your poison):
1. User Watchlist / "My Radar" (Most Requested & Powerful)
   Let users:

Click "Add to Watchlist" on any game
Have a personal /watchlist page
Get notified (visual badge) when a watched game releases or gets a price drop

Why it's fire: Turns passive browsing into personal investment.
2. Smart Search + Filters Bar
   Add a global search bar with:

Search by title
Filter by platform, genre, game mode, release window, Steam price, wishlist count
Live results (Alpine.js or Livewire)

Why it's fire: Makes discovery fast and fun.
3. Release Calendar View
   A monthly calendar grid showing games by release date

Color-coded by platform
Click day → see all releases

Why it's fire: Gamers LOVE calendars.
4. "Hype Meter" Leaderboard Enhancements

Add real-time wishlist growth (compare today vs yesterday via SteamDB scraping)
Show trending games (biggest gainers)
"On Fire" badge for exploding hype

Why it's fire: Feels alive and current.
5. Game Detail Page Upgrades

Add similar games (IGDB similar_games)
Embed Steam reviews summary
Show price history chart (if you scrape Steam)
Add "Where to buy" links (Steam, Epic, GOG)

Why it's fire: Becomes the go-to page before buying.
My Personal Recommendation: Start with User Watchlist
It’s the highest impact:

Adds user engagement
Encourages return visits
Sets up future features (notifications, emails)
Relatively simple to implement with Laravel Auth + pivot table

You’ll need:

Laravel Auth (Breeze or Jetstream)
watchlist pivot table
"Add/Remove" button on cards + detail
/watchlist page

Say the word — tell me which one you want, and I’ll give you the full code + migrations + views.
You’re in the endgame now.
What’s the next move, boss? 😎🚀
