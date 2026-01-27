<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EnrichFathomHourly extends Command
{
    protected $signature = 'fathom:enrich-hourly';
    protected $description = 'Enrich hourly site totals from Fathom total_hourly aggregations';

    public function handle(): int
    {
        $this->info('Fetching raw Fathom hourly responses...');

        $rawResponses = DB::table('fathom_api_responses')
            ->where('aggregation_type', 'total_hourly')
            ->get();

        if ($rawResponses->isEmpty()) {
            $this->warn('No raw hourly responses found. Run fathom:import --hourly first.');
            return self::FAILURE;
        }

        $this->info("Found {$rawResponses->count()} raw hourly responses. Processing...");

        $enriched = 0;

        foreach ($rawResponses as $response) {
            $siteId = $this->matchSite($response->site_id);

            if (!$siteId) {
                $this->warn("  ⚠ Site {$response->site_id} not found in sites table. Skipping.");
                continue;
            }

            $data = json_decode($response->response_data, true);
            $items = is_array($data) && isset($data[0]) ? $data : ($data['data'] ?? []);

            foreach ($items as $item) {
                // Parse date from Fathom API (format: "2026-01-26 14:00:00")
                // API returns 'date' field, not 'timestamp'
                $timestamp = $item['date'] ?? $item['timestamp'] ?? null;
                if (!$timestamp) {
                    continue;
                }

                // Parse into date and hour
                $carbon = Carbon::parse($timestamp, 'Europe/Amsterdam');
                $date = $carbon->format('Y-m-d');
                $hour = $carbon->hour;

                // Fathom API fields:
                // 'visits' = unique site visits (what dashboard shows as "People")
                $visitors = $item['visits'] ?? 0;
                $visits = $item['visits'] ?? 0;

                // Upsert: insert or replace if exists
                DB::table('enriched_site_totals_hourly')->updateOrInsert(
                    ['site_id' => $siteId, 'date' => $date, 'hour' => $hour],
                    [
                        'uniques' => $visitors,
                        'visits' => $visits,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                $enriched++;
            }
        }

        $this->newLine();
        $this->info("✓ Enriched {$enriched} hourly site total records");

        return self::SUCCESS;
    }

    private function matchSite(string $fathomSiteId): ?int
    {
        $site = DB::table('sites')->where('fathom_site_id', $fathomSiteId)->first();
        return $site ? $site->id : null;
    }
}
