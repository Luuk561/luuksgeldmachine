<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ImportFathomEventData extends Command
{
    protected $signature = 'fathom:import-event-data {--days=180 : Number of days to import}';
    protected $description = 'Import affiliate click event aggregations from Fathom';

    public function handle(): int
    {
        $days = $this->option('days');
        $token = config('services.fathom.api_token');
        $dateFrom = now()->subDays($days)->format('Y-m-d 00:00:00');
        $dateTo = now()->format('Y-m-d 23:59:59');
        $startDate = now()->subDays($days)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        // Get all affiliate click events
        $events = DB::table('fathom_events')
            ->join('sites', 'fathom_events.site_id', '=', 'sites.id')
            ->where('is_affiliate_click', true)
            ->select('fathom_events.*', 'sites.fathom_site_id')
            ->get();

        if ($events->isEmpty()) {
            $this->warn('No affiliate click events found. Run fathom:import-events first.');
            return self::FAILURE;
        }

        $this->info("Importing {$days} days of event data for {$events->count()} affiliate click events...");
        $this->warn("This will take ~" . ceil($events->count() / 10) . " minutes (rate limit: 10 req/min)");
        $this->newLine();

        $bar = $this->output->createProgressBar($events->count());
        $bar->start();

        $imported = 0;
        $errors = 0;

        foreach ($events as $index => $event) {
            // Rate limiting: 10 requests per minute
            if ($index > 0 && $index % 10 === 0) {
                sleep(60); // Wait 1 minute after every 10 requests
            }

            try {
                $response = Http::withToken($token)
                    ->get('https://api.usefathom.com/v1/aggregations', [
                        'entity' => 'event',
                        'site_id' => $event->fathom_site_id,
                        'entity_name' => $event->event_name,
                        'aggregates' => 'conversions,unique_conversions',
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                        'date_grouping' => 'day',
                        'field_grouping' => 'pathname',
                    ]);

                if ($response->successful()) {
                    $data = $response->json();

                    // Store raw response
                    DB::table('fathom_api_responses')->insert([
                        'site_id' => $event->fathom_site_id,
                        'aggregation_type' => 'event',
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'response_data' => json_encode([
                            'event_id' => $event->fathom_event_id,
                            'event_name' => $event->event_name,
                            'data' => $data,
                        ]),
                        'fetched_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $imported++;
                } else {
                    $errors++;
                }

                $bar->advance();

            } catch (\Exception $e) {
                $errors++;
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✓ Import completed!");
        $this->info("  Imported: {$imported}");
        if ($errors > 0) {
            $this->warn("  Errors: {$errors}");
        }

        return self::SUCCESS;
    }
}
