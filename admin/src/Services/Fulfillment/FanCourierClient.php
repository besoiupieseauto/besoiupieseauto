<?php

declare(strict_types=1);

namespace Evasystem\Services\Fulfillment;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Client Fan Courier API v2 (intern-awb).
 */
final class FanCourierClient
{
    private Client $http;
    private string $tokenCacheFile;

    public function __construct(?Client $http = null)
    {
        $cfg = FulfillmentConfig::fancourier();
        $baseUrl = rtrim((string) ($cfg['api_url'] ?? 'https://api.fancourier.ro'), '/') . '/';

        $this->http = $http ?? new Client([
            'base_uri' => $baseUrl,
            'timeout' => 45,
            'connect_timeout' => 15,
            'verify' => false,
        ]);

        $this->tokenCacheFile = dirname(__DIR__, 3) . '/storage/cache/fancourier_token.json';
    }

    public function isConfigured(): bool
    {
        return FulfillmentConfig::fancourierConfigured();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success:bool,awb:string,data:?array<string,mixed>,error:string}
     */
    public function createInternAwb(array $payload): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'awb' => '',
                'data' => null,
                'error' => 'Fan Courier nu este configurat.',
            ];
        }

        $token = $this->getToken();
        if ($token === '') {
            return [
                'success' => false,
                'awb' => '',
                'data' => null,
                'error' => 'Autentificare Fan Courier esuata.',
            ];
        }

        try {
            $response = $this->http->post('intern-awb', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $body = (string) $response->getBody();
            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($body, true);
            if (!is_array($decoded)) {
                return [
                    'success' => false,
                    'awb' => '',
                    'data' => null,
                    'error' => 'Raspuns Fan Courier invalid.',
                ];
            }

            $items = $decoded['response'] ?? $decoded['data'] ?? [];
            if (!is_array($items)) {
                $items = [];
            }

            $first = $items[0] ?? null;
            if (!is_array($first)) {
                return [
                    'success' => false,
                    'awb' => '',
                    'data' => $decoded,
                    'error' => (string) ($decoded['message'] ?? 'Fan Courier: AWB negasit in raspuns.'),
                ];
            }

            if (!empty($first['errors'])) {
                $errors = is_array($first['errors']) ? implode('; ', $first['errors']) : (string) $first['errors'];

                return [
                    'success' => false,
                    'awb' => '',
                    'data' => $decoded,
                    'error' => $errors !== '' ? $errors : 'Fan Courier a returnat erori.',
                ];
            }

            $awb = (string) ($first['awbNumber'] ?? $first['awb'] ?? '');
            if ($awb === '') {
                return [
                    'success' => false,
                    'awb' => '',
                    'data' => $decoded,
                    'error' => 'Fan Courier nu a returnat numar AWB.',
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

    private function getToken(): string
    {
        $cached = $this->readTokenCache();
        if ($cached !== null) {
            return $cached;
        }

        $cfg = FulfillmentConfig::fancourier();

        try {
            $response = $this->http->post('login', [
                'query' => [
                    'username' => (string) $cfg['username'],
                    'password' => (string) $cfg['password'],
                ],
            ]);

            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode((string) $response->getBody(), true);
            $token = (string) ($decoded['data']['token'] ?? '');
            $expiresAt = (string) ($decoded['data']['expiresAt'] ?? '');

            if ($token === '') {
                return '';
            }

            $this->writeTokenCache($token, $expiresAt);

            return $token;
        } catch (GuzzleException) {
            return '';
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
