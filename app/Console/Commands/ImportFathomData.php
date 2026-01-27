<?php

namespace App\Console\Commands;

use App\Services\FathomApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportFathomData extends Command
{
    protected $signature = 'fathom:import {site_id : Fathom site ID} {--days=7 : Number of days to import} {--hourly : Use hourly granularity instead of daily}';
    protected $description = 'Import Fathom pageview data and save to database';

    public function handle(FathomApiService $fathom): int
    {
        $siteId = $this->argument('site_id');
        $days = $this->option('days');
        $hourly = $this->option('hourly');
        $startDate = now()->subDays($days)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');
        $dateFrom = now()->subDays($days)->format('Y-m-d 00:00:00');
        $dateTo = now()->format('Y-m-d 23:59:59');

        $granularity = $hourly ? 'hourly' : 'daily';
        $this->info("Importing Fathom data ({$granularity}) for site {$siteId} from {$startDate} to {$endDate}...");

        // Determine aggregation types based on granularity
        $totalType = $hourly ? 'total_hourly' : 'total';
        $pathnameType = $hourly ? 'pathname_hourly' : 'pathname';
        $dateGrouping = $hourly ? 'hour' : 'day';
        $timezone = $hourly ? 'Europe/Amsterdam' : null;

        // Delete old imports for this site that overlap with our date range
        // This prevents duplicate data when re-importing
        $deleted = DB::table('fathom_api_responses')
            ->where('site_id', $siteId)
            ->whereIn('aggregation_type', [$totalType, $pathnameType])
            ->where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function($q) use ($startDate, $endDate) {
                          $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                      });
            })
            ->delete();

        if ($deleted > 0) {
            $this->info("  Removed {$deleted} overlapping imports");
        }

        try {
            // Get total aggregations
            $totalData = $fathom->getPageviewAggregations(
                $siteId,
                $dateFrom,
                $dateTo,
                ['pageviews', 'uniques', 'visits'],
                null,
                'timestamp:desc',
                1000,
                $dateGrouping,
                $timezone
            );

            // Delete existing responses for this site, type, and date range to avoid duplicates
            DB::table('fathom_api_responses')
                ->where('site_id', $siteId)
                ->where('aggregation_type', $totalType)
                ->where('start_date', $startDate)
                ->where('end_date', $endDate)
                ->delete();

            DB::table('fathom_api_responses')->insert([
                'site_id' => $siteId,
                'aggregation_type' => $totalType,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'response_data' => json_encode($totalData),
                'fetched_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->info("✓ Imported total aggregations ({$granularity})");

            // For hourly, we skip pathname aggregations (too much data, not needed for dashboard)
            // Get per-pathname aggregations (both daily and hourly)
            $pathnameData = $fathom->getPageviewAggregations(
                $siteId,
                $dateFrom,
                $dateTo,
                ['pageviews', 'uniques', 'visits'],
                'pathname',
                'pageviews:desc',
                1000,
                $dateGrouping,
                $timezone
            );

            // Delete existing responses for this site, type, and date range to avoid duplicates
            DB::table('fathom_api_responses')
                ->where('site_id', $siteId)
                ->where('aggregation_type', $pathnameType)
                ->where('start_date', $startDate)
                ->where('end_date', $endDate)
                ->delete();

            DB::table('fathom_api_responses')->insert([
                'site_id' => $siteId,
                'aggregation_type' => $pathnameType,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'response_data' => json_encode($pathnameData),
                'fetched_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $pageCount = \count($pathnameData['data'] ?? []);
            $this->info("✓ Imported {$pageCount} pathname aggregations");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Failed to import Fathom data');
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
