<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class LiveVisitorsController extends Controller
{
    public function index()
    {
        $token = config('services.fathom.api_token');

        // Get all sites with Fathom site IDs
        $sites = DB::table('sites')
            ->whereNotNull('fathom_site_id')
            ->get(['id', 'fathom_site_id']);

        $totalVisitors = 0;
        $rateLimitHit = false;

        foreach ($sites as $site) {
            try {
                /** @var \Illuminate\Http\Client\Response $response */
                $response = Http::withToken($token)
                    ->get('https://api.usefathom.com/v1/current_visitors', [
                        'site_id' => $site->fathom_site_id,
                    ]);

                if ($response->status() === 429) {
                    $rateLimitHit = true;
                    \Log::warning('Fathom API rate limit hit for live visitors');
                    break; // Stop trying if we hit rate limit
                }

                if ($response->successful()) {
                    $data = $response->json();
                    $totalVisitors += $data['total'] ?? 0;
                }
            } catch (\Exception $e) {
                // Silently fail - live visitors is not critical
                continue;
            }
        }

        if ($rateLimitHit) {
            return response()->json([
                'error' => 'Rate limit exceeded'
            ], 429);
        }

        return response()->json([
            'total' => $totalVisitors
        ]);
    }
}
