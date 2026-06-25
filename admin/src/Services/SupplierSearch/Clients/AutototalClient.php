<?php

declare(strict_types=1);

namespace Evasystem\Services\SupplierSearch\Clients;

use Evasystem\Services\SupplierSearch\SupplierSearchConfig;

final class AutototalClient
{
    private const TOKEN_TTL = 86400;

    public function getAvailabilityToken(): ?string
    {
        $cacheFile = SupplierSearchConfig::cacheDir() . '/autototal_availability_token.json';
        if (is_file($cacheFile)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached) && !empty($cached['token']) && (int) ($cached['expires_at'] ?? 0) > time() + 60) {
                return (string) $cached['token'];
            }
        }

        $creds = SupplierSearchConfig::autototalCredentials();
        if ($creds['username'] === '' || $creds['password'] === '') {
            return null;
        }

        $url = SupplierSearchConfig::autototalAvailabilityBaseUrl() . '/api/User';
        $payload = json_encode([
            'username' => $creds['username'],
            'password' => $creds['password'],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false || $status < 200 || $status >= 300) {
            return null;
        }

        $decoded = json_decode((string) $body, true);
        $token = is_array($decoded) ? (string) ($decoded['token'] ?? $body) : trim((string) $body);
        if ($token === '') {
            return null;
        }

        file_put_contents($cacheFile, json_encode([
            'token' => $token,
            'expires_at' => time() + self::TOKEN_TTL,
        ], JSON_UNESCAPED_UNICODE));

        return $token;
    }

    /** @return array{success:bool,data:array<string,mixed>,error:string,status:int} */
    public function checkAvailability(string $itemkey, int $quantity = 2): array
    {
        $token = $this->getAvailabilityToken();
        if ($token === null) {
            return ['success' => false, 'data' => [], 'error' => 'Token Autototal indisponibil (AUTOTOTAL_USERNAME/PASSWORD).', 'status' => 0];
        }

        $url = SupplierSearchConfig::autototalAvailabilityBaseUrl() . '/api/Availability?' . http_build_query([
            'itemkey' => $itemkey,
            'quantity' => $quantity,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = trim((string) curl_error($ch));
        curl_close($ch);

        if ($body === false || $error !== '') {
            return ['success' => false, 'data' => [], 'error' => $error !== '' ? $error : 'Răspuns gol Autototal', 'status' => $status];
        }

        $decoded = json_decode((string) $body, true);

        return [
            'success' => $status >= 200 && $status < 300,
            'data' => is_array($decoded) ? $decoded : [],
            'error' => $status >= 200 && $status < 300 ? '' : 'HTTP ' . $status,
            'status' => $status,
        ];
    }
}
