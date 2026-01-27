<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AggregateMetrics extends Command
{
    protected $signature = 'metrics:aggregate {--period=daily : daily, hourly, 7d, 30d, 90d, 365d, or all-time}';
    protected $description = 'Aggregate metrics from enriched data';

    public function handle(): int
    {
        $period = $this->option('period');
        $this->info("Aggregating {$period} metrics...");

        switch ($period) {
            case 'daily':
                $this->aggregateDaily();
                break;
            case 'hourly':
                $this->aggregateHourly();
                break;
            case '7d':
                $this->aggregateRolling(7);
                break;
            case '30d':
                $this->aggregateRolling(30);
                break;
            case '90d':
                $this->aggregateRolling(90);
                break;
            case '365d':
                $this->aggregateRolling(365);
                break;
            case 'all-time':
                $this->aggregateAllTime();
                break;
            default:
                $this->error("Invalid period. Use: daily, hourly, 7d, 30d, 90d, 365d, or all-time");
                return self::FAILURE;
        }

        $this->info("✓ Metrics aggregated");
        return self::SUCCESS;
    }

    private function aggregateHourly(): void
    {
        $today = now()->format('Y-m-d');
        $statusFilters = $this->getStatusFilters();
        $processed = 0;

        foreach ($statusFilters as $filterKey => $statuses) {
            // Aggregate global hourly metrics for today
            for ($hour = 0; $hour < 24; $hour++) {
                $this->aggregateGlobalForHour($today, $hour, $filterKey, $statuses);
                $processed++;
            }

            // Aggregate per-site hourly metrics for today
            $sites = DB::table('sites')->pluck('id');
            foreach ($sites as $siteId) {
                for ($hour = 0; $hour < 24; $hour++) {
                    $this->aggregateSiteForHour($today, $hour, $siteId, $filterKey, $statuses);
                }
            }
        }

        $this->line("  Processed {$processed} hourly slots × " . \count($statusFilters) . " status filters");
    }

    private function aggregateGlobalForHour(string $date, int $hour, string $statusFilter, array $statuses): void
    {
        // Orders from enriched_orders using order_datetime (ISO 8601 format with 'T')
        $hourStart = "{$date}T" . str_pad($hour, 2, '0', STR_PAD_LEFT) . ":00:00";
        $hourEnd = "{$date}T" . str_pad($hour, 2, '0', STR_PAD_LEFT) . ":59:59";

        $orderStats = DB::table('enriched_orders')
            ->whereBetween('order_datetime', [$hourStart, $hourEnd])
            ->whereIn('status', $statuses)
            ->selectRaw('
                COALESCE(SUM(commission), 0) as total_commission,
                COUNT(*) as total_orders
            ')
            ->first();

        // Manual commissions don't have hour granularity, so we skip them for hourly

        $commission = $orderStats->total_commission ?? 0;
        $orders = $orderStats->total_orders ?? 0;

        // Get visitors from hourly Fathom data
        $siteStats = DB::table('enriched_site_totals_hourly')
            ->where('date', $date)
            ->where('hour', $hour)
            ->selectRaw('
                COALESCE(SUM(uniques), 0) as total_visitors,
                COALESCE(SUM(visits), 0) as total_visits
            ')
            ->first();

        $visitors = $siteStats->total_visitors ?? 0;
        $visits = $siteStats->total_visits ?? 0;

        // Get pageviews from hourly pageview data
        $pageviewStats = DB::table('enriched_pageviews_hourly')
            ->where('date', $date)
            ->where('hour', $hour)
            ->selectRaw('COALESCE(SUM(pageviews), 0) as total_pageviews')
            ->first();

        $pageviews = $pageviewStats->total_pageviews ?? 0;

        // Get clicks from hourly click aggregates
        $clickStats = DB::table('enriched_click_aggregates_hourly')
            ->where('date', $date)
            ->where('hour', $hour)
            ->selectRaw('COALESCE(SUM(clicks), 0) as total_clicks')
            ->first();

        $clicks = $clickStats->total_clicks ?? 0;

        $rpv = $visitors > 0 ? $commission / $visitors : 0;
        $conversionRate = $clicks > 0 ? ($orders / $clicks) * 100 : 0;

        DB::table('metrics_hourly')->updateOrInsert(
            ['date' => $date, 'hour' => $hour, 'site_id' => null, 'status_filter' => $statusFilter],
            [
                'commission' => $commission,
                'orders' => $orders,
                'clicks' => $clicks,
                'pageviews' => $pageviews,
                'visitors' => $visitors,
                'visits' => $visits,
                'rpv' => $rpv,
                'conversion_rate' => $conversionRate,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function aggregateSiteForHour(string $date, int $hour, int $siteId, string $statusFilter, array $statuses): void
    {
        $hourStart = "{$date}T" . str_pad($hour, 2, '0', STR_PAD_LEFT) . ":00:00";
        $hourEnd = "{$date}T" . str_pad($hour, 2, '0', STR_PAD_LEFT) . ":59:59";

        $orderStats = DB::table('enriched_orders')
            ->whereBetween('order_datetime', [$hourStart, $hourEnd])
            ->where('site_id', $siteId)
            ->whereIn('status', $statuses)
            ->selectRaw('
                COALESCE(SUM(commission), 0) as total_commission,
                COUNT(*) as total_orders
            ')
            ->first();

        $commission = $orderStats->total_commission ?? 0;
        $orders = $orderStats->total_orders ?? 0;

        $siteStats = DB::table('enriched_site_totals_hourly')
            ->where('date', $date)
            ->where('hour', $hour)
            ->where('site_id', $siteId)
            ->first();

        $visitors = $siteStats->uniques ?? 0;
        $visits = $siteStats->visits ?? 0;

        // Get pageviews for this site and hour
        $pageviewStats = DB::table('enriched_pageviews_hourly')
            ->where('date', $date)
            ->where('hour', $hour)
            ->where('site_id', $siteId)
            ->selectRaw('COALESCE(SUM(pageviews), 0) as total_pageviews')
            ->first();

        $pageviews = $pageviewStats->total_pageviews ?? 0;

        // Get clicks for this site and hour
        $clickStats = DB::table('enriched_click_aggregates_hourly')
            ->where('date', $date)
            ->where('hour', $hour)
            ->where('site_id', $siteId)
            ->selectRaw('COALESCE(SUM(clicks), 0) as total_clicks')
            ->first();

        $clicks = $clickStats->total_clicks ?? 0;

        $rpv = $visitors > 0 ? $commission / $visitors : 0;
        $conversionRate = 0;

        DB::table('metrics_hourly')->updateOrInsert(
            ['date' => $date, 'hour' => $hour, 'site_id' => $siteId, 'status_filter' => $statusFilter],
            [
                'commission' => $commission,
                'orders' => $orders,
                'clicks' => $clicks,
                'pageviews' => $pageviews,
                'visitors' => $visitors,
                'visits' => $visits,
                'rpv' => $rpv,
                'conversion_rate' => $conversionRate,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function aggregateDaily(): void
    {
        // Get all unique dates from ALL data sources (orders, pageviews, site totals, clicks, manual commissions)
        $orderDates = DB::table('enriched_orders')->selectRaw('DISTINCT order_date as date')->pluck('date');
        $pageviewDates = DB::table('enriched_pageviews')->selectRaw('DISTINCT date')->pluck('date');
        $siteTotalDates = DB::table('enriched_site_totals')->selectRaw('DISTINCT date')->pluck('date');
        $clickDates = DB::table('enriched_click_aggregates')->selectRaw('DISTINCT date')->pluck('date');
        $manualCommissionDates = DB::table('manual_commissions')->selectRaw('DISTINCT date')->pluck('date');

        // Merge and get unique dates
        $dates = $orderDates
            ->merge($pageviewDates)
            ->merge($siteTotalDates)
            ->merge($clickDates)
            ->merge($manualCommissionDates)
            ->unique()
            ->sort()
            ->values();

        $statusFilters = $this->getStatusFilters();

        foreach ($dates as $date) {
            foreach ($statusFilters as $filterKey => $statuses) {
                $this->aggregateGlobalForDate($date, 'daily', $filterKey, $statuses);
                $this->aggregateSitesForDate($date, 'daily', $filterKey, $statuses);
                $this->aggregatePagesForDate($date, 'daily', $filterKey, $statuses);
                $this->aggregateProductsForDate($date, 'daily', $filterKey, $statuses);
            }
        }

        $this->line("  Processed " . $dates->count() . " days × " . count($statusFilters) . " status filters");
    }

    private function getStatusFilters(): array
    {
        return [
            'approved_pending' => ['Geaccepteerd', 'Open'], // Default
            'approved' => ['Geaccepteerd'],
            'rejected' => ['Geweigerd'],
        ];
    }

    private function aggregateRolling(int $days): void
    {
        $endDate = now()->format('Y-m-d');
        $startDate = now()->subDays($days - 1)->format('Y-m-d');
        $periodType = "{$days}d";

        $statusFilters = $this->getStatusFilters();

        foreach ($statusFilters as $filterKey => $statuses) {
            $this->aggregateGlobalForPeriod($startDate, $endDate, $periodType, $filterKey, $statuses);
            $this->aggregateSitesForPeriod($startDate, $endDate, $periodType, $filterKey, $statuses);
            $this->aggregatePagesForPeriod($startDate, $endDate, $periodType, $filterKey, $statuses);
            $this->aggregateProductsForPeriod($startDate, $endDate, $periodType, $filterKey, $statuses);
        }

        $this->line("  Processed {$startDate} to {$endDate} × " . count($statusFilters) . " status filters");
    }

    private function aggregateGlobalForDate(string $date, string $periodType, string $statusFilter, array $statuses): void
    {
        // Orders from Bol
        $orderStats = DB::table('enriched_orders')
            ->where('order_date', $date)
            ->whereIn('status', $statuses)
            ->selectRaw('
                SUM(commission) as total_commission,
                COUNT(*) as total_orders
            ')
            ->first();

        // Add manual commissions
        $manualCommission = DB::table('manual_commissions')
            ->where('date', $date)
            ->whereIn('status', $statuses)
            ->sum('commission');

        $commission = ($orderStats->total_commission ?? 0) + $manualCommission;
        $orders = $orderStats->total_orders ?? 0;

        // Get pageviews from pathname data
        $totalPageviews = DB::table('enriched_pageviews')
            ->where('date', $date)
            ->sum('pageviews');

        // Get visitors and visits from site-level totals (not pathname level to avoid double-counting)
        $siteStats = DB::table('enriched_site_totals')
            ->where('date', $date)
            ->selectRaw('
                SUM(uniques) as total_visitors,
                SUM(visits) as total_visits
            ')
            ->first();

        $totalVisitors = $siteStats->total_visitors ?? 0;
        $totalVisits = $siteStats->total_visits ?? 0;

        // Get affiliate clicks
        $totalClicks = DB::table('enriched_click_aggregates')
            ->where('date', $date)
            ->sum('clicks');

        // Use $commission and $orders already calculated above with manual commissions
        $visitors = $totalVisitors;
        $pageviews = $totalPageviews;
        $visits = $totalVisits;
        $clicks = $totalClicks;

        $rpv = $visitors > 0 ? $commission / $visitors : 0;
        $conversionRate = $clicks > 0 ? ($orders / $clicks) * 100 : 0;

        DB::table('metrics_global')->updateOrInsert(
            ['date' => $date, 'period_type' => $periodType, 'status_filter' => $statusFilter],
            [
                'commission' => $commission,
                'orders' => $orders,
                'clicks' => $clicks,
                'pageviews' => $pageviews,
                'visitors' => $visitors,
                'visits' => $visits,
                'rpv' => $rpv,
                'conversion_rate' => $conversionRate,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function aggregateGlobalForPeriod(string $startDate, string $endDate, string $periodType, string $statusFilter, array $statuses): void
    {
        // Orders from Bol
        $orderStats = DB::table('enriched_orders')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->whereIn('status', $statuses)
            ->selectRaw('
                SUM(commission) as total_commission,
                COUNT(*) as total_orders
            ')
            ->first();

        // Add manual commissions
        $manualCommission = DB::table('manual_commissions')
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', $statuses)
            ->sum('commission');

        $commission = ($orderStats->total_commission ?? 0) + $manualCommission;
        $orders = $orderStats->total_orders ?? 0;

        // Get pageviews from pathname data
        $totalPageviews = DB::table('enriched_pageviews')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('pageviews');

        // Get visitors and visits from site-level totals (not pathname level to avoid double-counting)
        $siteStats = DB::table('enriched_site_totals')
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('
                SUM(uniques) as total_visitors,
                SUM(visits) as total_visits
            ')
            ->first();

        $totalVisitors = $siteStats->total_visitors ?? 0;
        $totalVisits = $siteStats->total_visits ?? 0;

        // Get affiliate clicks
        $totalClicks = DB::table('enriched_click_aggregates')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('clicks');

        // Use $commission and $orders already calculated above with manual commissions
        $visitors = $totalVisitors;
        $pageviews = $totalPageviews;
        $visits = $totalVisits;
        $clicks = $totalClicks;

        $rpv = $visitors > 0 ? $commission / $visitors : 0;
        $conversionRate = $clicks > 0 ? ($orders / $clicks) * 100 : 0;

        DB::table('metrics_global')->updateOrInsert(
            ['date' => $endDate, 'period_type' => $periodType, 'status_filter' => $statusFilter],
            [
                'commission' => $commission,
                'orders' => $orders,
                'clicks' => $clicks,
                'pageviews' => $pageviews,
                'visitors' => $visitors,
                'visits' => $visits,
                'rpv' => $rpv,
                'conversion_rate' => $conversionRate,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function aggregateSitesForDate(string $date, string $periodType, string $statusFilter, array $statuses): void
    {
        $sites = DB::table('sites')->pluck('id');

        foreach ($sites as $siteId) {
            $orderStats = DB::table('enriched_orders')
                ->where('order_date', $date)
                ->where('site_id', $siteId)
                ->whereIn('status', $statuses)
                ->selectRaw('
                    SUM(commission) as total_commission,
                    COUNT(*) as total_orders
                ')
                ->first();

            // Add manual commissions for this site
            $manualCommission = DB::table('manual_commissions')
                ->where('date', $date)
                ->where('site_id', $siteId)
                ->whereIn('status', $statuses)
                ->sum('commission');

            $pageviewStats = DB::table('enriched_pageviews')
                ->where('date', $date)
                ->where('site_id', $siteId)
                ->selectRaw('SUM(pageviews) as total_pageviews')
                ->first();

            $siteStats = DB::table('enriched_site_totals')
                ->where('date', $date)
                ->where('site_id', $siteId)
                ->selectRaw('
                    SUM(uniques) as total_visitors,
                    SUM(visits) as total_visits
                ')
                ->first();

            $clickStats = DB::table('enriched_click_aggregates')
                ->where('date', $date)
                ->where('site_id', $siteId)
                ->selectRaw('SUM(clicks) as total_clicks')
                ->first();

            $commission = ($orderStats->total_commission ?? 0) + $manualCommission;
            $orders = $orderStats->total_orders ?? 0;
            $visitors = $siteStats->total_visitors ?? 0;
            $pageviews = $pageviewStats->total_pageviews ?? 0;
            $visits = $siteStats->total_visits ?? 0;
            $clicks = $clickStats->total_clicks ?? 0;

            $rpv = $visitors > 0 ? $commission / $visitors : 0;
            $conversionRate = $clicks > 0 ? $orders / $clicks : 0;

            DB::table('metrics_site')->updateOrInsert(
                ['site_id' => $siteId, 'date' => $date, 'period_type' => $periodType, 'status_filter' => $statusFilter],
                [
                    'commission' => $commission,
                    'orders' => $orders,
                    'clicks' => $clicks,
                    'pageviews' => $pageviews,
                    'visitors' => $visitors,
                    'visits' => $visits,
                    'rpv' => $rpv,
                    'conversion_rate' => $conversionRate,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function aggregateSitesForPeriod(string $startDate, string $endDate, string $periodType, string $statusFilter, array $statuses): void
    {
        $sites = DB::table('sites')->pluck('id');

        foreach ($sites as $siteId) {
            $orderStats = DB::table('enriched_orders')
                ->whereBetween('order_date', [$startDate, $endDate])
                ->where('site_id', $siteId)
                ->whereIn('status', $statuses)
                ->selectRaw('
                    SUM(commission) as total_commission,
                    COUNT(*) as total_orders
                ')
                ->first();

            // Add manual commissions for this site
            $manualCommission = DB::table('manual_commissions')
                ->whereBetween('date', [$startDate, $endDate])
                ->where('site_id', $siteId)
                ->whereIn('status', $statuses)
                ->sum('commission');

            $pageviewStats = DB::table('enriched_pageviews')
                ->whereBetween('date', [$startDate, $endDate])
                ->where('site_id', $siteId)
                ->selectRaw('SUM(pageviews) as total_pageviews')
                ->first();

            $siteStats = DB::table('enriched_site_totals')
                ->whereBetween('date', [$startDate, $endDate])
                ->where('site_id', $siteId)
                ->selectRaw('
                    SUM(uniques) as total_visitors,
                    SUM(visits) as total_visits
                ')
                ->first();

            $clickStats = DB::table('enriched_click_aggregates')
                ->whereBetween('date', [$startDate, $endDate])
                ->where('site_id', $siteId)
                ->selectRaw('SUM(clicks) as total_clicks')
                ->first();

            $commission = ($orderStats->total_commission ?? 0) + $manualCommission;
            $orders = $orderStats->total_orders ?? 0;
            $visitors = $siteStats->total_visitors ?? 0;
            $pageviews = $pageviewStats->total_pageviews ?? 0;
            $visits = $siteStats->total_visits ?? 0;
            $clicks = $clickStats->total_clicks ?? 0;

            $rpv = $visitors > 0 ? $commission / $visitors : 0;
            $conversionRate = $clicks > 0 ? $orders / $clicks : 0;

            DB::table('metrics_site')->updateOrInsert(
                ['site_id' => $siteId, 'date' => $endDate, 'period_type' => $periodType, 'status_filter' => $statusFilter],
                [
                    'commission' => $commission,
                    'orders' => $orders,
                    'clicks' => $clicks,
                    'pageviews' => $pageviews,
                    'visitors' => $visitors,
                    'visits' => $visits,
                    'rpv' => $rpv,
                    'conversion_rate' => $conversionRate,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function aggregatePagesForDate(string $date, string $periodType, string $statusFilter, array $statuses): void
    {
        // Get all pages with either pageviews OR clicks
        $pagesFromPageviews = DB::table('enriched_pageviews')
            ->where('date', $date)
            ->pluck('page_id');

        $pagesFromClicks = DB::table('enriched_page_clicks')
            ->where('date', $date)
            ->pluck('page_id');

        $pages = $pagesFromPageviews->merge($pagesFromClicks)->unique();

        foreach ($pages as $pageId) {
            $page = DB::table('pages')->find($pageId);
            if (!$page) continue;

            $pageviewStats = DB::table('enriched_pageviews')
                ->where('date', $date)
                ->where('page_id', $pageId)
                ->first();

            $visitors = $pageviewStats->uniques ?? 0;
            $pageviews = $pageviewStats->pageviews ?? 0;
            $visits = $pageviewStats->visits ?? 0;

            $clickStats = DB::table('enriched_page_clicks')
                ->where('date', $date)
                ->where('page_id', $pageId)
                ->first();

            $clicks = $clickStats->clicks ?? 0;

            DB::table('metrics_page')->updateOrInsert(
                ['page_id' => $pageId, 'date' => $date, 'period_type' => $periodType, 'status_filter' => $statusFilter],
                [
                    'site_id' => $page->site_id,
                    'commission' => 0, // TODO: link orders to pages
                    'orders' => 0,
                    'clicks' => $clicks,
                    'pageviews' => $pageviews,
                    'visitors' => $visitors,
                    'visits' => $visits,
                    'rpv' => 0,
                    'conversion_rate' => 0,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function aggregatePagesForPeriod(string $startDate, string $endDate, string $periodType, string $statusFilter, array $statuses): void
    {
        // Get all pages with either pageviews OR clicks in the period
        $pagesFromPageviews = DB::table('enriched_pageviews')
            ->whereBetween('date', [$startDate, $endDate])
            ->distinct()
            ->pluck('page_id');

        $pagesFromClicks = DB::table('enriched_page_clicks')
            ->whereBetween('date', [$startDate, $endDate])
            ->distinct()
            ->pluck('page_id');

        $pages = $pagesFromPageviews->merge($pagesFromClicks)->unique();

        foreach ($pages as $pageId) {
            $page = DB::table('pages')->find($pageId);
            if (!$page) continue;

            $pageviewStats = DB::table('enriched_pageviews')
                ->whereBetween('date', [$startDate, $endDate])
                ->where('page_id', $pageId)
                ->selectRaw('
                    SUM(pageviews) as total_pageviews,
                    SUM(uniques) as total_visitors,
                    SUM(visits) as total_visits
                ')
                ->first();

            $visitors = $pageviewStats->total_visitors ?? 0;
            $pageviews = $pageviewStats->total_pageviews ?? 0;
            $visits = $pageviewStats->total_visits ?? 0;

            $clickStats = DB::table('enriched_page_clicks')
                ->whereBetween('date', [$startDate, $endDate])
                ->where('page_id', $pageId)
                ->selectRaw('SUM(clicks) as total_clicks')
                ->first();

            $clicks = $clickStats->total_clicks ?? 0;

            DB::table('metrics_page')->updateOrInsert(
                ['page_id' => $pageId, 'date' => $endDate, 'period_type' => $periodType, 'status_filter' => $statusFilter],
                [
                    'site_id' => $page->site_id,
                    'commission' => 0,
                    'orders' => 0,
                    'clicks' => $clicks,
                    'pageviews' => $pageviews,
                    'visitors' => $visitors,
                    'visits' => $visits,
                    'rpv' => 0,
                    'conversion_rate' => 0,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function aggregateProductsForDate(string $date, string $periodType, string $statusFilter, array $statuses): void
    {
        $products = DB::table('enriched_orders')
            ->where('order_date', $date)
            ->whereIn('status', $statuses)
            ->whereNotNull('product_id')
            ->distinct()
            ->pluck('product_id');

        foreach ($products as $productId) {
            $stats = DB::table('enriched_orders')
                ->where('order_date', $date)
                ->where('product_id', $productId)
                ->whereIn('status', $statuses)
                ->selectRaw('
                    SUM(commission) as total_commission,
                    COUNT(*) as total_orders,
                    AVG(commission) as avg_commission
                ')
                ->first();

            DB::table('metrics_product')->updateOrInsert(
                ['product_id' => $productId, 'date' => $date, 'period_type' => $periodType, 'status_filter' => $statusFilter],
                [
                    'commission' => $stats->total_commission ?? 0,
                    'orders' => $stats->total_orders ?? 0,
                    'clicks' => 0,
                    'conversion_rate' => 0,
                    'avg_commission_per_order' => $stats->avg_commission ?? 0,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function aggregateProductsForPeriod(string $startDate, string $endDate, string $periodType, string $statusFilter, array $statuses): void
    {
        $products = DB::table('enriched_orders')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->whereIn('status', $statuses)
            ->whereNotNull('product_id')
            ->distinct()
            ->pluck('product_id');

        foreach ($products as $productId) {
            $stats = DB::table('enriched_orders')
                ->whereBetween('order_date', [$startDate, $endDate])
                ->where('product_id', $productId)
                ->whereIn('status', $statuses)
                ->selectRaw('
                    SUM(commission) as total_commission,
                    COUNT(*) as total_orders,
                    AVG(commission) as avg_commission
                ')
                ->first();

            DB::table('metrics_product')->updateOrInsert(
                ['product_id' => $productId, 'date' => $endDate, 'period_type' => $periodType, 'status_filter' => $statusFilter],
                [
                    'commission' => $stats->total_commission ?? 0,
                    'orders' => $stats->total_orders ?? 0,
                    'clicks' => 0,
                    'conversion_rate' => 0,
                    'avg_commission_per_order' => $stats->avg_commission ?? 0,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function aggregateAllTime(): void
    {
        // Get earliest and latest dates from all data sources (including manual commissions)
        $orderDates = DB::table('enriched_orders')->selectRaw('DISTINCT order_date as date')->pluck('date');
        $pageviewDates = DB::table('enriched_pageviews')->selectRaw('DISTINCT date')->pluck('date');
        $siteTotalDates = DB::table('enriched_site_totals')->selectRaw('DISTINCT date')->pluck('date');
        $clickDates = DB::table('enriched_click_aggregates')->selectRaw('DISTINCT date')->pluck('date');
        $manualCommissionDates = DB::table('manual_commissions')->selectRaw('DISTINCT date')->pluck('date');

        $allDates = $orderDates
            ->merge($pageviewDates)
            ->merge($siteTotalDates)
            ->merge($clickDates)
            ->merge($manualCommissionDates)
            ->unique()
            ->sort()
            ->values();

        if ($allDates->isEmpty()) {
            $this->warn('No data found for all-time aggregation');
            return;
        }

        $startDate = $allDates->first();
        $endDate = now()->format('Y-m-d');

        $statusFilters = $this->getStatusFilters();

        foreach ($statusFilters as $filterKey => $statuses) {
            $this->aggregateGlobalForPeriod($startDate, $endDate, 'all-time', $filterKey, $statuses);
            $this->aggregateSitesForPeriod($startDate, $endDate, 'all-time', $filterKey, $statuses);
            $this->aggregatePagesForPeriod($startDate, $endDate, 'all-time', $filterKey, $statuses);
            $this->aggregateProductsForPeriod($startDate, $endDate, 'all-time', $filterKey, $statuses);
        }

        $this->line("  Processed {$startDate} to {$endDate} (all-time) × " . count($statusFilters) . " status filters");
    }
}
