<?php

namespace App\Console\Commands;

use App\Services\BolApiService;
use Illuminate\Console\Command;

class TestBolApi extends Command
{
    protected $signature = 'bol:test {--days=30 : Number of days to fetch}';
    protected $description = 'Test Bol API connection and fetch recent orders';

    public function handle(BolApiService $bolApi): int
    {
        $days = $this->option('days');
        $startDate = now()->subDays($days)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $this->info("Fetching Bol orders from {$startDate} to {$endDate}...");

        try {
            $orderReport = $bolApi->getOrderReport($startDate, $endDate);

            $this->info('✓ Successfully fetched order report');
            $this->line('Total items: ' . count($orderReport['items'] ?? []));

            if (!empty($orderReport['items'])) {
                $this->newLine();
                $this->info('First 3 orders:');
                $sample = array_slice($orderReport['items'], 0, 3);

                foreach ($sample as $order) {
                    $this->line("- Order {$order['orderId']} | {$order['productTitle']} | €{$order['commission']}");
                }
            }

            $this->newLine();
            $this->info('Full response saved to storage/logs/bol-test.json');
            file_put_contents(
                storage_path('logs/bol-test.json'),
                json_encode($orderReport, JSON_PRETTY_PRINT)
            );

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Failed to fetch Bol data');
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
