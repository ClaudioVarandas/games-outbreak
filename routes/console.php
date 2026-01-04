<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// === GAME UPDATE SCHEDULES ===

// Tier 1: Upcoming games (0-14 days ahead) - Twice daily
Schedule::command('igdb:upcoming:update --days=3')
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
