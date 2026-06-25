<?php

declare(strict_types=1);

namespace Evasystem\Services\Fulfillment;

/**
 * Simulare fulfillment (SmartBill + AWB) — fara apeluri API reale.
 */
final class FulfillmentTestSimulator
{
    private string $logFile;

    public function __construct()
    {
        $this->logFile = dirname(__DIR__, 3) . '/storage/logs/fulfillment_test.log';
    }

    /**
     * @param array<string, mixed> $order
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, mixed> $payload
     * @return array{success:bool,test_mode:bool,series:string,number:string,error:string,data:array<string,mixed>}
     */
    public function simulateSmartBill(array $order, array $lines, array $payload, int $invoiceRandomId): array
    {
        $series = FulfillmentConfig::testSeries();
        $number = (string) $invoiceRandomId;
        $data = [
            'series' => $series,
            'number' => $number,
            'test_mode' => true,
            'simulated_at' => date('c'),
            'order_number' => (string) ($order['order_number'] ?? ''),
            'payload_preview' => $payload,
            'lines_count' => count($lines),
        ];

        $this->log('smartbill', $data);

        return [
            'success' => true,
            'test_mode' => true,
            'series' => $series,
            'number' => $number,
            'error' => '',
            'data' => $data,
        ];
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $options
     * @param array<string, mixed>|null $payload
     * @return array{success:bool,test_mode:bool,awb:string,courier:string,error:string,data:array<string,mixed>}
     */
    public function simulateAwb(array $order, array $options, ?array $payload, int $deliveryRandomId): array
    {
        $courier = $this->resolveCourierLabel($options);
        $awb = FulfillmentConfig::testAwbPrefix() . '-' . $deliveryRandomId;
        $data = [
            'awbNumber' => $awb,
            'test_mode' => true,
            'simulated_at' => date('c'),
            'courier' => $courier,
            'order_number' => (string) ($order['order_number'] ?? ''),
            'payload_preview' => $payload,
        ];

        $this->log('awb', $data);

        return [
            'success' => true,
            'test_mode' => true,
            'awb' => $awb,
            'courier' => $courier . ' (TEST)',
            'error' => '',
            'data' => $data,
        ];
    }

    public function prefixMessage(string $message): string
    {
        if (!FulfillmentConfig::isTestMode()) {
            return $message;
        }

        return '[MOD TEST] ' . $message;
    }

    public function localInvoiceNumber(int $invoiceRandomId): string
    {
        return FulfillmentConfig::testInvoicePrefix() . '-' . $invoiceRandomId;
    }

    public function localAwbNumber(int $deliveryRandomId): string
    {
        return FulfillmentConfig::testAwbPrefix() . '-' . $deliveryRandomId;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveCourierLabel(array $options): string
    {
        $courier = mb_strtolower(trim((string) ($options['courier'] ?? FulfillmentConfig::defaultCourier())));
        if (str_contains($courier, 'sameday')) {
            return 'Sameday';
        }

        return 'Fan Courier';
    }

    /** @param array<string, mixed> $data */
    private function log(string $type, array $data): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $line = json_encode([
            'type' => $type,
            'at' => date('c'),
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);

        if ($line !== false) {
            @file_put_contents($this->logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
}
