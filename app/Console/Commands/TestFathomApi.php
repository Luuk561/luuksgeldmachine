<?php

namespace App\Console\Commands;

use App\Services\FathomApiService;
use Illuminate\Console\Command;

class TestFathomApi extends Command
{
    protected $signature = 'fathom:test {site_id? : Fathom site ID} {--days=7 : Number of days to fetch}';
    protected $description = 'Test Fathom API connection and fetch pageview data';

    public function handle(FathomApiService $fathom): int
    {
        $siteId = $this->argument('site_id');

        // If no site ID provided, list all sites
        if (!$siteId) {
            $this->info('Fetching all sites...');
            try {
                $sites = $fathom->getSites();
                $this->info('✓ Found ' . count($sites['data']) . ' sites:');

                foreach ($sites['data'] as $site) {
                    $this->line("  - {$site['id']}: {$site['name']}");
                }

                $this->newLine();
                $this->info('Run: php artisan fathom:test {site_id} to test aggregations');
                return self::SUCCESS;

            } catch (\Exception $e) {
                $this->error('✗ Failed to fetch sites');
                $this->error($e->getMessage());
                return self::FAILURE;
            }
        }

        // Fetch aggregations for specific site
        $days = $this->option('days');
        $dateFrom = now()->subDays($days)->format('Y-m-d 00:00:00');
        $dateTo = now()->format('Y-m-d 23:59:59');

        $this->info("Fetching pageviews for site {$siteId} from {$dateFrom} to {$dateTo}...");

        try {
            // Get total aggregations (no grouping)
            $totalData = $fathom->getPageviewAggregations(
                $siteId,
                $dateFrom,
                $dateTo,
                ['pageviews', 'uniques', 'visits']
            );

            $this->info('✓ Total aggregations:');
            if (!empty($totalData['data'])) {
                $total = $totalData['data'][0];
                $this->line("  Pageviews: " . ($total['pageviews'] ?? 0));
                $this->line("  Unique visitors: " . ($total['uniques'] ?? 0));
                $this->line("  Visits: " . ($total['visits'] ?? 0));
            }

            $this->newLine();

            // Get per-pathname aggregations
            $pathnameData = $fathom->getPageviewAggregations(
                $siteId,
                $dateFrom,
                $dateTo,
                ['pageviews', 'uniques'],
                'pathname',
                'pageviews:desc',
                10
            );

            $this->info('✓ Top 10 pages:');
            foreach ($pathnameData['data'] ?? [] as $page) {
                $this->line("  {$page['pathname']}: {$page['pageviews']} pageviews, {$page['uniques']} uniques");
            }

            $this->newLine();
            $this->info('Full response saved to storage/logs/fathom-test.json');
            file_put_contents(
                storage_path('logs/fathom-test.json'),
                json_encode(['total' => $totalData, 'pages' => $pathnameData], JSON_PRETTY_PRINT)
            );

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Failed to fetch Fathom data');
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
