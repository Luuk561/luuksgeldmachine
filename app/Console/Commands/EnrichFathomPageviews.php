<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnrichFathomPageviews extends Command
{
    protected $signature = 'fathom:enrich-pageviews';
    protected $description = 'Enrich Fathom pageviews from raw API responses';

    public function handle(): int
    {
        $this->info('Fetching raw Fathom responses...');

        $rawResponses = DB::table('fathom_api_responses')
            ->where('aggregation_type', 'pathname')
            ->get();

        if ($rawResponses->isEmpty()) {
            $this->warn('No raw pathname responses found. Run fathom:import first.');
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

            // Check if data is directly an array or nested under 'data' key
            $items = is_array($data) && isset($data[0]) ? $data : ($data['data'] ?? []);

            foreach ($items as $item) {
                $pathname = $item['pathname'] ?? null;
                $pageviews = $item['pageviews'] ?? 0;
                $uniques = $item['uniques'] ?? 0;
                $visits = $item['visits'] ?? 0;
                $date = $item['date'] ?? $response->start_date; // Use date from item if available

                if (!$pathname) {
                    continue;
                }

                // Match or create page
                $pageId = $this->matchOrCreatePage($siteId, $pathname);

                // Upsert: insert or replace if exists (handles overlapping date ranges from multiple imports)
                DB::table('enriched_pageviews')->updateOrInsert(
                    ['site_id' => $siteId, 'page_id' => $pageId, 'date' => $date],
                    [
                        'pageviews' => $pageviews,
                        'uniques' => $uniques,
                        'visits' => $visits,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                $enriched++;
            }
        }

        $this->newLine();
        $this->info("✓ Enriched {$enriched} pageview records");
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

    private function matchOrCreatePage(int $siteId, string $pathname): int
    {
        // Try to find existing page
        $page = DB::table('pages')
            ->where('site_id', $siteId)
            ->where('pathname', $pathname)
            ->first();

        if ($page) {
            return $page->id;
        }

        // Create new page
        $site = DB::table('sites')->find($siteId);
        $url = "https://{$site->domain}{$pathname}";

        return DB::table('pages')->insertGetId([
            'site_id' => $siteId,
            'url' => $url,
            'pathname' => $pathname,
            'content_type' => $this->guessContentType($pathname),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function guessContentType(string $pathname): ?string
    {
        if ($pathname === '/') {
            return 'home';
        }

        if (str_contains($pathname, '/blog')) {
            return 'blog';
        }

        if (str_contains($pathname, '/review')) {
            return 'review';
        }

        if (str_contains($pathname, '/product')) {
            return 'product';
        }

        if (str_contains($pathname, '/top-') || str_contains($pathname, '/beste-')) {
            return 'category';
        }

        return null;
    }
}
