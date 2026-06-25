<?php

declare(strict_types=1);

namespace Evasystem\Services\Fulfillment;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Client REST Sameday (AWB).
 */
final class SamedayClient
{
    private Client $http;
    private string $tokenCacheFile;
    private ?string $token = null;

    public function __construct(?Client $http = null)
    {
        $cfg = FulfillmentConfig::sameday();
        $baseUrl = rtrim((string) ($cfg['api_url'] ?? 'https://api.sameday.ro'), '/') . '/';

        $this->http = $http ?? new Client([
            'base_uri' => $baseUrl,
            'timeout' => 60,
            'connect_timeout' => 20,
            'verify' => false,
        ]);

        $this->tokenCacheFile = dirname(__DIR__, 3) . '/storage/cache/sameday_token.json';
    }

    public function isConfigured(): bool
    {
        return FulfillmentConfig::samedayConfigured();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success:bool,awb:string,data:?array<string,mixed>,error:string}
     */
    public function createAwb(array $payload): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'awb' => '',
                'data' => null,
                'error' => 'Sameday nu este configurat.',
            ];
        }

        if (!$this->ensureToken()) {
            return [
                'success' => false,
                'awb' => '',
                'data' => null,
                'error' => 'Autentificare Sameday esuata.',
            ];
        }

        try {
            $response = $this->http->post('api/client/awb', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode((string) $response->getBody(), true);
            if (!is_array($decoded)) {
                return [
                    'success' => false,
                    'awb' => '',
                    'data' => null,
                    'error' => 'Raspuns Sameday invalid.',
                ];
            }

            $awb = (string) ($decoded['awbNumber'] ?? $decoded['awb'] ?? '');
            if ($awb === '' && isset($decoded['awb']) && is_array($decoded['awb'])) {
                $awb = (string) ($decoded['awb']['awbNumber'] ?? '');
            }

            if ($awb === '') {
                return [
                    'success' => false,
                    'awb' => '',
                    'data' => $decoded,
                    'error' => (string) ($decoded['error'] ?? $decoded['message'] ?? 'Sameday nu a returnat AWB.'),
                ];
            }

            return [
                'success' => true,
                'awb' => $awb,
                'data' => $decoded,
                'error' => '',
            ];
        } catch (GuzzleException $exception) {
            return [
                'success' => false,
                'awb' => '',
                'data' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function ensureToken(): bool
    {
        if ($this->token !== null && $this->token !== '') {
            return true;
        }

        $cached = $this->readTokenCache();
        if ($cached !== null) {
            $this->token = $cached;

            return true;
        }

        $cfg = FulfillmentConfig::sameday();

        try {
            $response = $this->http->post('api/authenticate', [
                'headers' => [
                    'X-Auth-Username' => (string) $cfg['username'],
                    'X-Auth-Password' => (string) $cfg['password'],
                ],
                'form_params' => [
                    'remember_me' => 'true',
                ],
            ]);

            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode((string) $response->getBody(), true);
            $token = (string) ($decoded['token'] ?? '');
            if ($token === '') {
                return false;
            }

            $this->token = $token;
            $this->writeTokenCache($token, (string) ($decoded['expire_at'] ?? ''));

            return true;
        } catch (GuzzleException) {
            return false;
        }
    }

    private function readTokenCache(): ?string
    {
        if (!is_file($this->tokenCacheFile)) {
            return null;
        }

        $raw = file_get_contents($this->tokenCacheFile);
        if ($raw === false) {
            return null;
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        $token = (string) ($data['token'] ?? '');
        $expiresAt = strtotime((string) ($data['expires_at'] ?? ''));
        if ($token === '' || $expiresAt <= time() + 60) {
            return null;
        }

        return $token;
    }

    private function writeTokenCache(string $token, string $expiresAt): void
    {
        $dir = dirname($this->tokenCacheFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        file_put_contents($this->tokenCacheFile, json_encode([
            'token' => $token,
            'expires_at' => $expiresAt !== '' ? $expiresAt : date('Y-m-d H:i:s', time() + 82800),
        ], JSON_UNESCAPED_UNICODE));
    }
}
