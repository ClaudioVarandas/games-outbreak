<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Schedule upcoming games update (twice daily at 7 AM and 7 PM)
Schedule::command('igdb:upcoming:update --days=2')
    ->twiceDaily(7, 19)
    ->name('igdb-upcoming-update')
    ->withoutOverlapping()
    ->onOneServer();
