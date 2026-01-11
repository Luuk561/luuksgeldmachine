<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOldMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'metrics:cleanup {--days=400 : Number of days to keep}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove old metrics data to keep database clean';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        $cutoffDate = now()->subDays($days)->format('Y-m-d');

        $this->info("🧹 Cleaning up metrics older than {$days} days (before {$cutoffDate})...");

        // Only delete daily metrics, keep rolled-up periods (7d, 30d, etc.)
        $tables = ['metrics_global', 'metrics_site', 'metrics_page', 'metrics_product'];
        $totalDeleted = 0;

        foreach ($tables as $table) {
            $deleted = DB::table($table)
                ->where('period_type', 'daily')
                ->where('date', '<', $cutoffDate)
                ->delete();

            if ($deleted > 0) {
                $this->line("  → Deleted {$deleted} old records from {$table}");
                $totalDeleted += $deleted;
            }
        }

        if ($totalDeleted > 0) {
            $this->info("✅ Cleanup complete! Removed {$totalDeleted} old daily records.");
        } else {
            $this->info("✅ No old data to clean up.");
        }

        return Command::SUCCESS;
    }
}
