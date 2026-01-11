<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncAllData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:all {--quick : Skip slow operations like all-time aggregation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all data: import from APIs, enrich, and aggregate metrics';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isQuick = $this->option('quick');
        $startTime = now();

        $this->info('🚀 Starting full data sync...');
        $this->newLine();

        // Step 1: Import raw data from APIs
        $this->info('📥 Step 1/4: Importing raw data from APIs...');
        $this->line('→ Fetching Fathom pageviews...');
        $this->call('fathom:import-pageviews');

        $this->line('→ Fetching Fathom events (affiliate clicks)...');
        $this->call('fathom:import-event-data');

        $this->line('→ Fetching Bol.com orders...');
        $this->call('bol:import-orders');
        $this->newLine();

        // Step 2: Enrich data with context
        $this->info('🔍 Step 2/4: Enriching data with context...');
        $this->line('→ Enriching pageviews with site/page info...');
        $this->call('fathom:enrich-pageviews');

        $this->line('→ Enriching events with site/page info...');
        $this->call('fathom:enrich-events');

        $this->line('→ Enriching orders with product info...');
        $this->call('bol:enrich-orders');
        $this->newLine();

        // Step 3: Aggregate metrics
        $this->info('📊 Step 3/4: Aggregating metrics...');
        if ($isQuick) {
            $this->line('⚡ Quick mode: skipping all-time aggregation');
            $this->call('metrics:aggregate', ['--quick' => true]);
        } else {
            $this->call('metrics:aggregate');
        }
        $this->newLine();

        // Step 4: Cleanup old data (optional)
        $this->info('🧹 Step 4/4: Cleaning up old data...');
        $this->line('→ Removing metrics older than 400 days...');
        $this->call('metrics:cleanup');
        $this->newLine();

        $duration = now()->diffInSeconds($startTime);
        $this->info("✅ Full sync completed in {$duration} seconds!");

        return Command::SUCCESS;
    }
}
