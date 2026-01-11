<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PagesController extends Controller
{
    public function index()
    {
        $sites = Site::orderBy('name')->get();

        return view('pages.index', compact('sites'));
    }

    public function show(Site $site, Request $request)
    {
        $period = $request->input('period', '30d');

        // Map period to days
        $days = match($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            'all-time' => null,
            default => 30,
        };

        // For all-time, get earliest date from metrics_page
        if ($period === 'all-time') {
            $startDate = DB::table('metrics_page')
                ->where('site_id', $site->id)
                ->min('date') ?? now()->subYear()->toDateString();
        } else {
            $startDate = now()->subDays($days)->toDateString();
        }

        // Get best performing pages per content type
        $topPages = DB::table('metrics_page')
            ->join('pages', 'metrics_page.page_id', '=', 'pages.id')
            ->where('metrics_page.site_id', $site->id)
            ->where('metrics_page.date', '>=', $startDate)
            ->where('metrics_page.period_type', 'daily')
            ->where('metrics_page.status_filter', 'approved_pending')
            ->select(
                'pages.id',
                'pages.title',
                'pages.pathname',
                'pages.content_type',
                DB::raw('SUM(metrics_page.commission) as total_commission'),
                DB::raw('SUM(metrics_page.visitors) as total_visitors'),
                DB::raw('SUM(metrics_page.pageviews) as total_pageviews'),
                DB::raw('SUM(metrics_page.orders) as total_orders'),
                DB::raw('SUM(metrics_page.clicks) as total_clicks'),
                DB::raw('SUM(metrics_page.commission) / NULLIF(SUM(metrics_page.visitors), 0) as rpv'),
                DB::raw('(SUM(metrics_page.orders) / NULLIF(SUM(metrics_page.clicks), 0)) * 100 as conversion_rate'),
                DB::raw('(SUM(metrics_page.clicks) / NULLIF(SUM(metrics_page.pageviews), 0)) * 100 as ctr')
            )
            ->groupBy('pages.id', 'pages.title', 'pages.pathname', 'pages.content_type')
            ->orderByDesc('total_pageviews')
            ->get();

        // Calculate summary metrics
        $summaryMetrics = [
            'total_pages' => $topPages->where('total_pageviews', '>', 0)->count(),
            'total_pageviews' => $topPages->sum('total_pageviews'),
            'total_clicks' => $topPages->sum('total_clicks'),
            'average_ctr' => $topPages->sum('total_pageviews') > 0
                ? ($topPages->sum('total_clicks') / $topPages->sum('total_pageviews')) * 100
                : 0,
            'best_page' => $topPages->sortByDesc('total_pageviews')->first(),
        ];

        // Get top 10 by content type (sorted by pageviews)
        // Filter out category pages - real products have longer pathnames like /producten/product-name
        $topBlogs = $topPages->where('content_type', 'blog')->sortByDesc('total_pageviews')->take(10);
        $topProducts = $topPages
            ->where('content_type', 'product')
            ->filter(fn($page) => $page->pathname !== '/producten' && substr_count($page->pathname, '/') >= 2)
            ->sortByDesc('total_pageviews')
            ->take(10);
        $topReviews = $topPages->where('content_type', 'review')->sortByDesc('total_pageviews')->take(10);

        return view('pages.show', compact('site', 'period', 'topBlogs', 'topProducts', 'topReviews', 'topPages', 'summaryMetrics'));
    }
}
