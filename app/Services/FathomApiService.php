<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FathomApiService
{
    private const API_BASE_URL = 'https://api.usefathom.com/v1';

    private string $apiToken;

    public function __construct()
    {
        $this->apiToken = config('services.fathom.api_token');
    }

    /**
     * Get pageview aggregations
     *
     * @param string $siteId Fathom site ID
     * @param string $dateFrom Format: Y-m-d H:i:s
     * @param string $dateeTo Format: Y-m-d H:i:s
     * @param array $aggregates e.g. ['pageviews', 'uniques', 'visits']
     * @param string|null $fieldGrouping e.g. 'pathname' or 'pathname,hostname'
     * @param string|null $sortBy e.g. 'pageviews:desc'
     * @param int $limit
     * @return array
     */
    public function getPageviewAggregations(
        string $siteId,
        string $dateFrom,
        string $dateTo,
        array $aggregates = ['pageviews', 'uniques', 'visits'],
        ?string $fieldGrouping = null,
        ?string $sortBy = null,
        int $limit = 1000,
        ?string $dateGrouping = null,
        ?string $timezone = null
    ): array {
        $params = [
            'entity' => 'pageview',
            'entity_id' => $siteId,
            'aggregates' => implode(',', $aggregates),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'limit' => $limit,
        ];

        if ($dateGrouping) {
            $params['date_grouping'] = $dateGrouping;
        }

        if ($timezone) {
            $params['timezone'] = $timezone;
        }

        if ($fieldGrouping) {
            $params['field_grouping'] = $fieldGrouping;
        }

        if ($sortBy) {
            $params['sort_by'] = $sortBy;
        }

        $response = Http::withToken($this->apiToken)
            ->withHeaders(['Accept' => 'application/json'])
            ->get(self::API_BASE_URL . '/aggregations', $params);

        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After') ?? 60;
            Log::warning('Fathom API rate limit hit', [
                'retry_after' => $retryAfter,
                'params' => $params,
            ]);
            throw new \Exception("Rate limit exceeded. Retry after {$retryAfter} seconds.");
        }

        if ($response->failed()) {
            Log::error('Fathom API aggregations failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'params' => $params,
            ]);
            throw new \Exception('Failed to fetch Fathom aggregations: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get list of sites
     *
     * @param array $params Optional pagination params (limit, starting_after, ending_before)
     * @return array
     */
    public function getSites(array $params = []): array
    {
        $response = Http::withToken($this->apiToken)
            ->withHeaders(['Accept' => 'application/json'])
            ->get(self::API_BASE_URL . '/sites', $params);

        if ($response->failed()) {
            Log::error('Fathom API sites failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to fetch Fathom sites');
        }

        return $response->json();
    }

    /**
     * Get current visitors
     *
     * @param string $siteId
     * @param bool $detailed
     * @return array
     */
    public function getCurrentVisitors(string $siteId, bool $detailed = false): array
    {
        $params = [
            'site_id' => $siteId,
        ];

        if ($detailed) {
            $params['detailed'] = 'true';
        }

        $response = Http::withToken($this->apiToken)
            ->withHeaders(['Accept' => 'application/json'])
            ->get(self::API_BASE_URL . '/current_visitors', $params);

        if ($response->failed()) {
            Log::error('Fathom API current_visitors failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to fetch current visitors');
        }

        return $response->json();
    }
}
