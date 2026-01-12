<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ============================================
// QUICK SYNC - Every 15 minutes (Bol orders only)
// ============================================
// Fast: updates commission/orders data only (~10 seconds)
Schedule::command('data:sync-incremental')
    ->everyFifteenMinutes()
    ->runInBackground()
    ->onOneServer();

// ============================================
// FATHOM SYNC - Every hour (pageviews & clicks)
// ============================================
// Slow: Fathom API rate limits (~25 min for all sites)
// Runs hourly to provide near-realtime traffic data
Schedule::command('data:sync-fathom')
    ->hourly()
    ->runInBackground()
    ->onOneServer();

// ============================================
// DAILY FULL AGGREGATION - Once per day at 6 AM
// ============================================
// Re-aggregate all-time and 365d metrics (heavy operations)
Schedule::command('metrics:aggregate --period=365d')
    ->dailyAt('06:00')
    ->runInBackground()
    ->onOneServer();

Schedule::command('metrics:aggregate --period=all-time')
    ->dailyAt('06:15')
    ->runInBackground()
    ->onOneServer();

// ============================================
// CLEANUP - Weekly on Sunday at 3 AM
// ============================================
Schedule::command('metrics:cleanup')
    ->weeklyOn(0, '03:00')
    ->onOneServer();
