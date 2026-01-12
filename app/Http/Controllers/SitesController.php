<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SitesController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->input('period', '30d');
        $statusFilter = $request->input('status_filter', 'approved_pending');

        // Get the latest date for this period type
        $latestDate = DB::table('metrics_site')
            ->where('period_type', $period)
            ->where('status_filter', $statusFilter)
            ->max('date');

        // Get all sites with their metrics for the selected period
        $sites = DB::table('metrics_site')
            ->join('sites', 'metrics_site.site_id', '=', 'sites.id')
            ->where('metrics_site.period_type', $period)
            ->where('metrics_site.status_filter', $statusFilter)
            ->where('metrics_site.date', $latestDate)
            ->select(
                'sites.id',
                'sites.name',
                'sites.domain',
                'metrics_site.commission',
                'metrics_site.orders',
                'metrics_site.clicks',
                'metrics_site.visitors',
                'metrics_site.pageviews',
                'metrics_site.rpv',
                'metrics_site.conversion_rate'
            )
            ->orderBy('metrics_site.commission', 'desc')
            ->get();

        // Check for unmapped orders (site_id = NULL)
        $unmappedStats = $this->getUnmappedStats($period, $statusFilter, $latestDate);

        if ($unmappedStats && $unmappedStats->commission > 0) {
            // Add unmapped as a separate "site"
            $sites->push((object) [
                'id' => null,
                'name' => 'Unknown/Unmapped',
                'domain' => '-',
                'commission' => $unmappedStats->commission,
                'orders' => $unmappedStats->orders,
                'clicks' => 0,
                'visitors' => 0,
                'pageviews' => 0,
                'rpv' => 0,
                'conversion_rate' => 0,
            ]);

            // Re-sort by commission
            $sites = $sites->sortByDesc('commission')->values();
        }

        // Get totals from metrics_global (includes orders without site_id)
        $globalMetrics = DB::table('metrics_global')
            ->where('period_type', $period)
            ->where('status_filter', $statusFilter)
            ->where('date', $latestDate)
            ->first();

        $totals = [
            'commission' => $globalMetrics->commission ?? 0,
            'orders' => $globalMetrics->orders ?? 0,
            'clicks' => $globalMetrics->clicks ?? 0,
            'visitors' => $globalMetrics->visitors ?? 0,
            'pageviews' => $globalMetrics->pageviews ?? 0,
        ];

        // Calculate percentage of total for each site
        $sites = $sites->map(function ($site) use ($totals) {
            $site->commission_pct = $totals['commission'] > 0
                ? ($site->commission / $totals['commission']) * 100
                : 0;
            return $site;
        });

        return view('sites.index', compact('sites', 'period', 'statusFilter', 'totals'));
    }

    private function getUnmappedStats(string $period, string $statusFilter, ?string $date)
    {
        if (!$date) {
            return null;
        }

        // Get status list
        $statuses = match($statusFilter) {
            'approved_pending' => ['Geaccepteerd', 'Open'],
            'approved' => ['Geaccepteerd'],
            'rejected' => ['Geweigerd'],
            default => ['Geaccepteerd', 'Open'],
        };

        // For daily, use exact date
        if ($period === 'daily') {
            return DB::table('enriched_orders')
                ->whereNull('site_id')
                ->where('order_date', $date)
                ->whereIn('status', $statuses)
                ->selectRaw('SUM(commission) as commission, COUNT(*) as orders')
                ->first();
        }

        // For rolling periods, calculate date range
        $days = (int) str_replace('d', '', $period);
        $endDate = $date;
        $startDate = date('Y-m-d', strtotime($endDate . " -{$days} days"));

        return DB::table('enriched_orders')
            ->whereNull('site_id')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->whereIn('status', $statuses)
            ->selectRaw('SUM(commission) as commission, COUNT(*) as orders')
            ->first();
    }
}
