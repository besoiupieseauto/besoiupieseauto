<?php

declare(strict_types=1);

namespace Evasystem\Services\Fulfillment;

/**
 * Gateway unificat pentru generare AWB (Fan Courier / Sameday).
 */
final class CourierGateway
{
    private FulfillmentPayloadBuilder $payloadBuilder;
    private FanCourierClient $fanCourier;
    private SamedayClient $sameday;

    public function __construct(
        ?FulfillmentPayloadBuilder $payloadBuilder = null,
        ?FanCourierClient $fanCourier = null,
        ?SamedayClient $sameday = null
    ) {
        $this->payloadBuilder = $payloadBuilder ?? new FulfillmentPayloadBuilder();
        $this->fanCourier = $fanCourier ?? new FanCourierClient();
        $this->sameday = $sameday ?? new SamedayClient();
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $options
     * @return array{success:bool,awb:string,courier:string,error:string,data:?array<string,mixed>}
     */
    public function createAwbForOrder(array $order, array $options = []): array
    {
        if (!FulfillmentConfig::awbEnabled()) {
            return [
                'success' => false,
                'awb' => '',
                'courier' => '',
                'error' => 'Generarea AWB este dezactivata (FULFILLMENT_AWB_ENABLED=0).',
                'data' => null,
            ];
        }

        $deliveryMethod = mb_strtolower(trim((string) ($order['delivery_method'] ?? '')));
        if ($deliveryMethod === 'ridicare_locala') {
            return [
                'success' => false,
                'awb' => '',
                'courier' => 'Ridicare magazin',
                'error' => 'Ridicare locala — AWB curier nu se genereaza.',
                'data' => null,
            ];
        }

        $courier = mb_strtolower(trim((string) ($options['courier'] ?? FulfillmentConfig::defaultCourier())));
        if ($courier === '' || $courier === 'fan courier' || $courier === 'fancourier') {
            $courier = 'fancourier';
        } elseif (str_contains($courier, 'sameday')) {
            $courier = 'sameday';
        }

        if (FulfillmentConfig::isTestMode()) {
            $payload = $courier === 'sameday'
                ? $this->payloadBuilder->buildSamedayAwb($order, $options)
                : $this->payloadBuilder->buildFanCourierShipment($order, $options);
            $deliveryRandomId = (int) ($options['delivery_randomn_id'] ?? 0);
            $simulator = new FulfillmentTestSimulator();
            $simulated = $simulator->simulateAwb($order, $options, $payload, $deliveryRandomId);

            return [
                'success' => $simulated['success'],
                'awb' => $simulated['awb'],
                'courier' => $simulated['courier'],
                'error' => $simulated['error'],
                'data' => $simulated['data'],
            ];
        }

        if ($courier === 'sameday' && $this->sameday->isConfigured()) {
            $payload = $this->payloadBuilder->buildSamedayAwb($order, $options);
            $result = $this->sameday->createAwb($payload);

            return [
                'success' => $result['success'],
                'awb' => $result['awb'],
                'courier' => 'Sameday',
                'error' => $result['error'],
                'data' => $result['data'],
            ];
        }

        if ($this->fanCourier->isConfigured()) {
            $payload = $this->payloadBuilder->buildFanCourierShipment($order, $options);
            $result = $this->fanCourier->createInternAwb($payload);

            return [
                'success' => $result['success'],
                'awb' => $result['awb'],
                'courier' => 'Fan Courier',
                'error' => $result['error'],
                'data' => $result['data'],
            ];
        }

        if ($courier === 'sameday' && !$this->sameday->isConfigured()) {
            return [
                'success' => false,
                'awb' => '',
                'courier' => 'Sameday',
                'error' => 'Sameday nu este configurat in .env.',
                'data' => null,
            ];
        }

        return [
            'success' => false,
            'awb' => '',
            'courier' => '',
            'error' => 'Niciun curier configurat (FANCOURIER_* sau SAMEDAY_*).',
            'data' => null,
        ];
    }
}
