<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BolApiService
{
    private const TOKEN_URL = 'https://login.bol.com/token';
    private const API_BASE_URL = 'https://api.bol.com/marketing/affiliate/reports/v2';
    private const TOKEN_CACHE_KEY = 'bol_api_access_token';

    private string $clientId;
    private string $clientSecret;

    public function __construct()
    {
        $this->clientId = config('services.bol.client_id');
        $this->clientSecret = config('services.bol.client_secret');
    }

    /**
     * Get access token (cached or fresh)
     */
    private function getAccessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(4), function () {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->withHeaders(['Accept' => 'application/json'])
                ->post(self::TOKEN_URL . '?grant_type=client_credentials');

            if ($response->failed()) {
                Log::error('Bol API token request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Failed to obtain Bol API token');
            }

            $data = $response->json();
            return $data['access_token'];
        });
    }

    /**
     * Get orders report
     *
     * @param string $startDate Format: Y-m-d
     * @param string $endDate Format: Y-m-d
     * @return array
     */
    public function getOrderReport(string $startDate, string $endDate): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->get(self::API_BASE_URL . '/order-report', [
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]);

        if ($response->failed()) {
            Log::error('Bol API order-report failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to fetch order report from Bol API');
        }

        return $response->json();
    }

    /**
     * Get commission report
     *
     * @param string $startDate Format: Y-m-d
     * @param string $endDate Format: Y-m-d
     * @return array
     */
    public function getCommissionReport(string $startDate, string $endDate): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->get(self::API_BASE_URL . '/commission-report', [
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]);

        if ($response->failed()) {
            Log::error('Bol API commission-report failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to fetch commission report from Bol API');
        }

        return $response->json();
    }

    /**
     * Get promotion report
     *
     * @param string $startDate Format: Y-m-d
     * @param string $endDate Format: Y-m-d
     * @return array
     */
    public function getPromotionReport(string $startDate, string $endDate): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->get(self::API_BASE_URL . '/promotion-report', [
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]);

        if ($response->failed()) {
            Log::error('Bol API promotion-report failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to fetch promotion report from Bol API');
        }

        return $response->json();
    }
}
