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
        $periods = ['1d' => 1, '7d' => 7, '30d' => 30, '90d' => 90, '365d' => 365, 'all-time' => null];
        $metricsData = [];
        $dailyMetricsData = [];
        $hourlyMetricsData = [];

        foreach ($periods as $periodKey => $days) {
            // Special handling for 1d (hourly data)
            if ($periodKey === '1d') {
                $today = today()->format('Y-m-d');

                // Get hourly metrics for chart (24 hours)
                $hourlyData = DB::table('metrics_hourly')
                    ->where('date', $today)
                    ->whereNull('site_id') // Global metrics
                    ->where('status_filter', $statusFilter)
                    ->orderBy('hour', 'asc')
                    ->get();

                // Fill missing hours with zeros
                $completeHourlyData = collect();
                for ($hour = 0; $hour < 24; $hour++) {
                    $metrics = $hourlyData->firstWhere('hour', $hour);
                    if ($metrics) {
                        $completeHourlyData->push($metrics);
                    } else {
                        // Calculate real-time for current/future hours if no data
                        $completeHourlyData->push((object)[
                            'date' => $today,
                            'hour' => $hour,
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

                $hourlyMetricsData[$periodKey] = $completeHourlyData;

                // Calculate totals for today from hourly data
                $metricsData[$periodKey] = (object)[
                    'commission' => $completeHourlyData->sum('commission'),
                    'orders' => $completeHourlyData->sum('orders'),
                    'clicks' => $completeHourlyData->sum('clicks'),
                    'pageviews' => $completeHourlyData->sum('pageviews'),
                    'visitors' => $completeHourlyData->sum('visitors'),
                    'visits' => $completeHourlyData->sum('visits'),
                    'rpv' => $completeHourlyData->sum('visitors') > 0
                        ? $completeHourlyData->sum('commission') / $completeHourlyData->sum('visitors')
                        : 0,
                    'conversion_rate' => $completeHourlyData->sum('clicks') > 0
                        ? ($completeHourlyData->sum('orders') / $completeHourlyData->sum('clicks')) * 100
                        : 0,
                ];

                continue;
            }

            // Get pre-computed aggregated metrics
            $metricsData[$periodKey] = DB::table('metrics_global')
                ->where('period_type', $periodKey)
                ->where('status_filter', $statusFilter)
                ->orderBy('date', 'desc')
                ->first();

            // Get daily metrics for chart - fill missing days with zeros
            $endDate = today(); // Use today() instead of now() for date-only comparison

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
                        // Check if this is TODAY and might have real-time data
                        if ($dateStr === today()->format('Y-m-d')) {
                            // Calculate today's metrics from enriched data (real-time fallback)
                            $todayMetrics = $this->calculateTodayMetrics($statusFilter);
                            $completeData->push($todayMetrics);
                        } else {
                            // Create empty metrics for past dates
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
                }

                $dailyMetricsData[$periodKey] = $completeData;
            }
        }

        // Top/worst sites per period
        $topSitesData = [];
        $worstSitesData = [];

        foreach (array_keys($periods) as $periodKey) {
            // Special handling for 1d - aggregate from hourly data
            if ($periodKey === '1d') {
                $today = today()->format('Y-m-d');

                // Aggregate hourly data per site for today
                $topSitesData[$periodKey] = DB::table('metrics_hourly')
                    ->join('sites', 'metrics_hourly.site_id', '=', 'sites.id')
                    ->where('metrics_hourly.date', $today)
                    ->whereNotNull('metrics_hourly.site_id')
                    ->where('status_filter', $statusFilter)
                    ->groupBy('metrics_hourly.site_id', 'sites.name', 'sites.domain')
                    ->selectRaw('
                        sites.name,
                        sites.domain,
                        metrics_hourly.site_id,
                        SUM(commission) as commission,
                        SUM(orders) as orders,
                        SUM(visitors) as visitors,
                        SUM(clicks) as clicks,
                        CASE WHEN SUM(visitors) > 0 THEN SUM(commission) / SUM(visitors) ELSE 0 END as rpv
                    ')
                    ->orderBy('commission', 'desc')
                    ->limit(5)
                    ->get();

                $worstSitesData[$periodKey] = DB::table('metrics_hourly')
                    ->join('sites', 'metrics_hourly.site_id', '=', 'sites.id')
                    ->where('metrics_hourly.date', $today)
                    ->whereNotNull('metrics_hourly.site_id')
                    ->where('status_filter', $statusFilter)
                    ->groupBy('metrics_hourly.site_id', 'sites.name', 'sites.domain')
                    ->havingRaw('SUM(visitors) > 0')
                    ->selectRaw('
                        sites.name,
                        sites.domain,
                        metrics_hourly.site_id,
                        SUM(commission) as commission,
                        SUM(orders) as orders,
                        SUM(visitors) as visitors,
                        SUM(clicks) as clicks,
                        CASE WHEN SUM(visitors) > 0 THEN SUM(commission) / SUM(visitors) ELSE 0 END as rpv
                    ')
                    ->orderBy('rpv', 'asc')
                    ->limit(5)
                    ->get();

                continue;
            }

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

        return view('dashboard', compact('metricsData', 'dailyMetricsData', 'hourlyMetricsData', 'topSitesData', 'worstSitesData', 'sites', 'dataFreshness'));
    }

    private function calculateTodayMetrics(string $statusFilter): object
    {
        // Real-time calculation from enriched data for today
        $today = today()->format('Y-m-d');

        // Get commission from enriched orders
        $orderMetrics = DB::table('enriched_orders')
            ->where('order_date', $today)
            ->when($statusFilter === 'approved', fn($q) => $q->where('status', 'Geaccepteerd'))
            ->when($statusFilter === 'rejected', fn($q) => $q->where('status', 'Geweigerd'))
            ->when($statusFilter === 'approved_pending', fn($q) => $q->whereIn('status', ['Geaccepteerd', 'Open']))
            ->selectRaw('
                COALESCE(SUM(commission), 0) as commission,
                COUNT(*) as orders
            ')
            ->first();

        // Get traffic from enriched site totals
        $trafficMetrics = DB::table('enriched_site_totals')
            ->where('date', $today)
            ->selectRaw('
                COALESCE(SUM(visitors), 0) as visitors,
                COALESCE(SUM(pageviews), 0) as pageviews,
                COALESCE(SUM(visits), 0) as visits
            ')
            ->first();

        // Get clicks from enriched click aggregates
        $clicks = DB::table('enriched_click_aggregates')
            ->where('date', $today)
            ->sum('total_clicks') ?? 0;

        // Calculate derived metrics
        $visitors = $trafficMetrics->visitors ?? 0;
        $rpv = $visitors > 0 ? ($orderMetrics->commission / $visitors) : 0;
        $conversionRate = $clicks > 0 ? (($orderMetrics->orders / $clicks) * 100) : 0;

        return (object)[
            'date' => $today,
            'period_type' => 'daily',
            'status_filter' => $statusFilter,
            'commission' => $orderMetrics->commission ?? 0,
            'orders' => $orderMetrics->orders ?? 0,
            'clicks' => $clicks,
            'pageviews' => $trafficMetrics->pageviews ?? 0,
            'visitors' => $visitors,
            'visits' => $trafficMetrics->visits ?? 0,
            'rpv' => $rpv,
            'conversion_rate' => $conversionRate,
        ];
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
