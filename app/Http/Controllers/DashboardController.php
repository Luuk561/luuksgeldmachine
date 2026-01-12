<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Map checkbox states to status_filter key
        $statusFilter = $this->getStatusFilterKey($request);

        // Prepare metrics for all periods
        $periods = ['7d' => 7, '30d' => 30, '90d' => 90, '365d' => 365, 'all-time' => null];
        $metricsData = [];
        $dailyMetricsData = [];

        foreach ($periods as $periodKey => $days) {
            // Get pre-computed aggregated metrics
            $metricsData[$periodKey] = DB::table('metrics_global')
                ->where('period_type', $periodKey)
                ->where('status_filter', $statusFilter)
                ->orderBy('date', 'desc')
                ->first();

            // Get daily metrics for chart - fill missing days with zeros
            $endDate = now();

            // For all-time, get the earliest date from any data source
            if ($periodKey === 'all-time') {
                $earliestOrder = DB::table('enriched_orders')->min('order_date');
                $earliestPageview = DB::table('enriched_pageviews')->min('date');
                $earliestSiteTotal = DB::table('enriched_site_totals')->min('date');
                $earliestClick = DB::table('enriched_click_aggregates')->min('date');

                $dates = collect([$earliestOrder, $earliestPageview, $earliestSiteTotal, $earliestClick])
                    ->filter()
                    ->sort()
                    ->values();

                $startDate = $dates->first() ? now()->parse($dates->first()) : now()->subDays(365);
            } else {
                $startDate = now()->subDays($days - 1);
            }

            // Get actual metrics data for chart
            $dailyData = DB::table('metrics_global')
                ->where('period_type', 'daily')
                ->where('status_filter', $statusFilter)
                ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->orderBy('date', 'asc')
                ->get();

            // For all-time: only use actual data, no gap filling
            if ($periodKey === 'all-time') {
                $dailyMetricsData[$periodKey] = $dailyData;
            } else {
                // For fixed periods (7d, 30d, etc.): fill gaps with zeros
                $metricsMap = $dailyData->keyBy('date');
                $completeData = collect();

                for ($date = clone $startDate; $date <= $endDate; $date->addDay()) {
                    $dateStr = $date->format('Y-m-d');
                    $metrics = $metricsMap->get($dateStr);

                    if ($metrics) {
                        $completeData->push($metrics);
                    } else {
                        // Create empty metrics for this date
                        $completeData->push((object)[
                            'date' => $dateStr,
                            'period_type' => 'daily',
                            'status_filter' => $statusFilter,
                            'commission' => 0,
                            'orders' => 0,
                            'clicks' => 0,
                            'pageviews' => 0,
                            'visitors' => 0,
                            'visits' => 0,
                            'rpv' => 0,
                            'conversion_rate' => 0,
                        ]);
                    }
                }

                $dailyMetricsData[$periodKey] = $completeData;
            }
        }

        // Top/worst sites per period
        $topSitesData = [];
        $worstSitesData = [];

        foreach (array_keys($periods) as $periodKey) {
            // Get the latest date for this period type
            $latestDate = DB::table('metrics_site')
                ->where('period_type', $periodKey)
                ->where('status_filter', $statusFilter)
                ->max('date');

            $topSitesData[$periodKey] = DB::table('metrics_site')
                ->join('sites', 'metrics_site.site_id', '=', 'sites.id')
                ->where('period_type', $periodKey)
                ->where('status_filter', $statusFilter)
                ->where('metrics_site.date', $latestDate)
                ->orderBy('commission', 'desc')
                ->limit(5)
                ->select('sites.name', 'sites.domain', 'metrics_site.*')
                ->get();

            $worstSitesData[$periodKey] = DB::table('metrics_site')
                ->join('sites', 'metrics_site.site_id', '=', 'sites.id')
                ->where('period_type', $periodKey)
                ->where('status_filter', $statusFilter)
                ->where('metrics_site.date', $latestDate)
                ->where('visitors', '>', 0)
                ->orderBy('rpv', 'asc')
                ->limit(5)
                ->select('sites.name', 'sites.domain', 'metrics_site.*')
                ->get();
        }

        // Get all sites for the manual commission dropdown
        $sites = DB::table('sites')
            ->orderBy('name')
            ->get(['id', 'name']);

        // Get data freshness info
        $lastSync = DB::table('sync_metadata')
            ->where('key', 'last_incremental_sync')
            ->value('value');

        $lastSyncLog = DB::table('sync_logs')
            ->whereIn('status', ['success', 'failed'])
            ->orderBy('completed_at', 'desc')
            ->first();

        $dataFreshness = [
            'last_sync' => $lastSync,
            'last_sync_carbon' => $lastSync ? \Carbon\Carbon::parse($lastSync) : null,
            'minutes_ago' => $lastSync ? now()->diffInMinutes(\Carbon\Carbon::parse($lastSync)) : null,
            'last_log' => $lastSyncLog,
        ];

        return view('dashboard', compact('metricsData', 'dailyMetricsData', 'topSitesData', 'worstSitesData', 'sites', 'dataFreshness'));
    }

    private function getStatusFilterKey(Request $request): string
    {
        $statuses = $request->input('statuses', ['Geaccepteerd', 'Open']);
        sort($statuses);

        // Map combinations to filter keys
        if ($statuses === ['Geaccepteerd', 'Open']) {
            return 'approved_pending';
        } elseif ($statuses === ['Geaccepteerd']) {
            return 'approved';
        } elseif ($statuses === ['Geweigerd']) {
            return 'rejected';
        }

        // Default fallback
        return 'approved_pending';
    }
}
