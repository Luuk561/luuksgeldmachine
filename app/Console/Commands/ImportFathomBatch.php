<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class ImportFathomBatch extends Command
{
    protected $signature = 'fathom:import-all {--days=365 : Number of days to import} {--hourly : Use hourly granularity instead of daily}';
    protected $description = 'Import Fathom data for all sites with rate limiting (10 req/min)';

    public function handle(): int
    {
        $days = $this->option('days');
        $hourly = $this->option('hourly');

        // Get all site IDs
        $sites = DB::table('sites')
            ->whereNotNull('fathom_site_id')
            ->select('id', 'name', 'fathom_site_id')
            ->get();

        $total = $sites->count();
        $granularity = $hourly ? 'hourly' : 'daily';
        $this->info("Importing {$days} days of {$granularity} data for {$total} sites...");
        // Hourly only does 1 request per site (no pathname), daily does 2
        $sleepTime = $hourly ? 6 : 12;
        $this->info("Rate limit: 10 requests/min ({$sleepTime} seconds between sites)");
        $this->newLine();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($sites as $index => $site) {
            // Rate limiting: 10 req/min = 1 request per 6 seconds
            // Daily does 2 requests (total + pathname), hourly does 1
            if ($index > 0) {
                sleep($sleepTime);
            }

            try {
                $args = [
                    'site_id' => $site->fathom_site_id,
                    '--days' => $days
                ];
                if ($hourly) {
                    $args['--hourly'] = true;
                }
                Artisan::call('fathom:import', $args);

                $bar->advance();

            } catch (\Exception $e) {
                $this->newLine();

                // Check if it's a rate limit error
                if (str_contains($e->getMessage(), 'Rate limit exceeded')) {
                    $this->warn("⚠ Rate limit hit for {$site->name}");
                    $this->warn("Waiting 60 seconds before retry...");
                    sleep(60);

                    // Retry once
                    try {
                        Artisan::call('fathom:import', $args);
                        $this->info("✓ Retry successful");
                        $bar->advance();
                    } catch (\Exception $retryError) {
                        $this->error("✗ Retry failed for {$site->name}: " . $retryError->getMessage());
                    }
                } else {
                    $this->error("✗ Failed for {$site->name}: " . $e->getMessage());
                }

                $this->newLine();
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('✓ Import completed');

        // Estimate completion time
        $minutes = ceil(($total * $sleepTime) / 60);
        $this->info("Total time: ~{$minutes} minutes");

        return self::SUCCESS;
    }
}
