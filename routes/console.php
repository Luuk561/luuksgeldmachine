<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ============================================
// AUTOMATED DATA SYNC - Runs every 15 minutes
// ============================================
// Quick sync: imports + enriches + aggregates (skip all-time to save time)
Schedule::command('sync:all --quick')
    ->everyFifteenMinutes()
    ->runInBackground()
    ->onOneServer();

// ============================================
// FULL SYNC - Once per day at 6 AM
// ============================================
// Full sync including all-time aggregation
Schedule::command('sync:all')
    ->dailyAt('06:00')
    ->runInBackground()
    ->onOneServer();

// ============================================
// CLEANUP - Weekly on Sunday at 3 AM
// ============================================
Schedule::command('metrics:cleanup')
    ->weeklyOn(0, '03:00')
    ->onOneServer();
