<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ImportFathomEvents extends Command
{
    protected $signature = 'fathom:import-events';
    protected $description = 'Import all Fathom event names (one-time setup for affiliate click tracking)';

    public function handle(): int
    {
        $token = config('services.fathom.api_token');

        $sites = DB::table('sites')
            ->whereNotNull('fathom_site_id')
            ->get();

        $this->info("Importing event names for {$sites->count()} sites...");
        $this->warn("This is a ONE-TIME import. Rate limit: 10 req/min (6 sec between sites)");
        $this->newLine();

        $bar = $this->output->createProgressBar($sites->count());
        $bar->start();

        $totalEvents = 0;
        $affiliateEvents = 0;

        foreach ($sites as $index => $site) {
            // Rate limiting
            if ($index > 0) {
                sleep(6);
            }

            try {
                // Fetch ALL events for this site (paginated)
                $allEvents = [];
                $hasMore = true;
                $startingAfter = null;

                while ($hasMore) {
                    $params = ['limit' => 100];
                    if ($startingAfter) {
                        $params['starting_after'] = $startingAfter;
                    }

                    /** @var \Illuminate\Http\Client\Response $response */
                    $response = Http::withToken($token)
                        ->get("https://api.usefathom.com/v1/sites/{$site->fathom_site_id}/events", $params);

                    if ($response->failed()) {
                        $this->newLine();
                        $this->error("Failed for {$site->name}: " . $response->body());
                        break;
                    }

                    $data = $response->json();
                    $events = $data['data'] ?? [];
                    $allEvents = array_merge($allEvents, $events);

                    $hasMore = $data['has_more'] ?? false;
                    if ($hasMore && !empty($events)) {
                        $startingAfter = end($events)['id'];
                    }
                }

                // Store events in database
                foreach ($allEvents as $event) {
                    $isAffiliateClick = str_starts_with($event['name'], 'Affiliate click');

                    DB::table('fathom_events')->updateOrInsert(
                        [
                            'site_id' => $site->id,
                            'fathom_event_id' => $event['id'],
                        ],
                        [
                            'event_name' => $event['name'],
                            'is_affiliate_click' => $isAffiliateClick,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );

                    $totalEvents++;
                    if ($isAffiliateClick) {
                        $affiliateEvents++;
                    }
                }

                $bar->advance();

            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Error for {$site->name}: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✓ Import completed!");
        $this->info("  Total events: {$totalEvents}");
        $this->info("  Affiliate click events: {$affiliateEvents}");

        return self::SUCCESS;
    }
}
