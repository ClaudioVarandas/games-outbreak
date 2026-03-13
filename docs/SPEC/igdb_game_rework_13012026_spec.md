# IGDB Game Rework Specification

**Date:** January 13, 2026
**Version:** 1.0
**Status:** Draft

---

## Table of Contents

1. [Overview](#overview)
2. [Goals](#goals)
3. [Architecture Changes](#architecture-changes)
4. [Database Schema](#database-schema)
5. [Service Layer Changes](#service-layer-changes)
6. [Console Commands](#console-commands)
7. [Job Structure](#job-structure)
8. [Deprecated Code](#deprecated-code)
9. [Implementation Plan](#implementation-plan)
10. [Monitoring & Alerts](#monitoring--alerts)
11. [Testing Strategy](#testing-strategy)

---

## Overview

This specification documents the rework of the IGDB integration to:
1. Remove Steam data enrichment during IGDB sync (performance improvement)
2. Add comprehensive external game source tracking from IGDB
3. Introduce separate data fetching commands per external source (starting with SteamSpy)
4. Establish a scalable architecture for future source integrations

---

## Goals

### Primary Goals
- **Performance**: Eliminate 300ms+ delay per game during IGDB sync caused by Steam API calls
- **Data Richness**: Capture all external game sources from IGDB (Steam, GOG, Epic, PlayStation, Xbox, Nintendo)
- **Separation of Concerns**: Decouple source data fetching from IGDB sync
- **Scalability**: Enable easy addition of new data sources

### Non-Goals (Future Work)
- UI changes for displaying external sources
- Migrating existing `steam_data` to new structure (data not actively used)

---

## Architecture Changes

### Current Flow
```
IGDB Sync → enrichWithSteamData() → Steam API (300ms/game) → Store in steam_data JSON
```

### New Flow
```
IGDB Sync → extractExternalSources() → Store in game_external_sources table
     ↓
Separate Command → SteamSpyService → Queue Jobs → Store in steam_game_data table
```

### Key Decisions
| Decision | Choice | Rationale |
|----------|--------|-----------|
| Source storage | Database table + IGDB sync | Auto-updates when IGDB adds sources |
| Pivot data | Minimal (UID only) | Separate tables per source for enriched data |
| Source-specific tables | Per-source table | Optimized schema per source (steam_game_data, etc.) |
| Steam enrichment | Immediate bypass | Performance improvement, data not actively used |
| Job pattern | Chain pattern | Self-limiting, handles failures gracefully |
| Retry tracking | Columns on pivot | Centralized, no separate table needed |

---

## Database Schema

### New Table: `external_game_sources`
Stores source definitions synced from IGDB's `/external_game_sources` endpoint.

```php
Schema::create('external_game_sources', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('igdb_id')->unique();
    $table->string('name');
    $table->string('slug')->nullable();
    $table->integer('category')->nullable(); // IGDB category type
    $table->timestamps();
});
```

### New Table: `game_external_sources` (Pivot)
Links games to their external source entries.

```php
Schema::create('game_external_sources', function (Blueprint $table) {
    $table->id();
    $table->foreignId('game_id')->constrained()->cascadeOnDelete();
    $table->foreignId('external_game_source_id')->constrained()->cascadeOnDelete();
    $table->string('external_uid'); // The UID on the external platform
    $table->string('external_url')->nullable(); // Direct link if provided by IGDB

    // Sync tracking columns
    $table->string('sync_status')->default('pending'); // pending, synced, failed
    $table->unsignedInteger('retry_count')->default(0);
    $table->timestamp('last_attempted_at')->nullable();
    $table->timestamp('next_retry_at')->nullable();
    $table->timestamp('last_synced_at')->nullable();

    $table->timestamps();

    $table->unique(['game_id', 'external_game_source_id']);
    $table->index('sync_status');
    $table->index('next_retry_at');
});
```

### New Table: `steam_game_data`
Stores SteamSpy data for games with Steam presence.

```php
Schema::create('steam_game_data', function (Blueprint $table) {
    $table->id();
    $table->foreignId('game_id')->constrained()->cascadeOnDelete();
    $table->string('steam_app_id')->index();

    // SteamSpy fields
    $table->string('owners')->nullable(); // Range string (e.g., "1,000,000 .. 2,000,000")
    $table->string('players_forever')->nullable();
    $table->string('players_2weeks')->nullable();
    $table->unsignedInteger('average_forever')->nullable(); // Minutes
    $table->unsignedInteger('average_2weeks')->nullable();
    $table->unsignedInteger('median_forever')->nullable();
    $table->unsignedInteger('median_2weeks')->nullable();
    $table->unsignedInteger('ccu')->nullable(); // Concurrent users
    $table->unsignedInteger('price')->nullable(); // In cents
    $table->integer('score_rank')->nullable();
    $table->string('genre')->nullable();
    $table->json('tags')->nullable(); // SteamSpy tags with vote counts

    $table->timestamps();

    $table->unique('game_id');
});
```

---

## Service Layer Changes

### IgdbService Updates

#### New Method: `extractExternalSources()`
```php
/**
 * Extract external game sources from IGDB response.
 *
 * @param array $igdbGame Raw IGDB game response
 * @return Collection<ExternalSourceData>
 */
public function extractExternalSources(array $igdbGame): Collection
```

**Returns:** Collection of `ExternalSourceData` DTOs

#### Updated IGDB Query Fields
Current:
```
external_games.category, external_games.uid
```

New:
```
external_games.category, external_games.uid, external_games.url, external_games.external_game_source.*
```

### New DTO: `ExternalSourceData`
```php
namespace App\DTOs;

readonly class ExternalSourceData
{
    public function __construct(
        public int $sourceId,      // IGDB external_game_source ID
        public string $sourceName, // Source name (e.g., "Steam")
        public string $externalUid,
        public ?string $externalUrl,
        public ?int $category,
    ) {}
}
```

### New Service: `SteamSpyService`
```php
namespace App\Services;

class SteamSpyService
{
    /**
     * Fetch game details from SteamSpy API.
     */
    public function fetchGameDetails(string $appId): ?array;

    /**
     * Fetch top 100 games by owners.
     */
    public function fetchTop100InTwoWeeks(): array;

    /**
     * Fetch all games (paginated).
     */
    public function fetchAllGames(int $page = 0): array;

    /**
     * Determine if game data is stale based on priority.
     */
    public function isStale(Game $game, GameExternalSource $sourceLink): bool;
}
```

**Rate Limiting:** 250ms delay between requests (~4 req/sec)

**Staleness Logic:**
- High-priority games (update_priority >= 50): 7 days
- Other games: 30 days

---

## Console Commands

### New Command: `igdb:sync-sources`
Syncs external game source definitions from IGDB.

```bash
php artisan igdb:sync-sources
```

**Purpose:** Manual command to populate/update `external_game_sources` table
**Frequency:** Run manually when needed (IGDB rarely adds new sources)

### New Command: `steamspy:sync`
Syncs game data from SteamSpy API via background jobs.

```bash
php artisan steamspy:sync [options]
```

**Options:**
| Option | Description | Default |
|--------|-------------|---------|
| `--threshold={score}` | Minimum update_priority to sync | 0 |
| `--limit={n}` | Maximum games to process | 500 |

**Behavior:**
1. Query games with Steam source in `game_external_sources`
2. Filter by update_priority >= threshold
3. Exclude recently synced (staleness check)
4. Dispatch first job in chain
5. Each job processes one game, dispatches next

### Updated Commands (Full Refactor)
The following commands will be updated to:
- Remove `enrichWithSteamData()` calls
- Add `extractExternalSources()` calls
- Store sources in new pivot table

| Command | File |
|---------|------|
| `igdb:upcoming:update` | `UpdateUpcomingGames.php` |
| `igdb:gamelist:refresh` | `RefreshGameListGames.php` |
| `igdb:update-popular` | `UpdatePopularGames.php` |
| `igdb:update-stale` | `UpdateStaleGames.php` |
| `igdb:update-recently-released` | `UpdateRecentlyReleasedGames.php` |

---

## Job Structure

### Job: `SyncSteamSpyGameData`
Processes a single game and dispatches next job in chain.

```php
namespace App\Jobs;

class SyncSteamSpyGameData implements ShouldQueue
{
    public function __construct(
        public int $gameExternalSourceId,
        public ?int $nextGameExternalSourceId = null,
    ) {}

    public function handle(SteamSpyService $steamSpy): void
    {
        // 1. Fetch source link
        // 2. Call SteamSpy API
        // 3. Update steam_game_data table
        // 4. Update sync_status on pivot
        // 5. Dispatch next job if exists
    }

    public function failed(Throwable $exception): void
    {
        // Update sync_status = 'failed'
        // Increment retry_count
        // Calculate next_retry_at (exponential backoff)
    }
}
```

**Queue:** `low` (non-blocking)

**Exponential Backoff:**
- Retry 1: 1 hour
- Retry 2: 4 hours
- Retry 3: 24 hours
- Retry 4+: 7 days

---

## Deprecated Code

### IgdbService Methods

#### `enrichWithSteamData()` (Lines 186-344)
```php
/**
 * @deprecated Will be removed in future version.
 * Steam data enrichment has been replaced by separate SteamSpy sync.
 * This method now returns the input unchanged.
 *
 * @see SteamSpyService::fetchGameDetails()
 * @see \App\Console\Commands\SteamSpySync
 */
public function enrichWithSteamData(array $igdbGames): array
{
    return $igdbGames; // Bypass - return unchanged
}
```

#### `fetchSteamAppDetails()` (Lines 147-175)
```php
/**
 * @deprecated Will be removed in future version.
 * Direct Steam API access replaced by SteamSpy integration.
 *
 * @see SteamSpyService::fetchGameDetails()
 */
public function fetchSteamAppDetails(array $appIds): array
```

#### `fetchSteamPopularUpcoming()` (Lines 100-142)
```php
/**
 * @deprecated Will be removed in future version.
 * Steam upcoming games now fetched via SteamSpy.
 *
 * @see SteamSpyService::fetchTop100InTwoWeeks()
 */
public function fetchSteamPopularUpcoming(int $count = 50): Collection
```

### Database Columns

#### `games.steam_data` (JSON column)
```
Status: DEPRECATED
Action: Keep column, stop writing to it
Reason: Existing data preserved for historical reference
Future: May be dropped in future migration after confirming no dependencies
```

#### `games.steam_wishlist_count` (Integer column)
```
Status: DEPRECATED
Action: Keep column, stop writing to it
Reason: Wishlist data will come from SteamSpy
```

### Tests

#### `IgdbServiceTest` - Steam Enrichment Tests
The following test methods should be marked as skipped:

```php
// tests/Unit/Services/IgdbServiceTest.php

it('enriches games with steam data', function () {
    // ... existing test
})->skip('Steam enrichment deprecated - see igdb_game_rework_13012026_spec.md');

it('caches steam app details', function () {
    // ...
})->skip('Steam enrichment deprecated');

it('handles missing steam app id gracefully', function () {
    // ...
})->skip('Steam enrichment deprecated');

it('retries steam api on failure', function () {
    // ...
})->skip('Steam enrichment deprecated');
```

---

## Implementation Plan

### Phase 1: Database Schema
1. Create migration for `external_game_sources` table
2. Create migration for `game_external_sources` pivot table
3. Create migration for `steam_game_data` table
4. Create models: `ExternalGameSource`, `GameExternalSource`, `SteamGameData`
5. Add relationships to `Game` model

### Phase 2: IGDB Source Sync
1. Create `igdb:sync-sources` command
2. Run initial sync of external game sources from IGDB

### Phase 3: IgdbService Refactor
1. Create `ExternalSourceData` DTO
2. Add `extractExternalSources()` method to IgdbService
3. Update IGDB query to fetch expanded `external_games` fields
4. Deprecate `enrichWithSteamData()` (return unchanged)
5. Update `FetchGameImages` job to use dual lookup (new table + fallback to steam_data)

### Phase 4: Command Updates
1. Update `UpdateUpcomingGames` - remove enrichment, add source extraction
2. Update `RefreshGameListGames` - same changes
3. Update `UpdatePopularGames` - same changes
4. Update `UpdateStaleGames` - same changes
5. Update `UpdateRecentlyReleasedGames` - same changes
6. Update `Game::refreshFromIgdb()` - same changes

### Phase 5: SteamSpy Integration
1. Create `SteamSpyService` class
2. Create `SyncSteamSpyGameData` job
3. Create `steamspy:sync` command
4. Add queue worker configuration for `low` queue

### Phase 6: Testing & Cleanup
1. Skip deprecated tests in IgdbServiceTest
2. Create new tests for:
   - `ExternalGameSource` model
   - `extractExternalSources()` method
   - `SteamSpyService`
   - `SyncSteamSpyGameData` job
   - `steamspy:sync` command
3. Run full test suite

---

## Monitoring & Alerts

### Queue Monitoring
Monitor the `low` queue for SteamSpy sync jobs:

```php
// Suggested Horizon configuration
'low' => [
    'connection' => 'redis',
    'queue' => ['low'],
    'balance' => 'simple',
    'processes' => 1,
    'tries' => 1, // Job handles own retries
    'timeout' => 60,
],
```

### Metrics to Track
| Metric | Description | Alert Threshold |
|--------|-------------|-----------------|
| `steamspy_sync_jobs_pending` | Jobs waiting in queue | > 1000 |
| `steamspy_sync_failures_1h` | Failed syncs in last hour | > 50 |
| `steamspy_api_errors_1h` | API errors in last hour | > 100 |
| `game_external_sources_failed` | Total records with failed status | > 500 |

### Logging
Add structured logging for sync operations:

```php
Log::channel('steamspy')->info('Game sync completed', [
    'game_id' => $game->id,
    'steam_app_id' => $appId,
    'duration_ms' => $duration,
]);

Log::channel('steamspy')->warning('Game sync failed', [
    'game_id' => $game->id,
    'steam_app_id' => $appId,
    'error' => $exception->getMessage(),
    'retry_count' => $retryCount,
]);
```

### Health Checks
Add health check for sync freshness:

```php
// In scheduled command or health check endpoint
$staleCount = GameExternalSource::query()
    ->where('sync_status', 'failed')
    ->where('retry_count', '>=', 3)
    ->count();

if ($staleCount > 100) {
    // Alert: Many games stuck in failed state
}
```

---

## Testing Strategy

### Unit Tests
- `ExternalSourceData` DTO construction
- `SteamSpyService::isStale()` logic
- Exponential backoff calculation

### Feature Tests
- `igdb:sync-sources` command
- `steamspy:sync` command with various options
- `extractExternalSources()` with various IGDB responses
- Job chain progression
- Failure handling and retry logic

### Integration Tests
- Full flow: IGDB sync → source extraction → SteamSpy sync
- API mocking for IGDB and SteamSpy

---

## Appendix

### IGDB External Game Source Categories (Partial List)
| ID | Name | Notes |
|----|------|-------|
| 1 | Steam | Primary PC source |
| 5 | GOG | DRM-free PC |
| 11 | Xbox Marketplace | Xbox console |
| 13 | Apple App Store | iOS |
| 14 | Google Play | Android |
| 15 | itch.io | Indie PC |
| 26 | Epic Games Store | PC |
| 36 | PlayStation Store | PlayStation console |

*Full list available via `igdb:sync-sources` command*

### SteamSpy API Reference
Base URL: `https://steamspy.com/api.php`

Endpoints:
- `?request=appdetails&appid={appid}` - Single game details
- `?request=top100in2weeks` - Top 100 by players in 2 weeks
- `?request=all&page={page}` - All games (paginated)

Rate Limit: ~4 requests/second recommended

---

*End of Specification*