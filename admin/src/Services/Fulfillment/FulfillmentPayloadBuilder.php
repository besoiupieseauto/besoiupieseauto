<?php

declare(strict_types=1);

namespace Evasystem\Services\Fulfillment;

/**
 * Construieste payload-uri SmartBill / curieri din comanda site + order_items.
 */
final class FulfillmentPayloadBuilder
{
    /** @var array<string, string> */
    private const COUNTY_MAP = [
        'TM' => 'Timis',
        'B' => 'Bucuresti',
        'CJ' => 'Cluj',
        'IS' => 'Iasi',
        'AB' => 'Alba',
        'AR' => 'Arad',
        'AG' => 'Arges',
        'BC' => 'Bacau',
        'BH' => 'Bihor',
        'BN' => 'Bistrita-Nasaud',
        'BT' => 'Botosani',
        'BR' => 'Braila',
        'BV' => 'Brasov',
        'BZ' => 'Buzau',
        'CL' => 'Calarasi',
        'CS' => 'Caras-Severin',
        'CT' => 'Constanta',
        'CV' => 'Covasna',
        'DB' => 'Dambovita',
        'DJ' => 'Dolj',
        'GL' => 'Galati',
        'GR' => 'Giurgiu',
        'GJ' => 'Gorj',
        'HR' => 'Harghita',
        'HD' => 'Hunedoara',
        'IL' => 'Ialomita',
        'IF' => 'Ilfov',
        'MM' => 'Maramures',
        'MH' => 'Mehedinti',
        'MS' => 'Mures',
        'NT' => 'Neamt',
        'OT' => 'Olt',
        'PH' => 'Prahova',
        'SJ' => 'Salaj',
        'SM' => 'Satu Mare',
        'SB' => 'Sibiu',
        'SV' => 'Suceava',
        'TR' => 'Teleorman',
        'TL' => 'Tulcea',
        'VL' => 'Valcea',
        'VS' => 'Vaslui',
        'VN' => 'Vrancea',
    ];

    /**
     * @param array<string, mixed> $order
     * @param array<int, array<string, mixed>> $lines
     * @return array<string, mixed>
     */
    public function buildSmartBillInvoice(array $order, array $lines): array
    {
        $cfg = FulfillmentConfig::smartbill();
        $parsed = $this->parseOrderNotes((string) ($order['notes'] ?? ''));
        $paymentMethod = mb_strtolower(trim((string) ($order['payment_status'] ?? 'ramburs')));
        $county = $this->resolveCounty($parsed, $order);
        $city = $parsed['city'] !== '' ? $parsed['city'] : 'Timisoara';
        $address = $parsed['address'] !== '' ? $parsed['address'] : ($city . ', Romania');

        $products = [];
        $totalWithVat = 0.0;

        foreach ($lines as $line) {
            $name = trim((string) ($line['product_name'] ?? $line['name'] ?? 'Piesa auto'));
            $qty = max(1, (int) ($line['quantity'] ?? 1));
            $lineTotal = round((float) ($line['line_total'] ?? 0), 2);
            if ($lineTotal <= 0) {
                $unitPrice = round((float) ($line['unit_price'] ?? 0), 2);
                $lineTotal = round($unitPrice * $qty, 2);
            }
            $unitPrice = $qty > 0 ? round($lineTotal / $qty, 2) : $lineTotal;
            $totalWithVat += $lineTotal;

            $products[] = [
                'name' => $name,
                'isDiscount' => false,
                'measuringUnitName' => 'buc',
                'currency' => 'RON',
                'quantity' => $qty,
                'price' => $unitPrice,
                'saveToDb' => false,
                'isService' => false,
                'isTaxIncluded' => false,
                'taxName' => 'Normala',
                'taxPercentage' => 21,
            ];
        }

        if ($products === []) {
            $amount = round((float) ($order['total_amount'] ?? 0), 2);
            $products[] = [
                'name' => 'Comanda ' . ($order['order_number'] ?? ''),
                'isDiscount' => false,
                'measuringUnitName' => 'buc',
                'currency' => 'RON',
                'quantity' => 1,
                'price' => $amount,
                'saveToDb' => false,
                'isService' => false,
                'isTaxIncluded' => false,
                'taxName' => 'Normala',
                'taxPercentage' => 21,
            ];
            $totalWithVat = $amount;
        }

        $issueDate = date('Y-m-d');

        return [
            'companyVatCode' => (string) ($cfg['company_vat'] ?? 'RO31298897'),
            'client' => [
                'name' => (string) ($order['client_name'] ?? 'Client'),
                'vatCode' => '-',
                'isTaxPayer' => false,
                'address' => $address,
                'city' => mb_strtolower($county) === 'bucuresti' ? 'Sector 1' : $city,
                'county' => $county,
                'country' => 'Romania',
                'email' => (string) ($order['email'] ?? 'client@besoiupieseauto.ro'),
                'saveToDb' => false,
            ],
            'issueDate' => $issueDate,
            'seriesName' => (string) ($cfg['series_name'] ?? 'BPA_CAI'),
            'isDraft' => FulfillmentConfig::isTestMode(),
            'dueDate' => date('Y-m-d', strtotime('+14 days')),
            'deliveryDate' => $issueDate,
            'products' => $products,
            'payment' => [
                'value' => round($totalWithVat, 2),
                'type' => $this->mapSmartBillPaymentType($paymentMethod),
                'isCash' => in_array($paymentMethod, ['numerar', 'ramburs'], true),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function buildFanCourierShipment(array $order, array $options = []): array
    {
        $cfg = FulfillmentConfig::fancourier();
        $parsed = $this->parseOrderNotes((string) ($order['notes'] ?? ''));
        $paymentMethod = mb_strtolower(trim((string) ($order['payment_status'] ?? 'ramburs')));
        $total = round((float) ($order['total_amount'] ?? 0), 2);
        $isCod = in_array($paymentMethod, ['ramburs'], true);
        $county = $this->resolveCounty($parsed, $order);
        $city = $parsed['city'] !== '' ? $parsed['city'] : 'Timisoara';
        $street = $options['street'] ?? $parsed['street'] ?? ('Str. ' . $city);
        $streetNo = (string) ($options['street_no'] ?? $parsed['street_no'] ?? '1');
        $zipCode = $parsed['postal_code'] !== '' ? $parsed['postal_code'] : '300000';

        return [
            'clientId' => (int) ($cfg['client_id'] ?? 0),
            'shipments' => [[
                'info' => [
                    'service' => $isCod ? (string) ($cfg['service_cod'] ?? 'Cont Colector') : (string) ($cfg['service_standard'] ?? 'Standard'),
                    'packages' => [
                        'parcel' => 1,
                        'envelope' => 0,
                    ],
                    'weight' => max(1, (float) ($options['weight'] ?? 2)),
                    'cod' => $isCod ? $total : 0,
                    'declaredValue' => $total,
                    'payment' => 'sender',
                    'returnPayment' => null,
                    'observation' => 'Comanda ' . ($order['order_number'] ?? ''),
                    'content' => 'Piese auto',
                    'dimensions' => [
                        'length' => 30,
                        'height' => 20,
                        'width' => 20,
                    ],
                    'costCenter' => (string) ($cfg['cost_center'] ?? 'WEB'),
                    'options' => ['X'],
                ],
                'recipient' => [
                    'name' => (string) ($order['client_name'] ?? 'Client'),
                    'contactPerson' => (string) ($order['client_name'] ?? 'Client'),
                    'phone' => $this->normalizePhone((string) ($order['phone'] ?? '')),
                    'email' => (string) ($order['email'] ?? ''),
                    'address' => [
                        'county' => $county,
                        'locality' => $city,
                        'street' => (string) $street,
                        'streetNo' => $streetNo,
                        'zipCode' => $zipCode,
                    ],
                ],
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function buildSamedayAwb(array $order, array $options = []): array
    {
        $cfg = FulfillmentConfig::sameday();
        $parsed = $this->parseOrderNotes((string) ($order['notes'] ?? ''));
        $paymentMethod = mb_strtolower(trim((string) ($order['payment_status'] ?? 'ramburs')));
        $total = round((float) ($order['total_amount'] ?? 0), 2);
        $isCod = in_array($paymentMethod, ['ramburs'], true);
        $county = $this->resolveCounty($parsed, $order);
        $city = $parsed['city'] !== '' ? $parsed['city'] : 'Timisoara';
        $address = $options['address'] ?? $parsed['address'] ?? ('Str. ' . $city);

        return [
            'pickupPoint' => (int) ($cfg['pickup_point_id'] ?? 0),
            'contactPerson' => (string) ($order['client_name'] ?? 'Client'),
            'packageType' => 0,
            'packageNumber' => 1,
            'packageWeight' => max(1, (float) ($options['weight'] ?? 2)),
            'service' => (int) ($cfg['service_id'] ?? 7),
            'awbPayment' => 1,
            'cashOnDelivery' => $isCod ? $total : 0,
            'insuredValue' => $total,
            'thirdPartyPickup' => 0,
            'awbRecipient' => [
                'name' => (string) ($order['client_name'] ?? 'Client'),
                'phone' => $this->normalizePhone((string) ($order['phone'] ?? '')),
                'email' => (string) ($order['email'] ?? ''),
                'county' => $county,
                'city' => $city,
                'address' => (string) $address,
                'postalCode' => $parsed['postal_code'] !== '' ? $parsed['postal_code'] : '300000',
            ],
            'parcels' => [[
                'weight' => max(1, (float) ($options['weight'] ?? 2)),
            ]],
            'observation' => 'Comanda ' . ($order['order_number'] ?? ''),
        ];
    }

    /**
     * @return array{client_name:string,phone:string,email:string,county:string,city:string,postal_code:string,address:string,street:string,street_no:string,payment_label:string}
     */
    public function parseOrderNotes(string $notes): array
    {
        $result = [
            'client_name' => '',
            'phone' => '',
            'email' => '',
            'county' => '',
            'city' => '',
            'postal_code' => '',
            'address' => '',
            'street' => '',
            'street_no' => '',
            'payment_label' => '',
        ];

        foreach (preg_split('/\R/u', $notes) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^Client:\s*(.+)$/iu', $line, $m) === 1) {
                $result['client_name'] = trim((string) ($m[1] ?? ''));
            } elseif (preg_match('/^Telefon:\s*(.+)$/iu', $line, $m) === 1) {
                $result['phone'] = trim((string) ($m[1] ?? ''));
            } elseif (preg_match('/^Email:\s*(.+)$/iu', $line, $m) === 1) {
                $result['email'] = trim((string) ($m[1] ?? ''));
            } elseif (preg_match('/^Judet\/Oras:\s*(.+)$/iu', $line, $m) === 1) {
                $result['county'] = trim((string) ($m[1] ?? ''));
            } elseif (preg_match('/^Localitate:\s*(.+)$/iu', $line, $m) === 1) {
                $result['city'] = trim((string) ($m[1] ?? ''));
            } elseif (preg_match('/^Cod postal:\s*(.+)$/iu', $line, $m) === 1) {
                $result['postal_code'] = trim((string) ($m[1] ?? ''));
            } elseif (preg_match('/^Plata:\s*(.+)$/iu', $line, $m) === 1) {
                $result['payment_label'] = trim((string) ($m[1] ?? ''));
            } elseif (preg_match('/^(?:Adresa|Address|Livrare):\s*(.+)$/iu', $line, $m) === 1) {
                $result['address'] = trim((string) ($m[1] ?? ''));
            }
        }

        if ($result['address'] !== '' && $result['street'] === '') {
            $result['street'] = $result['address'];
        }

        return $result;
    }

    /**
     * @param array<string, string> $parsed
     * @param array<string, mixed> $order
     */
    private function resolveCounty(array $parsed, array $order): string
    {
        $county = trim($parsed['county'] ?? '');
        if ($county !== '') {
            if (isset(self::COUNTY_MAP[strtoupper($county)])) {
                return self::COUNTY_MAP[strtoupper($county)];
            }

            return $county;
        }

        return 'Timis';
    }

    private function mapSmartBillPaymentType(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'card_online', 'card_fizic', 'card' => 'Card',
            'numerar' => 'Chitanta',
            'confirmata', 'platita' => 'Ordin plata',
            default => 'Ramburs',
        };
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone) ?? $phone;
        if ($phone === '') {
            return '0700000000';
        }

        return mb_substr($phone, 0, 16);
    }
}
