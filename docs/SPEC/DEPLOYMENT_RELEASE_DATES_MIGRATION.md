# Release Dates Migration - Production Deployment Guide

## Overview

This guide covers the migration of `release_dates` from a JSON field to a normalized `game_release_dates` table.

**Status**: ✅ Code ready for deployment
**Data Migration**: ⚠️ Requires manual execution in production
**Rollback**: ✅ Supported (see section below)

---

## Pre-Deployment Checklist

- [ ] Review all code changes
- [ ] Ensure `release_date_statuses` table exists and is populated
- [ ] Database backup created
- [ ] Estimated downtime communicated (if any)
- [ ] Test on staging environment first

---

## Deployment Steps

### Step 1: Deploy Code & Run Structure Migrations

```bash
# Pull latest code
git pull origin main

# Install dependencies (if needed)
composer install --no-dev --optimize-autoloader

# Run migrations (structure only - data migration skipped in production)
php artisan migrate

# Clear caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**What happens:**
- ✅ Creates `game_release_dates` table
- ✅ Adds indexes for performance
- ⏭️ **Skips** data migration (safe - uses command instead)
- ⏭️ **Skips** dropping `release_dates` column (safe - keeps backup)

---

### Step 2: Test Migration Command (DRY RUN)

**IMPORTANT**: Always test with `--dry-run` first!

```bash
php artisan game:migrate-release-dates --dry-run --chunk=100
```

**Expected output:**
```
Release Dates Migration Summary:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Games with JSON release_dates: 2204
Existing records in game_release_dates: 0
Chunk size: 100
Mode: DRY RUN (no changes)

Starting migration...
[████████████████████████████████] 100%

Migration completed!
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Games processed: 2204
Release dates created: 4106
Games skipped (invalid data): 0
Errors: 0

⚠️  This was a DRY RUN - no changes were made.
    Run without --dry-run to perform the actual migration.
```

---

### Step 3: Execute Data Migration

Once dry-run looks good:

```bash
php artisan game:migrate-release-dates --chunk=100
```

**Options:**
- `--chunk=100` - Process 100 games at a time (adjust based on server resources)
- `--force` - Skip confirmation prompt (useful for automation)
- `--dry-run` - Test without making changes

**Performance Notes:**
- Processes games in chunks to avoid memory issues
- Progress bar shows real-time status
- Errors logged to `storage/logs/laravel.log`
- Safe to run multiple times (skips already-migrated data)

**Monitoring:**
```bash
# Watch the migration in real-time
tail -f storage/logs/laravel.log
```

---

### Step 4: Verify Migration

```bash
php artisan tinker
```

```php
// Check counts
echo "Games: " . \App\Models\Game::count() . "\n";
echo "Release dates: " . \App\Models\GameReleaseDate::count() . "\n";

// Test a random game
$game = \App\Models\Game::with(['releaseDates.platform', 'releaseDates.status'])->inRandomOrder()->first();
echo "Game: {$game->name}\n";
echo "Release dates: {$game->releaseDates->count()}\n";
$game->releaseDates->each(fn($rd) => dump([
    'date' => $rd->formatted_date,
    'platform' => $rd->platform?->name,
    'status' => $rd->status?->name
]));
```

---

### Step 5: Test Application

1. **View a game detail page** - Check release dates display correctly
2. **Run IGDB sync** - Ensure new games sync properly
3. **Check logs** - Verify no errors

```bash
# Test upcoming games update
php artisan games:update-upcoming --days=30 --limit=10
```

---

### Step 6: Drop Old JSON Column (OPTIONAL)

⚠️ **Only after confirming everything works for at least 24-48 hours!**

The `release_dates` JSON column is kept as a safety backup. To remove it:

```bash
# The migration will safety-check before dropping
php artisan migrate
```

Or manually:

```sql
ALTER TABLE games DROP COLUMN release_dates;
```

---

## Rollback Plan

If issues occur, you can rollback:

### Option A: Rollback Migrations (Full Revert)

```bash
# Rollback last 3 migrations
php artisan migrate:rollback --step=3
```

This will:
1. Restore `release_dates` JSON column
2. Clear `game_release_dates` table
3. Drop `game_release_dates` table

### Option B: Keep Table, Restore JSON (Safer)

If you want to keep the table but restore JSON functionality:

```bash
# Rollback only the drop-column migration
php artisan migrate:rollback --step=1
```

Then update code to use JSON field again (git revert).

---

## Troubleshooting

### Migration Command Fails

**Check:**
1. `platforms` table is populated
2. `release_date_statuses` table is populated
3. Sufficient database connections available
4. Check `storage/logs/laravel.log` for specific errors

**Solutions:**
- Reduce chunk size: `--chunk=50`
- Re-run (safe - skips existing data)

### Views Show No Release Dates

**Check:**
1. Eager loading is active: `Game::with(['releaseDates.platform', 'releaseDates.status'])`
2. Platform data exists
3. Browser cache cleared

### IGDB Sync Creates JSON Instead of Records

**Check:**
- Code deployment is complete
- Caches cleared (`php artisan config:cache`)
- `Game::syncReleaseDates()` is being called

---

## Database Schema Reference

### Before Migration
```sql
games
├── id
├── release_dates (JSON) -- Array of release date objects
└── ...
```

### After Migration
```sql
games
├── id
└── ...

game_release_dates
├── id
├── game_id (FK → games.id)
├── platform_id (FK → platforms.id)
├── status_id (FK → release_date_statuses.id)
├── igdb_release_date_id (IGDB tracking)
├── date (timestamp)
├── year, month, day
├── region
├── human_readable
├── is_manual (bool - for user overrides)
└── timestamps
```

**Indexes:**
- `game_id`, `platform_id`, `date`, `(game_id, platform_id)`

---

## Performance Impact

**Migration Time Estimate:**
- ~2,000 games with ~4,000 release dates
- Chunk size 100: ~20-30 seconds
- Chunk size 50: ~40-60 seconds

**Database Size:**
- Old: JSON field ~500KB - 2MB
- New: Normalized table ~200-400KB (more efficient)

**Query Performance:**
- ✅ Better: Indexed queries on date/platform
- ✅ Better: No JSON parsing overhead
- ✅ Better: Proper foreign key constraints

---

## Post-Migration Benefits

✅ **CRUD Operations** - Individual release dates can be edited via UI
✅ **Better Performance** - Indexed queries instead of JSON scanning
✅ **Data Integrity** - Foreign key constraints prevent orphaned data
✅ **Manual Overrides** - Users can add/edit dates (preserved during sync)
✅ **Future-Proof** - Ready for release date management UI

---

## Need Help?

- Check logs: `storage/logs/laravel.log`
- Run dry-run to diagnose: `php artisan game:migrate-release-dates --dry-run`
- Test locally first with production database snapshot
