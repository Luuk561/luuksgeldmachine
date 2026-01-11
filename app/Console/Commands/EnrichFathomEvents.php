<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnrichFathomEvents extends Command
{
    protected $signature = 'fathom:enrich-events';
    protected $description = 'Enrich affiliate click events from raw API responses';

    public function handle(): int
    {
        $this->info('Fetching raw event responses...');

        // Get only the LATEST response per event (longest period = most complete data)
        // This handles overlapping imports (e.g. 7d then 30d)
        $responses = DB::select("
            SELECT far.*
            FROM fathom_api_responses far
            INNER JOIN (
                SELECT
                    json_extract(response_data, '$.event_id') as event_id,
                    MAX(julianday(end_date) - julianday(start_date)) as max_days
                FROM fathom_api_responses
                WHERE aggregation_type = 'event'
                GROUP BY json_extract(response_data, '$.event_id')
            ) latest
            ON json_extract(far.response_data, '$.event_id') = latest.event_id
            AND (julianday(far.end_date) - julianday(far.start_date)) = latest.max_days
            WHERE far.aggregation_type = 'event'
        ");

        $responses = collect($responses);

        if ($responses->isEmpty()) {
            $this->warn('No event responses found. Run fathom:import-event-data first.');
            return self::FAILURE;
        }

        $this->info("Found {$responses->count()} responses. Processing...");

        // Clear existing aggregates to rebuild from scratch
        DB::table('enriched_page_clicks')->truncate();
        $this->line('  Cleared existing page click aggregates');

        $pageClickData = []; // [site_id][page_id][date] = ['clicks' => x, 'unique_clicks' => y]

        foreach ($responses as $response) {
            $data = json_decode($response->response_data, true);
            $eventId = $data['event_id'] ?? null;
            $items = $data['data'] ?? [];

            if (!$eventId) {
                continue;
            }

            // Find the site_id for this event
            $event = DB::table('fathom_events')
                ->where('fathom_event_id', $eventId)
                ->first();

            if (!$event) {
                continue;
            }

            // Aggregate clicks per site+page+date
            foreach ($items as $item) {
                $date = $item['date'] ?? $response->start_date;
                $conversions = $item['conversions'] ?? 0;
                $uniqueConversions = $item['unique_conversions'] ?? 0;
                $pathname = $item['pathname'] ?? null;

                $siteId = $event->site_id;

                // Page-level aggregates (if pathname exists)
                if ($pathname) {
                    // Find or create page_id for this pathname
                    $page = DB::table('pages')
                        ->where('site_id', $siteId)
                        ->where('pathname', $pathname)
                        ->first();

                    if (!$page) {
                        // Create new page record
                        $pageId = DB::table('pages')->insertGetId([
                            'site_id' => $siteId,
                            'pathname' => $pathname,
                            'url' => null, // Will be filled by other processes
                            'content_type' => null, // Will be determined later
                            'title' => null, // Will be filled later
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        $pageId = $page->id;
                    }

                    if (!isset($pageClickData[$siteId])) {
                        $pageClickData[$siteId] = [];
                    }

                    if (!isset($pageClickData[$siteId][$pageId])) {
                        $pageClickData[$siteId][$pageId] = [];
                    }

                    if (!isset($pageClickData[$siteId][$pageId][$date])) {
                        $pageClickData[$siteId][$pageId][$date] = ['clicks' => 0, 'unique_clicks' => 0];
                    }

                    $pageClickData[$siteId][$pageId][$date]['clicks'] += $conversions;
                    $pageClickData[$siteId][$pageId][$date]['unique_clicks'] += $uniqueConversions;
                }
            }
        }

        // Insert page-level aggregated data
        $pageEnriched = 0;
        foreach ($pageClickData as $siteId => $pages) {
            foreach ($pages as $pageId => $dates) {
                foreach ($dates as $date => $data) {
                    DB::table('enriched_page_clicks')->insert([
                        'site_id' => $siteId,
                        'page_id' => $pageId,
                        'date' => $date,
                        'clicks' => $data['clicks'],
                        'unique_clicks' => $data['unique_clicks'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $pageEnriched++;
                }
            }
        }

        // Now build site-level aggregates from page-level data
        $this->line('  Building site-level click aggregates from page data...');
        DB::table('enriched_click_aggregates')->truncate();

        $siteAggregates = DB::table('enriched_page_clicks')
            ->select('site_id', 'date', DB::raw('SUM(clicks) as total_clicks'), DB::raw('SUM(unique_clicks) as total_unique_clicks'))
            ->groupBy('site_id', 'date')
            ->get();

        foreach ($siteAggregates as $aggregate) {
            DB::table('enriched_click_aggregates')->insert([
                'site_id' => $aggregate->site_id,
                'date' => $aggregate->date,
                'clicks' => $aggregate->total_clicks,
                'unique_clicks' => $aggregate->total_unique_clicks,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->newLine();
        $this->info("✓ Enriched {$pageEnriched} page-level click records from {$responses->count()} event responses");
        $this->info("✓ Built {$siteAggregates->count()} site-level click aggregates from page data");

        return self::SUCCESS;
    }
}
