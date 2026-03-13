How to Test the IGDB Game Rework

1. Sync External Game Sources from IGDB

First, populate the external_game_sources table with source definitions:

php artisan igdb:sync-sources

Expected output:
- Shows progress bar
- Creates/updates records for sources like Steam (ID 1), GOG (ID 5), Epic (ID 26), etc.
- Displays a table with key sources at the end

Verify in database:
php artisan tinker --execute="echo App\Models\ExternalGameSource::count() . ' sources synced'"

  ---
2. Test IGDB Sync with External Sources

Fetch a single game to verify external sources are captured:

php artisan igdb:upcoming:update --igdb-id=119171 --limit=1

Verify the game has external sources:
php artisan tinker --execute="
\$game = App\Models\Game::where('igdb_id', 119171)->first();
echo 'Game: ' . \$game?->name . PHP_EOL;
echo 'External sources: ' . \$game?->gameExternalSources()->count() . PHP_EOL;
\$game?->gameExternalSources->each(fn(\$s) =>
print('  - ' . \$s->externalGameSource->name . ': ' . \$s->external_uid . PHP_EOL)
);
"

  ---
3. Test SteamSpy Sync

Run the SteamSpy sync for games with Steam sources:

# Sync a limited number of games (dry run to see what would be synced)
php artisan steamspy:sync --limit=5 --threshold=0

Verify SteamSpy data was stored:
php artisan tinker --execute="
\$data = App\Models\SteamGameData::with('game')->first();
if (\$data) {
echo 'Game: ' . \$data->game->name . PHP_EOL;
echo 'Steam AppID: ' . \$data->steam_app_id . PHP_EOL;
echo 'Owners: ' . \$data->owners . PHP_EOL;
echo 'CCU: ' . \$data->ccu . PHP_EOL;
echo 'Price: ' . \$data->price_formatted . PHP_EOL;
} else {
echo 'No SteamSpy data yet. Run: php artisan queue:work --queue=low' . PHP_EOL;
}
"

Note: SteamSpy jobs run on the low queue. Start a worker if needed:
php artisan queue:work --queue=low --once

  ---
4. Test Dual Lookup for Steam AppID

Verify the FetchGameImages job uses dual lookup:

php artisan tinker --execute="
\$game = App\Models\Game::whereHas('gameExternalSources', fn(\$q) =>
\$q->whereHas('externalGameSource', fn(\$q2) => \$q2->where('igdb_id', 1))
)->first();

\$igdb = app(App\Services\IgdbService::class);
\$steamId = \$igdb->getSteamAppIdFromSources(\$game);

echo 'Game: ' . \$game->name . PHP_EOL;
echo 'Steam AppID from sources: ' . \$steamId . PHP_EOL;
echo 'Steam AppID from steam_data (deprecated): ' . (\$game->steam_data['appid'] ?? 'N/A') . PHP_EOL;
"

  ---
5. Test Game Refresh with External Sources

Refresh an existing game and verify sources are synced:

php artisan tinker --execute="
\$game = App\Models\Game::whereNotNull('igdb_id')->first();
\$igdb = app(App\Services\IgdbService::class);

echo 'Before: ' . \$game->gameExternalSources()->count() . ' sources' . PHP_EOL;
\$game->refreshFromIgdb(\$igdb);
\$game->refresh();
echo 'After: ' . \$game->gameExternalSources()->count() . ' sources' . PHP_EOL;
"

  ---
6. Verify Sync Status Tracking

Check the sync status of external source links:

php artisan tinker --execute="
use App\Models\GameExternalSource;

echo 'Pending: ' . GameExternalSource::where('sync_status', 'pending')->count() . PHP_EOL;
echo 'Synced: ' . GameExternalSource::where('sync_status', 'synced')->count() . PHP_EOL;
echo 'Failed: ' . GameExternalSource::where('sync_status', 'failed')->count() . PHP_EOL;
"

  ---
7. Run Automated Tests

# Run all new tests
php artisan test tests/Unit/Services/IgdbServiceTest.php
php artisan test tests/Unit/Services/SteamSpyServiceTest.php

# Run full suite
php artisan test

  ---
Quick Checklist
┌──────────────────────────────┬────────────────────────────────────────┬─────────────────────────────────────┐
│             Test             │                Command                 │              Expected               │
├──────────────────────────────┼────────────────────────────────────────┼─────────────────────────────────────┤
│ Sources synced               │ ExternalGameSource::count()            │ > 30 sources                        │
├──────────────────────────────┼────────────────────────────────────────┼─────────────────────────────────────┤
│ Game has sources             │ $game->gameExternalSources()->count()  │ ≥ 1 for PC games                    │
├──────────────────────────────┼────────────────────────────────────────┼─────────────────────────────────────┤
│ Steam lookup works           │ $igdb->getSteamAppIdFromSources($game) │ Returns Steam AppID                 │
├──────────────────────────────┼────────────────────────────────────────┼─────────────────────────────────────┤
│ SteamSpy data stored         │ SteamGameData::count()                 │ > 0 after sync                      │
├──────────────────────────────┼────────────────────────────────────────┼─────────────────────────────────────┤
│ enrichWithSteamData bypassed │ Check logs                             │ No Steam API calls during IGDB sync │
└──────────────────────────────┴────────────────────────────────────────┴─────────────────────────────────────┘


  ---

Current Scheduled Tasks

The file has 4 tiers of IGDB game updates - upcoming, recently released, popular, and stale games.

New Commands That Need Scheduling

1. igdb:sync-sources - Syncs external source definitions (Steam, GOG, Epic, etc.)
   - These rarely change, so monthly is sufficient
   - Should run before other syncs to ensure lookup table is populated
2. steamspy:sync - Syncs Steam player data for games with Steam links
   - Should run regularly to keep Steam metrics fresh
   - Uses the update_priority field to prioritize important games

Recommended Additions

// === EXTERNAL SOURCES SCHEDULES ===

// Sync external game source definitions (Steam, GOG, Epic, etc.) - Monthly on 1st at 1 AM
Schedule::command('igdb:sync-sources')
->monthlyOn(1, '1:00')
->name('igdb-sync-sources')
->withoutOverlapping()
->onOneServer();

// SteamSpy data sync for games with Steam links - Daily at 5 AM
Schedule::command('steamspy:sync --limit=100 --threshold=50')
->dailyAt('5:00')
->name('steamspy-sync')
->withoutOverlapping()
->onOneServer();

Pre-Deployment Checklist

1. Run migrations - 3 new tables need to be created:
   - external_game_sources
   - game_external_sources
   - steam_game_data
2. Initial sync - Run igdb:sync-sources once before deployment to populate the lookup table
3. Backfill existing games - Existing games need to be re-synced to populate their external source links


-------


All schedules are registered. Here's the production deployment checklist:

1. Migrations (run in order)

php artisan migrate
This will create:
- external_game_sources - lookup table for Steam, GOG, Epic, etc.
- game_external_sources - pivot linking games to external sources
- steam_game_data - SteamSpy player counts, reviews, etc.

2. Initial Data Sync (run once after migrations)

# Populate external source definitions
php artisan igdb:sync-sources

# Re-sync games to populate external source links (pick one approach):
# Option A: Sync upcoming games
php artisan igdb:upcoming:update --days=30

# Option B: Refresh specific game lists
php artisan igdb:gamelist:refresh <game_list_id>

3. Schedule Summary
   ┌───────────────────┬─────────────────────┬─────────────────────────┐
   │      Command      │      Frequency      │         Purpose         │
   ├───────────────────┼─────────────────────┼─────────────────────────┤
   │ igdb:sync-sources │ Monthly (1st, 1 AM) │ Sync source definitions │
   ├───────────────────┼─────────────────────┼─────────────────────────┤
   │ steamspy:sync     │ Daily (5 AM)        │ Sync Steam player data  │
   └───────────────────┴─────────────────────┴─────────────────────────┘
4. Queue Worker

Ensure the queue worker is running for the low queue (SteamSpy jobs use this):
php artisan queue:work --queue=low,default

What else do you need for deployment?

  ----

Environment Variable (optional)

# .env - comma-separated IGDB source IDs
IGDB_ACTIVE_EXTERNAL_SOURCES=1      # Steam only (default)
IGDB_ACTIVE_EXTERNAL_SOURCES=1,5,26 # Steam, GOG, Epic

  ----

New isStale() Logic

isStale() checks (in order):
├── Never synced? → stale ✓
├── Synced BEFORE release AND now released? → stale ✓ (NEW)
├── Released in last 14 days? → stale after 3 days (NEW)
├── High priority (>=50)? → stale after 7 days
└── Low priority? → stale after 30 days

Constants Added

RECENTLY_RELEASED_DAYS = 14      // Window for "recently released"
RECENTLY_RELEASED_STALE_DAYS = 3 // Sync every 3 days for new releases

How It Works
┌─────────────────────────────────────────────┬───────────────────────────────────┐
│                  Scenario                   │             Behavior              │
├─────────────────────────────────────────────┼───────────────────────────────────┤
│ Game synced before release, now released    │ Immediate re-sync                 │
├─────────────────────────────────────────────┼───────────────────────────────────┤
│ Game released 5 days ago, synced 4 days ago │ Re-sync (>3 days)                 │
├─────────────────────────────────────────────┼───────────────────────────────────┤
│ Game released 5 days ago, synced 2 days ago │ Wait (within 3 days)              │
├─────────────────────────────────────────────┼───────────────────────────────────┤
│ Game released 20 days ago                   │ Normal priority rules (7/30 days) │
└─────────────────────────────────────────────┴───────────────────────────────────┘
No schedule changes needed - the existing daily steamspy:sync will automatically pick up games that need re-syncing based on these rules.
