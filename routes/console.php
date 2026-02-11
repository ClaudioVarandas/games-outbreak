<?php

use Illuminate\Support\Facades\Schedule;

// === GAME UPDATE SCHEDULES ===

// Tier 1: Upcoming games (0-7 days ahead) - Twice daily
Schedule::command('igdb:upcoming:update --days=7')
    ->twiceDaily(4, 16)
    ->name('igdb-upcoming-update')
    ->withoutOverlapping()
    ->onOneServer();

// Tier 2: Recently released games (0-60 days ago) - Weekly on Sundays at 3 AM
Schedule::command('igdb:update-recently-released --days=60 --limit=100')
    ->weeklyOn(0, '3:00')
    ->name('igdb-recently-released-update')
    ->withoutOverlapping()
    ->onOneServer();

// Tier 3: Popular games by view count - Weekly on Wednesdays at 3 AM
Schedule::command('igdb:update-popular --limit=100 --min-views=5')
    ->weeklyOn(3, '3:00')
    ->name('igdb-popular-games-update')
    ->withoutOverlapping()
    ->onOneServer();

// Tier 4: Stale games (90+ days old) - Monthly on 1st at 2 AM
Schedule::command('igdb:update-stale --min-days=90 --batch-size=50')
    ->monthlyOn(1, '2:00')
    ->name('igdb-stale-games-update')
    ->withoutOverlapping()
    ->onOneServer();

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

// === HIGHLIGHTS SYNC SCHEDULE ===

// Sync highlighted games from monthly/indie lists to yearly highlights - Weekly on Mondays at 4 AM
Schedule::command('highlights:sync')
    ->weeklyOn(1, '4:00')
    ->name('highlights-sync')
    ->withoutOverlapping()
    ->onOneServer();
