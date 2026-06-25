<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Furnizori;

/**
 * Client pentru Auto Partner Customer API (REST).
 * @see https://customerapi.autopartner.dev/CustomerAPI.svc/rest
 */
final class AutoPartnerApiClient
{
    private string $baseUrl = 'https://customerapi.autopartner.dev/CustomerAPI.svc/rest';
    private string $clientCode = '';
    private string $wsPassword = '';
    private string $clientPassword = '';

    /** @param array<string, mixed> $furnizor */
    public function configure(array $furnizor): self
    {
        $this->baseUrl = rtrim(trim((string) ($furnizor['api_base_url'] ?? $this->baseUrl)), '/');
        $this->clientCode = trim((string) ($furnizor['api_client_code'] ?? $furnizor['conn_username'] ?? ''));
        $this->wsPassword = (string) ($furnizor['api_ws_password'] ?? '');
        $this->clientPassword = (string) ($furnizor['api_client_password'] ?? '');

        $tokenJson = trim((string) ($furnizor['api_token'] ?? ''));
        if ($tokenJson !== '' && str_starts_with($tokenJson, '{')) {
            $decoded = json_decode($tokenJson, true);
            if (is_array($decoded)) {
                $this->clientCode = trim((string) ($decoded['clientCode'] ?? $decoded['client_code'] ?? $this->clientCode));
                $this->wsPassword = (string) ($decoded['wsPassword'] ?? $decoded['ws_password'] ?? $this->wsPassword);
                $this->clientPassword = (string) ($decoded['clientPassword'] ?? $decoded['client_password'] ?? $this->clientPassword);
            }
        } elseif ($tokenJson !== '' && $this->clientPassword === '') {
            $this->clientPassword = $tokenJson;
        }

        return $this;
    }

    /** @return array{ok:bool,message:string} */
    public function testConnection(): array
    {
        if ($this->clientCode === '' || $this->wsPassword === '' || $this->clientPassword === '') {
            return [
                'ok' => false,
                'message' => 'Lipsesc clientCode, wsPassword sau clientPassword API.',
            ];
        }

        $result = $this->request('/ProductAvailabilityV2', [
            'product' => ['productCode' => 'GDB1330', 'quantity' => 1],
            'onlySite' => false,
        ]);

        if (!$result['success']) {
            return ['ok' => false, 'message' => 'API Auto Partner: ' . $result['error']];
        }

        $payload = $result['data']['RestProductAvailabilityV2Result'] ?? $result['data'] ?? [];
        $errorCode = $payload['ErrorCode'] ?? null;
        if ($errorCode !== null && $errorCode !== '' && $errorCode !== 0) {
            return ['ok' => false, 'message' => 'API Auto Partner eroare cod ' . (string) $errorCode];
        }

        $productCode = (string) ($payload['Availability']['ProductCode'] ?? 'GDB1330');

        return [
            'ok' => true,
            'message' => 'OK — API Auto Partner activ (test ' . $productCode . ').',
        ];
    }

    /** @param array<string, mixed> $data @return array{success:bool,data:array<string,mixed>,error:string,status:int} */
    public function request(string $endpoint, array $data = []): array
    {
        if (!function_exists('curl_init')) {
            return ['success' => false, 'data' => [], 'error' => 'cURL lipseste pe server.', 'status' => 0];
        }

        $url = $this->baseUrl . (str_starts_with($endpoint, '/') ? $endpoint : '/' . $endpoint);
        $payload = array_merge([
            'clientCode' => $this->clientCode,
            'wsPassword' => $this->wsPassword,
            'clientPassword' => $this->clientPassword,
        ], $data);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = trim((string) curl_error($ch));
        curl_close($ch);

        if ($body === false || $error !== '') {
            return ['success' => false, 'data' => [], 'error' => $error !== '' ? $error : 'Raspuns gol', 'status' => $status];
        }

        $decoded = json_decode((string) $body, true);

        if ($status < 200 || $status >= 300) {
            return [
                'success' => false,
                'data' => is_array($decoded) ? $decoded : [],
                'error' => 'HTTP ' . $status,
                'status' => $status,
            ];
        }

        return [
            'success' => true,
            'data' => is_array($decoded) ? $decoded : [],
            'error' => '',
            'status' => $status,
        ];
    }

    /** @return array<string, string> */
    public function credentialsForStorage(): array
    {
        return [
            'api_base_url' => $this->baseUrl,
            'api_client_code' => $this->clientCode,
            'api_ws_password' => $this->wsPassword,
            'api_client_password' => $this->clientPassword,
            'api_token' => json_encode([
                'clientCode' => $this->clientCode,
                'wsPassword' => $this->wsPassword,
                'clientPassword' => $this->clientPassword,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }
}
