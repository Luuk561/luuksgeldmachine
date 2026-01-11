<?php

namespace App\Console\Commands;

use App\Services\FathomApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportFathomData extends Command
{
    protected $signature = 'fathom:import {site_id : Fathom site ID} {--days=7 : Number of days to import}';
    protected $description = 'Import Fathom pageview data and save to database';

    public function handle(FathomApiService $fathom): int
    {
        $siteId = $this->argument('site_id');
        $days = $this->option('days');
        $startDate = now()->subDays($days)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');
        $dateFrom = now()->subDays($days)->format('Y-m-d 00:00:00');
        $dateTo = now()->format('Y-m-d 23:59:59');

        $this->info("Importing Fathom data for site {$siteId} from {$startDate} to {$endDate}...");

        // Delete old imports for this site that overlap with our date range
        // This prevents duplicate data when re-importing
        $deleted = DB::table('fathom_api_responses')
            ->where('site_id', $siteId)
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
            // Get total aggregations grouped by day
            $totalData = $fathom->getPageviewAggregations(
                $siteId,
                $dateFrom,
                $dateTo,
                ['pageviews', 'uniques', 'visits'],
                null,
                'timestamp:desc',
                1000,
                'day'
            );

            DB::table('fathom_api_responses')->insert([
                'site_id' => $siteId,
                'aggregation_type' => 'total',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'response_data' => json_encode($totalData),
                'fetched_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->info('✓ Imported total aggregations');

            // Get per-pathname aggregations grouped by day
            $pathnameData = $fathom->getPageviewAggregations(
                $siteId,
                $dateFrom,
                $dateTo,
                ['pageviews', 'uniques', 'visits'],
                'pathname',
                'pageviews:desc',
                1000,
                'day'
            );

            DB::table('fathom_api_responses')->insert([
                'site_id' => $siteId,
                'aggregation_type' => 'pathname',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'response_data' => json_encode($pathnameData),
                'fetched_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $pageCount = count($pathnameData['data'] ?? []);
            $this->info("✓ Imported {$pageCount} pathname aggregations");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Failed to import Fathom data');
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
