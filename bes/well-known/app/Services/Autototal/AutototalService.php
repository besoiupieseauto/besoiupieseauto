<?php

namespace App\Services\Autototal;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\Response;
use Exception;

/**
 * AutoTotal Service
 *
 * Laravel service for interacting with AutoTotal APIs:
 * 1. Availability API - https://atx.autototal.ro:15063
 * 2. Order API - https://atx.autototal.ro:15085
 */
class AutototalService
{
    /* =====================================================
     | CREDENTIALS BY LOCATION
     ===================================================== */
    private string $username;
    private string $password;
    
    // Timisoara credentials
    private string $timisoaraUsername;
    private string $timisoaraPassword;
    
    // Utvin credentials
    private string $utvinUsername;
    private string $utvinPassword;

    public function __construct()
    {
        $this->username = (string) env('AUTOTOTAL_USER_TIMISOARA', '');
        $this->password = (string) env('AUTOTOTAL_PASS_TIMISOARA', '');
        $this->timisoaraUsername = (string) env('AUTOTOTAL_USER_TIMISOARA', '');
        $this->timisoaraPassword = (string) env('AUTOTOTAL_PASS_TIMISOARA', '');
        $this->utvinUsername = (string) env('AUTOTOTAL_USER_UTVIN', '');
        $this->utvinPassword = (string) env('AUTOTOTAL_PASS_UTVIN', '');
    }

    /**
     * Base URLs
     */
    private string $availabilityBaseUrl = 'https://atx.autototal.ro:15063';
    private string $orderBaseUrl = 'https://atx.autototal.ro:15085';

    /**
     * API version
     */
    private string $orderApiVersion = '1';

    /**
     * Tokens
     */
    private ?string $availabilityToken = null;
    private ?string $orderToken = null;

    /**
     * Token expiry timestamps
     */
    private ?int $availabilityTokenExpiresAt = null;
    private ?int $orderTokenExpiresAt = null;

    /**
     * Token lifetimes
     */
    private int $availabilityTokenLifetime = 86400; // 24h
    private int $orderTokenLifetime = 600; // 10 min

    /**
     * HTTP settings
     */
    private int $timeout = 30;
    private int $connectTimeout = 10;

    private function getAvailabilityTokenCacheKey(): string
    {
        return 'autototal:availability_token:' . md5($this->username);
    }

    private function getOrderTokenCacheKey(string $version = '1'): string
    {
        return 'autototal:order_token:' . md5($this->username . '|' . $version);
    }

    /* =====================================================
     | TOKEN CHECKS
     ===================================================== */
    private function isAvailabilityTokenExpired(): bool
    {
        return !$this->availabilityToken
            || !$this->availabilityTokenExpiresAt
            || time() >= ($this->availabilityTokenExpiresAt - 60);
    }

    private function isOrderTokenExpired(): bool
    {
        return !$this->orderToken
            || !$this->orderTokenExpiresAt
            || time() >= ($this->orderTokenExpiresAt - 30);
    }

    /* =====================================================
     | CREDENTIALS BY LOCATION
     ===================================================== */
    protected function setCredentialsByOrderFrom(?string $orderFrom = null): void
    {
        match ($orderFrom) {
            'UTVIN' => $this->setUtvinCredentials(),
            'TIMISOARA' => $this->setTimisoaraCredentials(),
            default => $this->setTimisoaraCredentials(), // default to Timisoara
        };
        
        // Invalidate tokens when credentials change
        $this->availabilityToken = null;
        $this->availabilityTokenExpiresAt = null;
        $this->orderToken = null;
        $this->orderTokenExpiresAt = null;
    }
    
    private function setUtvinCredentials(): void
    {
        $this->username = $this->utvinUsername;
        $this->password = $this->utvinPassword;
    }
    
    private function setTimisoaraCredentials(): void
    {
        $this->username = $this->timisoaraUsername;
        $this->password = $this->timisoaraPassword;
    }

    /* =====================================================
     | TOKEN ENSURE
     ===================================================== */
    private function ensureAvailabilityToken(?string $orderFrom = null, ?int $timeout = null, ?int $connectTimeout = null)
    {
        if ($orderFrom) {
            $this->setCredentialsByOrderFrom($orderFrom);
        }

        if (!$this->availabilityToken) {
            $cachedToken = Cache::get($this->getAvailabilityTokenCacheKey());
            if (is_string($cachedToken) && $cachedToken !== '') {
                $this->availabilityToken = $cachedToken;
                $this->availabilityTokenExpiresAt = time() + max(60, $this->availabilityTokenLifetime - 120);
            }
        }

        if (!$this->isAvailabilityTokenExpired()) {
            return $this->availabilityToken;
        }

        return $this->authenticateAvailability($this->username, $this->password, $timeout, $connectTimeout);
    }

    private function ensureOrderToken(string $version = '1', ?string $orderFrom = null)
    {
        if ($orderFrom) {
            $this->setCredentialsByOrderFrom($orderFrom);
        }

        if (!$this->orderToken) {
            $cachedOrderToken = Cache::get($this->getOrderTokenCacheKey($version));
            if (is_string($cachedOrderToken) && $cachedOrderToken !== '') {
                $this->orderToken = $cachedOrderToken;
                $this->orderTokenExpiresAt = time() + max(60, $this->orderTokenLifetime - 60);
            }
        }

        if (!$this->isOrderTokenExpired()) {
            return $this->orderToken;
        }

        return $this->authenticateOrder($this->username, $this->password, $version);
    }

    public function getAvailabilityBaseUrl(): string
    {
        return $this->availabilityBaseUrl;
    }

    public function getAvailabilityToken(?string $orderFrom = null, ?int $timeout = null, ?int $connectTimeout = null)
    {
        return $this->ensureAvailabilityToken($orderFrom, $timeout, $connectTimeout);
    }

    /* =====================================================
     | AUTHENTICATION
     ===================================================== */
    public function authenticateAvailability(string $username, string $password, ?int $timeout = null, ?int $connectTimeout = null)
    {
        try {
            $requestTimeout = max(2, min($timeout ?? $this->timeout, 15));
            $requestConnectTimeout = max(1, min($connectTimeout ?? $this->connectTimeout, 5));

            $response = Http::timeout($requestTimeout)
                ->connectTimeout($requestConnectTimeout)
                ->post($this->availabilityBaseUrl . '/api/User', [
                    'username' => $username,
                    'password' => $password,
                ]);
			
            if ($response->successful()) {
                // Availability API returns "token" (lowercase) in the response
                $token = $response->json('token') ?? $response->body();

                $this->availabilityToken = $token;
                $this->availabilityTokenExpiresAt = time() + $this->availabilityTokenLifetime;
                Cache::put(
                    $this->getAvailabilityTokenCacheKey(),
                    $token,
                    now()->addSeconds(max(60, $this->availabilityTokenLifetime - 120))
                );

                Log::info('AutoTotal Availability API authenticated', [
                    'username' => $username,
                    'location' => $this->username === $this->utvinUsername ? 'UTVIN' : 'TIMISOARA'
                ]);

                return $token;
            }

            Log::error('Availability auth failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Availability auth exception', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function authenticateOrder(string $username, string $password, string $version = '1')
    {
        try {
            $this->orderApiVersion = $version;

            $response = Http::timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->post($this->orderBaseUrl . "/api/v{$version}/user/authenticate", [
                    'Username' => $username,
                    'Password' => $password,
                ]);

            if ($response->successful()) {
                // Order API returns "Token" (uppercase) in the response
                $token = $response->json('Token') ?? $response->json('token') ?? $response->body();

                $this->orderToken = $token;
                $this->orderTokenExpiresAt = time() + $this->orderTokenLifetime;
                Cache::put(
                    $this->getOrderTokenCacheKey($version),
                    $token,
                    now()->addSeconds(max(60, $this->orderTokenLifetime - 60))
                );

                Log::info('AutoTotal Order API authenticated', [
                    'username' => $username,
                    'location' => $this->username === $this->utvinUsername ? 'UTVIN' : 'TIMISOARA'
                ]);

                return $token;
            }

            Log::error('Order auth failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Order auth exception', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /* =====================================================
     | AVAILABILITY
     ===================================================== */
    public function checkAvailability(?string $itemkey = null, ?int $quantity = null, ?string $orderFrom = null)
    {
        $token = $this->ensureAvailabilityToken($orderFrom);

        $response = Http::timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->withToken($token, 'Bearer')
            ->get($this->availabilityBaseUrl . '/api/Availability', array_filter([
                'itemkey'  => $itemkey,
                'quantity' => $quantity,
            ]));

        if ($response->successful()) {
            return $response->json() ?? $response->body();
        }

        Log::error('Availability check failed', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        return false;
    }

    /**
     * Check availability for multiple itemkeys in parallel.
     *
     * @param array $requests Array of ['itemkey' => string, 'quantity' => int, 'orderFrom' => ?string]
     * @return array Responses in the same order as requests
     */
    public function checkAvailabilityBatch(array $requests, ?int $timeout = null, ?int $connectTimeout = null): array
    {
        if (empty($requests)) {
            return [];
        }

        $token = $this->ensureAvailabilityToken(
            $requests[0]['orderFrom'] ?? null,
            $timeout,
            $connectTimeout
        );
        $baseUrl = rtrim($this->availabilityBaseUrl, '/') . '/api/Availability';
        $searchTimeout = min($timeout ?? $this->timeout, 15);
        $searchConnectTimeout = min($connectTimeout ?? $this->connectTimeout, 5);

        if (!$token || !is_string($token)) {
            return array_fill(0, count($requests), false);
        }

        $responses = Http::pool(fn ($pool) => collect($requests)
            ->map(fn ($req, $index) => $pool
                ->as((string) $index)
                ->withToken($token, 'Bearer')
                ->timeout($searchTimeout)
                ->connectTimeout($searchConnectTimeout)
                ->get($baseUrl, array_filter([
                    'itemkey'  => $req['itemkey'] ?? null,
                    'quantity' => $req['quantity'] ?? 1,
                ])))
            ->all()
        );

        $result = [];
        foreach ($requests as $index => $req) {
            $response = $responses[(string) $index] ?? null;
            if ($response instanceof Response) {
                if ($response->successful()) {
                    $result[$index] = $response->json() ?? $response->body();
                    continue;
                }

                Log::error('Autototal batch request failed', [
                    'request_index' => $index,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $result[$index] = false;
                continue;
            }

            if ($response instanceof \Throwable) {
                Log::error('Autototal batch connection failed', [
                    'request_index' => $index,
                    'exception_class' => get_class($response),
                    'message' => $response->getMessage(),
                ]);
                $result[$index] = false;
                continue;
            }

            if ($response !== null) {
                Log::error('Autototal batch returned unexpected response type', [
                    'request_index' => $index,
                    'response_type' => get_debug_type($response),
                ]);
            }

            $result[$index] = false;
        }

        return $result;
    }

    /* =====================================================
     | ORDER
     ===================================================== */
    public function createOrder(
        array $items,
        ?array $urls = null,
        ?string $remarks = null,
        string $version = '1',
        ?string $orderFrom = null
    ) {
        $token = $this->ensureOrderToken($version, $orderFrom);

		$payload = [
			'ITEM'    => $items,
			'URL'     => $urls ?? [],
			'REMARKS' => $remarks ?? '',
		];
		
        $response = Http::timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->withToken($token, 'Bearer')
            ->post($this->orderBaseUrl . "/api/v{$version}/order", $payload);

        if ($response->successful()) {
			$data = $response->json();

			if (
				isset($data[0]['STATUS']) &&
				isset($data[0]['MESSAGE']) &&
				$data[0]['MESSAGE'] === 'OK'
			) {
				return [
					'success'   => true,
					'order_id'  => $data[0]['STATUS'],
					'message'   => $data[0]['MESSAGE'],
					'raw'       => $data
				];
			}

			return [
				'success' => false,
				'raw'     => $data
			];
        }

		Log::error('Create order failed', [
			'status' => $response->status(),
			'body'   => $response->body(),
		]);

        return false;
    }
	
    public function applyPrefix(string $brand, string $code): string
    {
        $brand = strtoupper(trim($brand));
        $code  = trim($code);

        // Remove spaces, dashes, slashes
        //$code = str_replace([' ', '-', '/', '|', '\\'], '', $code);

        switch ($brand) {
            case 'MEYLE':
                return $code . 'MY';
            case 'MONROE':
                return $code . 'MON';
            case 'AIRTEX':
                return $code . 'A';
            case 'ARNOTT':
                return $code . 'ARN';
            case 'ASSO':
                return $code . 'A';
            case 'ATE':
                return $code; // uses short codes as is
            case 'BERU':
            case 'BERU BY DRIV':
                return $code . 'BERU';
            case 'BILSTEIN':
                return $code . 'B';
            case 'BUGIAD':
                return $code . 'BUG';
            case 'CORTECO':
                return $code . 'CO';
            case 'DAYCO':
                return $code . 'DY';
            case 'ELSTOCK':
                return $code . 'E';
            case 'ERA':
                return $code . 'ERA';
            case 'FAI AUTOPARTS':
                return $code . 'FAI';
            case 'FEBI BILSTEIN':
                return $code . 'F';
            case 'GRAF':
                return $code . 'G';
            case 'HEPU':
                return $code . 'H';
            case 'HITACHI':
                return $code . 'HH';
            case 'JAPANPARTS':
                return $code . 'JAP';
            case 'KOLBENSCHMIDT':
                return $code . 'KBS';
            case 'KYB':
                return $code . 'K';
            case 'LUK':
                return $code . 'L';
            case 'MEAT & DORIA':
                return $code . 'MD';
            case 'NGK':
                return $code . 'NGK';
            case 'NISSENS':
                return 'N' . $code;
            case 'NRF':
                return $code . 'NRF';
            case 'SACHS':
                return $code . 'S';
            case 'SNR':
                return $code . 'SNR';
            case 'SPIDAN':
                return '00' . $code;
            case 'STABILUS':
                return $code . 'STA';
            case 'TEXTAR':
                return $code . 'TX';
            case 'TOPRAN':
                return $code . 'HP';
            case 'TRISCAN':
                return $code . 'T';
            case 'VALEO':
                return $code . 'V';
            case 'WAHLER':
                return $code . 'WLR';
            default:
                return $code; // fallback
        }
    }

    public function normalizeCode(string $apiCode): string
    {
        // Remove spaces, dashes, slashes, and convert to uppercase
        $code = strtoupper($apiCode);
        //$code = str_replace([' ', '-', '/', '|', '\\'], '', $code);

        // Remove known suffixes to get the base code
        $suffixes = [
            'MY', 'MON', 'A', 'ARN', 'BERU', 'B', 'BUG', 'CO', 'DY',
            'E', 'ERA', 'FAI', 'F', 'G', 'H', 'HH', 'JAP', 'KBS', 'K',
            'L', 'MD', 'NGK', 'NRF', 'S', 'SNR', 'STA', 'TX', 'HP', 'T',
            'V', 'WLR'
        ];

        foreach ($suffixes as $suf) {
            if (str_ends_with($code, $suf)) {
                $code = substr($code, 0, -strlen($suf));
                break;
            }
        }

        // Remove leading 'N' for NISSENS
        if (str_starts_with($code, 'N') && strlen($code) > 1) {
            $code = substr($code, 1);
        }

        // Remove leading '00' for SPIDAN
        if (str_starts_with($code, '00') && strlen($code) > 2) {
            $code = substr($code, 2);
        }

        return $code;
    }
}
