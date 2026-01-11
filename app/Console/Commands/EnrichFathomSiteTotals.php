<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnrichFathomSiteTotals extends Command
{
    protected $signature = 'fathom:enrich-totals';
    protected $description = 'Enrich site-level totals from Fathom total aggregations';

    public function handle(): int
    {
        $this->info('Fetching raw Fathom total responses...');

        $rawResponses = DB::table('fathom_api_responses')
            ->where('aggregation_type', 'total')
            ->get();

        if ($rawResponses->isEmpty()) {
            $this->warn('No raw total responses found. Run fathom:import first.');
            return self::FAILURE;
        }

        $this->info("Found {$rawResponses->count()} raw responses. Processing...");

        $enriched = 0;
        $skipped = 0;

        foreach ($rawResponses as $response) {
            $siteId = $this->matchSite($response->site_id);

            if (!$siteId) {
                $this->warn("  ⚠ Site {$response->site_id} not found in sites table. Skipping.");
                continue;
            }

            $data = json_decode($response->response_data, true);
            $items = is_array($data) && isset($data[0]) ? $data : ($data['data'] ?? []);

            foreach ($items as $item) {
                $date = $item['date'] ?? $response->start_date;
                // Fathom API fields (as per their docs):
                // 'visits' = unique site visits (what dashboard shows as "People")
                // 'uniques' = unique page visits (not used - per pathname metric)
                // 'pageviews' = total pageviews
                $visitors = $item['visits'] ?? 0;  // Unique visitors (People)
                $visits = $item['visits'] ?? 0;  // Same as visitors

                // Upsert: insert or replace if exists (handles overlapping date ranges from multiple imports)
                DB::table('enriched_site_totals')->updateOrInsert(
                    ['site_id' => $siteId, 'date' => $date],
                    [
                        'uniques' => $visitors,  // Store visitor count
                        'visits' => $visits,  // Store visit count (same as visitors for site-level)
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                $enriched++;
            }
        }

        $this->newLine();
        $this->info("✓ Enriched {$enriched} site total records");
        if ($skipped > 0) {
            $this->info("  Skipped {$skipped} existing records");
        }

        return self::SUCCESS;
    }

    private function matchSite(string $fathomSiteId): ?int
    {
        $site = DB::table('sites')->where('fathom_site_id', $fathomSiteId)->first();
        return $site ? $site->id : null;
    }
}
