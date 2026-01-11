<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnrichBolOrders extends Command
{
    protected $signature = 'bol:enrich-orders';
    protected $description = 'Enrich Bol orders from raw API responses';

    public function handle(): int
    {
        $this->info('Fetching raw Bol order responses...');

        $rawResponses = DB::table('bol_api_responses')
            ->where('endpoint', 'order-report')
            ->get();

        if ($rawResponses->isEmpty()) {
            $this->warn('No raw order responses found. Run bol:import-orders first.');
            return self::FAILURE;
        }

        $this->info("Found {$rawResponses->count()} raw responses. Processing...");

        $enriched = 0;
        $skipped = 0;

        foreach ($rawResponses as $response) {
            $data = json_decode($response->response_data, true);
            $items = $data['items'] ?? [];

            foreach ($items as $item) {
                $orderItemId = $item['orderItemId'];

                // Skip if already enriched
                if (DB::table('enriched_orders')->where('order_item_id', $orderItemId)->exists()) {
                    $skipped++;
                    continue;
                }

                // Match or create product
                $productId = $this->matchOrCreateProduct($item);

                // Match site (via site_code from Bol)
                $siteId = $this->matchSite($item['siteCode'] ?? null, $item['siteName'] ?? null);

                // Insert enriched order
                DB::table('enriched_orders')->insert([
                    'order_id' => $item['orderId'],
                    'order_item_id' => $orderItemId,
                    'product_id' => $productId,
                    'site_id' => $siteId,
                    'page_id' => null, // Can't determine page from Bol data alone
                    'order_date' => $item['orderDate'],
                    'order_datetime' => $item['orderDateTime'],
                    'quantity' => $item['quantity'],
                    'commission' => $item['commission'],
                    'price_excl_vat' => $item['priceExclVat'],
                    'price_incl_vat' => $item['priceInclVat'],
                    'status' => $item['status'],
                    'status_final' => $item['statusFinal'],
                    'approved_for_payment' => $item['approvedForPayment'],
                    'site_code' => $item['siteCode'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $enriched++;
            }
        }

        $this->newLine();
        $this->info("✓ Enriched {$enriched} orders");
        if ($skipped > 0) {
            $this->info("  Skipped {$skipped} existing orders");
        }

        return self::SUCCESS;
    }

    private function matchOrCreateProduct(array $item): ?int
    {
        $productId = $item['productId'] ?? null;
        $productTitle = $item['productTitle'] ?? 'Unknown Product';

        if (!$productId) {
            return null;
        }

        // Try to find existing product
        $product = DB::table('products')->where('bol_product_id', $productId)->first();

        if ($product) {
            return $product->id;
        }

        // Create new product
        return DB::table('products')->insertGetId([
            'bol_product_id' => $productId,
            'name' => $productTitle,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function matchSite(?string $siteCode, ?string $siteName): ?int
    {
        if (!$siteCode && !$siteName) {
            return null;
        }

        // Try exact match on site_code (if we had mapping)
        // For now, try fuzzy match on domain/name
        if ($siteName) {
            $site = DB::table('sites')
                ->where('domain', 'LIKE', "%{$siteName}%")
                ->orWhere('name', 'LIKE', "%{$siteName}%")
                ->first();

            if ($site) {
                return $site->id;
            }
        }

        return null; // Unmapped
    }
}
