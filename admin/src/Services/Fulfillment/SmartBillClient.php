<?php

declare(strict_types=1);

namespace Evasystem\Services\Fulfillment;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Client REST SmartBill Cloud (factura fiscala).
 */
final class SmartBillClient
{
    private Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client([
            'timeout' => 45,
            'connect_timeout' => 15,
            'verify' => true,
        ]);
    }

    public function isConfigured(): bool
    {
        return FulfillmentConfig::smartbillConfigured();
    }

    /**
     * @param array<string, mixed> $invoiceData
     * @return array{success:bool,data:?array<string,mixed>,error:string,status:int}
     */
    public function createInvoice(array $invoiceData): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'SmartBill nu este configurat (SMARTBILL_API_KEY / SMARTBILL_USER_EMAIL).',
                'status' => 0,
            ];
        }

        $cfg = FulfillmentConfig::smartbill();
        $endpoint = rtrim((string) $cfg['api_url'], '/') . '/invoice';

        try {
            $response = $this->http->post($endpoint, [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($cfg['user_email'] . ':' . $cfg['api_key']),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $invoiceData,
            ]);

            $body = (string) $response->getBody();
            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($body, true);

            if (!is_array($decoded)) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'Raspuns SmartBill invalid.',
                    'status' => $response->getStatusCode(),
                ];
            }

            if (!isset($decoded['number'], $decoded['series'])) {
                $message = (string) ($decoded['errorText'] ?? $decoded['message'] ?? $body);

                return [
                    'success' => false,
                    'data' => $decoded,
                    'error' => $message !== '' ? $message : 'SmartBill nu a returnat numar factura.',
                    'status' => $response->getStatusCode(),
                ];
            }

            return [
                'success' => true,
                'data' => $decoded,
                'error' => '',
                'status' => $response->getStatusCode(),
            ];
        } catch (GuzzleException $exception) {
            return [
                'success' => false,
                'data' => null,
                'error' => $exception->getMessage(),
                'status' => 0,
            ];
        }
    }
}
