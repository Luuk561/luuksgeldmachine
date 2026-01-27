<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FathomDataSync extends Command
{
    protected $signature = 'data:sync-fathom';
    protected $description = 'Hourly Fathom data sync - pageviews and affiliate clicks (slow due to API rate limits)';

    public function handle(): int
    {
        $startTime = now();

        $this->info('🔄 Fathom hourly sync started...');
        $this->warn('⏱️  This takes ~25 minutes due to Fathom API rate limits');
        $this->newLine();

        // Step 1: Import Fathom data (today only)
        $this->info('📥 Step 1/4: Importing Fathom data...');

        $this->line('   → Pageviews daily (today)...');
        $this->call('fathom:import-all', ['--days' => 1]);

        $this->line('   → Pageviews hourly (today)...');
        $this->call('fathom:import-all', ['--days' => 1, '--hourly' => true]);

        $this->line('   → Events/clicks (today)...');
        $this->call('fathom:import-event-data', ['--days' => 1]);
        $this->newLine();

        // Step 2: Enrich Fathom data
        $this->info('🔍 Step 2/4: Enriching Fathom data...');

        $this->line('   → Enriching pageviews (daily)...');
        $this->call('fathom:enrich-pageviews');

        $this->line('   → Enriching pageviews (hourly)...');
        $this->call('fathom:enrich-pageviews-hourly');

        $this->line('   → Enriching site totals (daily)...');
        $this->call('fathom:enrich-totals');

        $this->line('   → Enriching site totals (hourly)...');
        $this->call('fathom:enrich-hourly');

        $this->line('   → Enriching events...');
        $this->call('fathom:enrich-events');
        $this->newLine();

        // Step 3: Re-aggregate metrics
        $this->info('📊 Step 3/4: Re-aggregating metrics...');

        $periods = ['hourly', 'daily', '7d', '30d', '90d'];

        foreach ($periods as $period) {
            $this->line("   → Updating {$period}...");
            $this->call('metrics:aggregate', ['--period' => $period]);
        }
        $this->newLine();

        // Log to sync_logs
        $duration = now()->diffInSeconds($startTime);
        DB::table('sync_logs')->insert([
            'sync_type' => 'fathom_hourly',
            'started_at' => $startTime,
            'completed_at' => now(),
            'duration_seconds' => $duration,
            'status' => 'success',
            'details' => json_encode(['note' => 'Fathom hourly sync']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $minutes = round($duration / 60, 1);
        $this->info("✅ Fathom sync completed in {$minutes} minutes!");

        return self::SUCCESS;
    }
}
