<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InitialDataImport extends Command
{
    protected $signature = 'data:initial-import {--force : Force re-import and delete existing data}';
    protected $description = 'Initial historical data import from May 2025 to now (run once during setup)';

    public function handle(): int
    {
        $startDate = '2025-05-01';
        $endDate = now()->format('Y-m-d');

        // Check if data already exists
        $hasData = DB::table('fathom_api_responses')->exists()
                || DB::table('bol_api_responses')->exists()
                || DB::table('enriched_pageviews')->exists();

        if ($hasData && !$this->option('force')) {
            $this->warn('⚠️  Data already exists in database!');
            $this->newLine();

            if (!$this->confirm('This will DELETE all existing data and re-import from scratch. Continue?', false)) {
                $this->info('Import cancelled.');
                return self::SUCCESS;
            }
        }

        $this->info("🚀 Starting initial historical import...");
        $this->info("   Period: {$startDate} → {$endDate}");
        $this->newLine();

        $startTime = now();

        // Step 0: Sync sites from Fathom first!
        $this->info('📋 Step 0/5: Syncing sites from Fathom API...');
        $this->call('sites:sync');

        $siteCount = DB::table('sites')->count();
        if ($siteCount === 0) {
            $this->error('❌ No sites found after sync. Cannot continue.');
            $this->error('   Make sure FATHOM_API_TOKEN is set correctly.');
            return self::FAILURE;
        }

        $this->info("   ✓ Found {$siteCount} sites");
        $this->newLine();

        // Step 1: Clear existing data
        if ($hasData || $this->option('force')) {
            $this->warn('🗑️  Clearing existing data...');

            DB::table('metrics_site')->truncate();
            DB::table('metrics_page')->truncate();
            DB::table('enriched_pageviews')->truncate();
            DB::table('enriched_site_totals')->truncate();
            DB::table('enriched_page_clicks')->truncate();
            DB::table('enriched_click_aggregates')->truncate();
            DB::table('enriched_orders')->truncate();
            DB::table('fathom_api_responses')->truncate();
            DB::table('bol_api_responses')->truncate();

            $this->line('   ✓ All tables cleared');
            $this->newLine();
        }

        // Step 2: Calculate days since May 2024
        $start = \Carbon\Carbon::parse($startDate);
        $end = now();
        $days = $start->diffInDays($end); // Positive number

        $this->info("📥 Step 2/5: Importing {$days} days of raw data from APIs...");
        $this->line("   From: {$startDate} to {$endDate}");
        $this->newLine();

        $this->line("   → Fetching Fathom pageviews (all sites)...");
        $this->call('fathom:import-all', ['--days' => $days]);

        $this->line("   → Setting up Fathom events (one-time)...");
        $this->call('fathom:import-events');

        $this->line("   → Fetching Fathom event data (affiliate clicks)...");
        $this->call('fathom:import-event-data', ['--days' => $days]);

        $this->line("   → Fetching Bol.com orders...");
        $this->call('bol:import-orders', ['--days' => $days]);
        $this->newLine();

        // Step 3: Enrich
        $this->info('🔍 Step 3/5: Enriching data with context...');

        $this->line('   → Enriching pageviews...');
        $this->call('fathom:enrich-pageviews');

        $this->line('   → Enriching site totals...');
        $this->call('fathom:enrich-totals');

        $this->line('   → Enriching events...');
        $this->call('fathom:enrich-events');

        $this->line('   → Enriching orders...');
        $this->call('bol:enrich-orders');
        $this->newLine();

        // Step 4: Aggregate all periods
        $this->info('📊 Step 4/5: Aggregating metrics (all periods)...');

        $periods = ['daily', '7d', '30d', '90d', '365d', 'all-time'];

        foreach ($periods as $period) {
            $this->line("   → Aggregating {$period}...");
            $this->call('metrics:aggregate', ['--period' => $period]);
        }
        $this->newLine();

        // Step 5: Record last sync time
        $this->info('📝 Step 5/5: Recording sync timestamp...');

        $now = now()->toDateTimeString();

        DB::table('sync_metadata')->updateOrInsert(
            ['key' => 'last_full_sync'],
            ['value' => $now, 'updated_at' => now()]
        );

        DB::table('sync_metadata')->updateOrInsert(
            ['key' => 'last_incremental_sync'],
            ['value' => $now, 'updated_at' => now()]
        );

        $this->line('   ✓ Sync timestamps saved');
        $this->newLine();

        $duration = now()->diffInMinutes($startTime);
        $this->info("✅ Initial import completed in ~{$duration} minutes!");
        $this->newLine();
        $this->info("💡 Next steps:");
        $this->line("   • Schedule 'data:sync-incremental' to run every 15 minutes");
        $this->line("   • This will fetch only NEW data since last sync");

        return self::SUCCESS;
    }
}
