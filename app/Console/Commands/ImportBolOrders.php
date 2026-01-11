<?php

namespace App\Console\Commands;

use App\Services\BolApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportBolOrders extends Command
{
    protected $signature = 'bol:import-orders {--days=30 : Number of days to import}';
    protected $description = 'Import Bol orders and save to database';

    public function handle(BolApiService $bolApi): int
    {
        $days = $this->option('days');
        $startDate = now()->subDays($days)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $this->info("Importing Bol orders from {$startDate} to {$endDate}...");

        try {
            $orderReport = $bolApi->getOrderReport($startDate, $endDate);

            // Save raw response to database
            DB::table('bol_api_responses')->insert([
                'endpoint' => 'order-report',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'response_data' => json_encode($orderReport),
                'fetched_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $itemCount = count($orderReport['items'] ?? []);
            $this->info("✓ Successfully imported {$itemCount} orders");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Failed to import Bol orders');
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
