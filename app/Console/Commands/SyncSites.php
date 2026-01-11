<?php

namespace App\Console\Commands;

use App\Services\FathomApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncSites extends Command
{
    protected $signature = 'sites:sync';
    protected $description = 'Sync sites from Fathom API to sites table';

    public function handle(FathomApiService $fathom): int
    {
        $this->info('Fetching sites from Fathom...');

        try {
            $allSites = [];
            $hasMore = true;
            $startingAfter = null;

            // Handle pagination
            while ($hasMore) {
                $params = ['limit' => 100];
                if ($startingAfter) {
                    $params['starting_after'] = $startingAfter;
                }

                $response = $fathom->getSites($params);
                $sites = $response['data'] ?? [];
                $allSites = array_merge($allSites, $sites);

                $hasMore = $response['has_more'] ?? false;
                if ($hasMore && !empty($sites)) {
                    $startingAfter = end($sites)['id'];
                }
            }

            $this->info('Found ' . count($allSites) . ' sites. Syncing to database...');

            $synced = 0;
            $skipped = 0;

            foreach ($allSites as $site) {
                $domain = $site['name']; // Using 'name' as domain for now
                $fathomId = $site['id'];

                $existing = DB::table('sites')->where('fathom_site_id', $fathomId)->first();

                if ($existing) {
                    $skipped++;
                    continue;
                }

                DB::table('sites')->insert([
                    'domain' => $domain,
                    'name' => $domain,
                    'fathom_site_id' => $fathomId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $synced++;
                $this->line("  ✓ {$domain} ({$fathomId})");
            }

            $this->newLine();
            $this->info("✓ Synced {$synced} new sites");
            if ($skipped > 0) {
                $this->info("  Skipped {$skipped} existing sites");
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Failed to sync sites');
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
