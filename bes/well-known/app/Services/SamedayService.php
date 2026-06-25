<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\ApiCredential;

class SamedayService
{ 
    protected $client;
    protected $baseUrl;
    protected $token;
    protected $tokenExpiry;
    protected $username;
    protected $password;

    public function __construct()
    {
        $this->username = $this->getCredential('sameday', 'auth_user');
        $this->password = $this->getCredential('sameday', 'auth_password');
        $this->baseUrl  = $this->getCredential('sameday', 'host_url', 'https://api.sameday.ro');
		
        //$this->baseUrl = config('sameday.host_url', 'https://api.sameday.ro');

        $proxy = config('sameday.proxy'); // Load from .env

        Log::info('Using proxy for Sameday API', ['proxy' => $proxy]);

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => config('sameday.timeout', 60),
            'connect_timeout' => config('sameday.connect_timeout', 30),
            'verify' => config('sameday.verify_ssl', false),
            'proxy' => $proxy
        ]);

        $this->initializeToken();
    }

    protected function getCredential(string $service, string $key, $default = null): ?string
    {
        $record = ApiCredential::where('service_name', $service)
            ->where('data_key', $key)
            ->first();

        return $record->data_value ?? config("sameday.$key") ?? $default;
    }
	
    protected function initializeToken()
    {
        if (Cache::has('sameday_token')) {
            $tokenData = Cache::get('sameday_token');
            $this->token = $tokenData['token'];
            $this->tokenExpiry = $tokenData['expires_at'];
        } else {
            $this->authenticate();
        }
    }

    public function authenticate()
    {
        try {
            Log::info('Authenticating with Sameday API', [
                'username' => $this->username,
                'password_length' => strlen($this->password),
                'url' => $this->baseUrl . '/api/authenticate'
            ]);

/*             $response = $this->client->post('/api/authenticate', [
                'json' => [
                    'username' => config('sameday.auth_user'),
                    'password' => config('sameday.auth_password')
                ]
            ]); */
			$response = $this->client->post('/api/authenticate', [
				'headers' => [
					'X-Auth-Username' => $this->username,
					'X-Auth-Password' => $this->password,
				],
				'form_params' => [
					'remember_me' => true
				]
			]);

            $data = json_decode((string) $response->getBody(), true);
            Log::info('Sameday API response', ['data' => $data]);

            if (isset($data['token'])) {
                $this->token = $data['token'];
                $this->tokenExpiry = $data['expire_at'] ?? now()->addHours(24)->toDateTimeString();

                Cache::put('sameday_token', [
                    'token' => $this->token,
                    'expires_at' => $this->tokenExpiry
                ], now()->addHours(23));

                Log::info('Sameday authentication successful', ['expires_at' => $this->tokenExpiry]);

                return [
                    'success' => true,
                    'token' => $this->token,
                    'expires_at' => $this->tokenExpiry
                ];
            }

            Log::error('Sameday authentication failed', ['response' => $data]);

            return [
                'success' => false,
                'error' => 'Authentication failed'
            ];
        } catch (\Exception $e) {
            Log::error('Sameday authentication error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    protected function makeRequest($method, $endpoint, $options = [])
    {
        if (empty($this->token)) {
            $this->authenticate();
            if (empty($this->token)) {
                return [
                    'success' => false,
                    'error' => 'Failed to obtain authentication token'
                ];
            }
        }

        $options['headers'] = $options['headers'] ?? [];
        $options['headers']['Authorization'] = 'Bearer ' . $this->token;

        try {
            $response = $this->client->request($method, $endpoint, $options);
            $data = json_decode((string) $response->getBody(), true);

            return [
                'success' => true,
                'data' => $data
            ];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();

            if ($statusCode === 401) {
                $this->authenticate();
                if (!empty($this->token)) {
                    $options['headers']['Authorization'] = 'Bearer ' . $this->token;
                    try {
                        $response = $this->client->request($method, $endpoint, $options);
                        $data = json_decode((string) $response->getBody(), true);

                        return [
                            'success' => true,
                            'data' => $data
                        ];
                    } catch (\Exception $retryEx) {
                        Log::error('Sameday API retry error', [
                            'message' => $retryEx->getMessage(),
                            'endpoint' => $endpoint
                        ]);
                    }
                }
            }

            $errorBody = json_decode((string) $response->getBody(), true);
            Log::error('Sameday API error', [
                'status' => $statusCode,
                'endpoint' => $endpoint,
                'error' => $errorBody,
                'message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $errorBody['message'] ?? $e->getMessage(),
                'status' => $statusCode
            ];
        } catch (\Exception $e) {
            Log::error('Sameday API general error', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getPickupPoints($city = null)
    {
        $options = [];
        if ($city) {
            $options['query'] = ['city' => $city];
        }

        return $this->makeRequest('GET', '/api/client/pickup-points', $options);
    }

    public function getServices()
    {
        return $this->makeRequest('GET', '/api/client/services');
    }

    public function getCities($county = null)
    {
        $options = [];
        if ($county) {
            $options['query'] = ['county' => $county];
        }

        return $this->makeRequest('GET', '/api/client/cities', $options);
    }

    public function getCounties()
    {
        return $this->makeRequest('GET', '/api/client/counties');
    }

    public function calculatePrice($request)
    {
        return $this->makeRequest('POST', '/api/client/calculate-price', [
            'json' => $request
        ]);
    }

    public function createAwb($request)
    {
        return $this->makeRequest('POST', '/api/client/awb', [
            'json' => $request
        ]);
    }
}
