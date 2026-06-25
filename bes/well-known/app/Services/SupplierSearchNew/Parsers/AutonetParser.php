<?php

namespace App\Services\SupplierSearchNew\Parsers;

use App\Services\SupplierSearchNew\Contracts\SupplierParserInterface;
use App\Services\Autonet\AutonetService;
use Illuminate\Support\Facades\DB;

class AutonetParser implements SupplierParserInterface
{
    /** @var array<string, array{manufacturer: mixed, db_name: mixed, order_code: mixed}> */
    protected array $requestRowMap = [];

    public function __construct(
        protected AutonetService $autonetService
    ) {}

    public function setRequestRowMap(array $rowMap): void
    {
        $this->requestRowMap = $rowMap;
    }

    public function parse(string $query, $rawResponse, string $rawBody): array
    {
        $data = is_array($rawResponse) ? $rawResponse : (json_decode($rawBody, true) ?: []);
        if (!is_array($data)) {
            return [];
        }

        $articles = isset($data[0]) ? $data : [$data];
        $entries = [];
        $metaCache = [];

        foreach ($articles as $article) {
            $apiPartNoRaw = trim((string) ($article['PartNo'] ?? ''));
            $apiPartNo = $this->autonetService->mapAutonetLemforderPartNoToCatalogStyle(
                $apiPartNoRaw
            );
            if ($apiPartNo === '') {
                continue;
            }

            $normalizedCode = $this->autonetService->normalizeCode($apiPartNo);
            $normalizedBaseCode = str_replace([' ', '-', '/', '|', '\\'], '', $normalizedCode);
            if ($normalizedBaseCode === '') {
                continue;
            }

            $cacheKey = $normalizedBaseCode . '|' . $normalizedCode;
            if (!isset($metaCache[$cacheKey])) {
                $metaCache[$cacheKey] = $this->resolveProductMeta(
                    $normalizedBaseCode,
                    $normalizedCode,
                    $apiPartNo
                );
            }
            $meta = $metaCache[$cacheKey];

            $orderCode = $meta['order_code'] ?? $normalizedCode;
            $deliveryData = $article['DeliveryData'] ?? [];
            $variants = [];

            if (empty($deliveryData) && isset($article['PriceWoVat']) && $article['PriceWoVat'] > 0) {
                $variants[] = [
                    'supplier_stock' => 0,
                    'price' => (float) ($article['PriceWoVat'] ?? 0),
                    'currency' => $article['Currency'] ?? 'RON',
                    'order_code' => $orderCode,
                    'autonet_partno' => $apiPartNoRaw !== '' ? $apiPartNoRaw : $apiPartNo,
                    'depot' => '',
                    'is_blocked' => false,
                    'delivery' => ['info_text' => '', 'plant_name' => null],
                    'multiple_qty' => 1,
                ];
            } else {
                $rows = array_values(array_filter($deliveryData, static function ($d): bool {
                    return is_array($d);
                }));
                if ($rows === []) {
                    // nothing
                } elseif (count($rows) === 1) {
                    $d = $rows[0];
                    $variants[] = [
                        'supplier_stock' => (int) ($d['Quantity'] ?? 0),
                        'price' => (float) ($article['PriceWoVat'] ?? 0),
                        'currency' => $article['Currency'] ?? 'RON',
                        'order_code' => $orderCode,
                        'autonet_partno' => $apiPartNoRaw !== '' ? $apiPartNoRaw : $apiPartNo,
                        'depot' => $d['Code'] ?? '',
                        'is_blocked' => false,
                        'delivery' => [
                            'info_text' => $d['DeliveryDate'] ?? '',
                            'plant_name' => null,
                        ],
                        'multiple_qty' => 1,
                    ];
                } else {
                    // Same idea as AutototalParser: one variant, summed qty, depots joined with " + "
                    $aggregatedQty = 0;
                    $codes = [];
                    $firstDate = '';
                    foreach ($rows as $d) {
                        $aggregatedQty += (int) ($d['Quantity'] ?? 0);
                        $c = trim((string) ($d['Code'] ?? ''));
                        if ($c !== '') {
                            $codes[$c] = true;
                        }
                        if ($firstDate === '' && !empty($d['DeliveryDate'])) {
                            $firstDate = (string) $d['DeliveryDate'];
                        }
                    }
                    $variants[] = [
                        'supplier_stock' => $aggregatedQty,
                        'price' => (float) ($article['PriceWoVat'] ?? 0),
                        'currency' => $article['Currency'] ?? 'RON',
                        'order_code' => $orderCode,
                        'autonet_partno' => $apiPartNoRaw !== '' ? $apiPartNoRaw : $apiPartNo,
                        'depot' => $codes !== [] ? implode(' + ', array_keys($codes)) : '',
                        'is_blocked' => false,
                        'delivery' => [
                            'info_text' => $firstDate,
                            'plant_name' => null,
                        ],
                        'multiple_qty' => 1,
                    ];
                }
            }

            if (!empty($variants)) {
                $entries[] = [
                    'code' => $normalizedBaseCode,
                    'mfrpn' => $normalizedBaseCode,
                    'manufacturer' => $meta['manufacturer'] ?? null,
                    'db_name' => $meta['db_name'] ?? null,
                    'name' => null,
                    'ean' => null,
                    'material' => null,
                    'supplier_name' => 'autonet',
                    'variants' => $variants,
                ];
            }
        }

        return $entries;
    }

    /**
     * Resolve product metadata for Autonet results using the same priority as legacy flow:
     * parts_catalog first, then autonet_qwp_data fallback.
     */
    private function resolveProductMeta(
        string $normalizedBaseCode,
        string $normalizedCode,
        string $apiPartNo = ''
    ): array
    {
        if (isset($this->requestRowMap[$normalizedBaseCode])) {
            return $this->requestRowMap[$normalizedBaseCode];
        }

        if ($apiPartNo !== '') {
            $normalizedApiPartNo = preg_replace('/[.\s\-\/|\\\\]+/', '', $apiPartNo);
            if ($normalizedApiPartNo !== '' && isset($this->requestRowMap[$normalizedApiPartNo])) {
                return $this->requestRowMap[$normalizedApiPartNo];
            }
        }

        // Legacy parity: if row map has no metadata, still try QWP lookup directly
        // by ArtNr/RefNr so pure QWP results are not dropped as unknown brand.
        $qwpCandidates = array_values(array_unique(array_filter([
            $apiPartNo,
            $normalizedCode,
            $normalizedBaseCode,
        ])));
        if (!empty($qwpCandidates)) {
            $qwpRow = DB::table('autonet_qwp_data')
                ->whereIn('ArtNr', $qwpCandidates)
                ->orWhereIn('RefNr', $qwpCandidates)
                ->first();
            if ($qwpRow) {
                return [
                    'manufacturer' => 'QWP',
                    'db_name' => 'QWP',
                    'order_code' => (string) ($qwpRow->ArtNr ?? $normalizedCode),
                ];
            }
        }

        return [
            'manufacturer' => null,
            'db_name' => null,
            'order_code' => $normalizedCode,
        ];
    }
}
