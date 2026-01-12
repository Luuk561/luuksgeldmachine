<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IncrementalDataSync extends Command
{
    protected $signature = 'data:sync-incremental';
    protected $description = 'Incremental data sync - fetches only NEW data since last sync';

    public function handle(): int
    {
        $startTime = now();

        // Get last sync timestamp
        $lastSync = DB::table('sync_metadata')
            ->where('key', 'last_incremental_sync')
            ->value('value');

        if (!$lastSync) {
            $this->warn('⚠️  No previous sync found. Run data:initial-import first!');
            return self::FAILURE;
        }

        $lastSyncCarbon = \Carbon\Carbon::parse($lastSync);
        $daysSinceLastSync = (int) $lastSyncCarbon->diffInDays(now());

        // If more than 2 days, something is wrong - limit to prevent massive imports
        if ($daysSinceLastSync > 2) {
            $this->warn("⚠️  Last sync was {$daysSinceLastSync} days ago!");
            $this->warn("   Using max 2 days to prevent overload.");
            $daysSinceLastSync = 2;
        }

        // Always fetch at least 1 day to catch today's data
        $daysToFetch = max(1, $daysSinceLastSync);

        $this->info("🔄 Incremental sync started...");
        $this->info("   Last sync: {$lastSync}");
        $this->info("   Fetching: {$daysToFetch} day(s) of new data");
        $this->newLine();

        // Step 1: Import raw data (only recent days)
        $this->info('📥 Step 1/3: Importing recent data from APIs...');

        $this->line("   → Fathom pageviews ({$daysToFetch}d)...");
        $this->call('fathom:import-all', ['--days' => $daysToFetch]);

        $this->line("   → Fathom events ({$daysToFetch}d)...");
        $this->call('fathom:import-event-data', ['--days' => $daysToFetch]);

        $this->line("   → Bol orders ({$daysToFetch}d)...");
        $this->call('bol:import-orders', ['--days' => $daysToFetch]);
        $this->newLine();

        // Step 2: Enrich (only processes new/updated data)
        $this->info('🔍 Step 2/3: Enriching new data...');

        $this->line('   → Enriching pageviews...');
        $this->call('fathom:enrich-pageviews');

        $this->line('   → Enriching site totals...');
        $this->call('fathom:enrich-totals');

        $this->line('   → Enriching events...');
        $this->call('fathom:enrich-events');

        $this->line('   → Enriching orders...');
        $this->call('bol:enrich-orders');
        $this->newLine();

        // Step 3: Re-aggregate (only recent periods need updating)
        $this->info('📊 Step 3/3: Updating aggregated metrics...');

        // Only update rolling periods (daily, 7d, 30d, 90d)
        // Skip all-time and 365d for speed (those run once per day)
        $periods = ['daily', '7d', '30d', '90d'];

        foreach ($periods as $period) {
            $this->line("   → Updating {$period}...");
            $this->call('metrics:aggregate', ['--period' => $period]);
        }
        $this->newLine();

        // Step 4: Update sync timestamp
        DB::table('sync_metadata')->updateOrInsert(
            ['key' => 'last_incremental_sync'],
            ['value' => now()->toDateTimeString(), 'updated_at' => now()]
        );

        $duration = now()->diffInSeconds($startTime);
        $this->info("✅ Incremental sync completed in {$duration} seconds!");

        return self::SUCCESS;
    }
}
