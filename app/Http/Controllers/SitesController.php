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

        // Calculate totals
        $totals = [
            'commission' => $sites->sum('commission'),
            'orders' => $sites->sum('orders'),
            'clicks' => $sites->sum('clicks'),
            'visitors' => $sites->sum('visitors'),
            'pageviews' => $sites->sum('pageviews'),
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
}
