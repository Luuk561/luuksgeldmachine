<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ============================================
// INCREMENTAL SYNC - Runs every 15 minutes
// ============================================
// Fetches only NEW data since last sync (efficient)
Schedule::command('data:sync-incremental')
    ->everyFifteenMinutes()
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
