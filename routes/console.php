<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Schedule upcoming games update (daily at 2 AM)
Schedule::command('igdb:upcoming:update --days=2')
    ->twiceDaily('07:00', '19:00')
    ->withoutOverlapping()
    ->onOneServer();
