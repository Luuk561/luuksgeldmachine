<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebugDataPipeline extends Command
{
    protected $signature = 'debug:data-pipeline';
    protected $description = 'Check data flow through the entire pipeline';

    public function handle(): int
    {
        $this->info('🔍 Checking data pipeline...');
        $this->newLine();

        // Step 1: Raw API Responses
        $this->info('📥 Step 1: Raw API Responses');
        $this->checkTable('fathom_api_responses', ['aggregation_type'], ['start_date', 'end_date']);
        $this->checkTable('bol_api_responses', ['endpoint'], ['start_date', 'end_date']);
        $this->newLine();

        // Step 2: Enriched Data
        $this->info('🔍 Step 2: Enriched Data');
        $this->checkTable('enriched_pageviews', [], ['date']);
        $this->checkTable('enriched_site_totals', [], ['date']);
        $this->checkTable('enriched_click_aggregates', [], ['date']);
        $this->checkTable('enriched_orders', ['status'], ['order_date']);
        $this->newLine();

        // Step 3: Aggregated Metrics
        $this->info('📊 Step 3: Aggregated Metrics');
        $this->checkTable('metrics_global', ['period_type', 'status_filter'], ['date']);
        $this->checkTable('metrics_site', ['period_type', 'status_filter'], ['date']);
        $this->checkTable('metrics_page', ['period_type', 'status_filter'], ['date']);
        $this->newLine();

        // Step 4: Master Data
        $this->info('📋 Step 4: Master Data');
        $sites = DB::table('sites')->count();
        $pages = DB::table('pages')->count();
        $products = DB::table('products')->count();

        $this->line("  • Sites: {$sites}");
        $this->line("  • Pages: {$pages}");
        $this->line("  • Products: {$products}");
        $this->newLine();

        // Step 5: Diagnose issues
        $this->info('💡 Diagnosis:');
        $this->diagnose();

        return self::SUCCESS;
    }

    private function checkTable(string $table, array $groupBy = [], array $dateColumns = []): void
    {
        $count = DB::table($table)->count();

        if ($count === 0) {
            $this->error("  ✗ {$table}: EMPTY (0 records)");
            return;
        }

        $output = "  ✓ {$table}: {$count} records";

        // Add date range if date columns exist
        if (!empty($dateColumns)) {
            foreach ($dateColumns as $col) {
                try {
                    $min = DB::table($table)->min($col);
                    $max = DB::table($table)->max($col);
                    $output .= " | {$col}: {$min} → {$max}";
                } catch (\Exception $e) {
                    // Column doesn't exist, skip
                }
            }
        }

        $this->line($output);

        // Show breakdown by group
        if (!empty($groupBy)) {
            foreach ($groupBy as $col) {
                try {
                    $breakdown = DB::table($table)
                        ->select($col, DB::raw('COUNT(*) as count'))
                        ->groupBy($col)
                        ->get();

                    foreach ($breakdown as $row) {
                        $value = $row->$col ?? 'NULL';
                        $this->line("      • {$col}={$value}: {$row->count}");
                    }
                } catch (\Exception $e) {
                    // Column doesn't exist, skip
                }
            }
        }
    }

    private function diagnose(): void
    {
        $issues = [];

        // Check if raw data exists but enriched doesn't
        $hasRawFathom = DB::table('fathom_api_responses')->count() > 0;
        $hasEnrichedPageviews = DB::table('enriched_pageviews')->count() > 0;
        $hasEnrichedTotals = DB::table('enriched_site_totals')->count() > 0;

        if ($hasRawFathom && !$hasEnrichedPageviews) {
            $issues[] = "Raw Fathom data exists but enriched_pageviews is empty → Run 'fathom:enrich-pageviews'";
        }

        if ($hasRawFathom && !$hasEnrichedTotals) {
            $issues[] = "Raw Fathom data exists but enriched_site_totals is empty → Run 'fathom:enrich-totals'";
        }

        // Check if enriched exists but metrics don't
        $hasEnrichedOrders = DB::table('enriched_orders')->count() > 0;
        $hasMetrics = DB::table('metrics_site')->count() > 0;

        if (($hasEnrichedPageviews || $hasEnrichedOrders) && !$hasMetrics) {
            $issues[] = "Enriched data exists but metrics_site is empty → Run 'metrics:aggregate --period=daily'";
        }

        // Check if sites exist
        $hasSites = DB::table('sites')->count() > 0;
        if (!$hasSites) {
            $issues[] = "No sites found in database → Add sites first!";
        }

        if (empty($issues)) {
            $this->info("  ✓ No issues detected! Pipeline looks healthy.");
        } else {
            foreach ($issues as $issue) {
                $this->warn("  ⚠ {$issue}");
            }
        }
    }
}
