<?php

declare(strict_types=1);

namespace Evasystem\Services\SupplierSearch\Parsers;

use Evasystem\Services\SupplierSearch\Clients\AutonetClient;
use PDO;

final class AutonetParser
{
    /** @var array<string, array<string, mixed>> */
    private array $requestRowMap = [];

    public function __construct(private readonly ?PDO $pdo = null)
    {
    }

    /** @param array<string, array<string, mixed>> $rowMap */
    public function setRequestRowMap(array $rowMap): void
    {
        $this->requestRowMap = $rowMap;
    }

    /** @return array<int, array<string, mixed>> */
    public function parse(string $query, mixed $rawResponse, string $rawBody = ''): array
    {
        $data = is_array($rawResponse) ? $rawResponse : (json_decode($rawBody, true) ?: []);
        if (!is_array($data)) {
            return [];
        }

        $articles = isset($data[0]) ? $data : [$data];
        $entries = [];
        $metaCache = [];

        foreach ($articles as $article) {
            if (!is_array($article)) {
                continue;
            }

            $apiPartNoRaw = trim((string) ($article['PartNo'] ?? ''));
            $apiPartNo = AutonetClient::mapAutonetLemforderPartNoToCatalogStyle($apiPartNoRaw);
            if ($apiPartNo === '') {
                continue;
            }

            $normalizedCode = AutonetClient::normalizeCode($apiPartNo);
            $normalizedBaseCode = str_replace([' ', '-', '/', '|', '\\'], '', $normalizedCode);
            if ($normalizedBaseCode === '') {
                continue;
            }

            $cacheKey = $normalizedBaseCode . '|' . $normalizedCode;
            if (!isset($metaCache[$cacheKey])) {
                $metaCache[$cacheKey] = $this->resolveProductMeta($normalizedBaseCode, $normalizedCode, $apiPartNo);
            }
            $meta = $metaCache[$cacheKey];
            $orderCode = $meta['order_code'] ?? $normalizedCode;
            $deliveryData = $article['DeliveryData'] ?? [];
            $variants = [];

            if ($deliveryData === [] && isset($article['PriceWoVat']) && (float) $article['PriceWoVat'] > 0) {
                $variants[] = $this->buildVariant($article, $orderCode, $apiPartNoRaw, $apiPartNo, 0, '');
            } else {
                $rows = array_values(array_filter($deliveryData, static fn ($d) => is_array($d)));
                if (count($rows) === 1) {
                    $d = $rows[0];
                    $variants[] = $this->buildVariant(
                        $article,
                        $orderCode,
                        $apiPartNoRaw,
                        $apiPartNo,
                        (int) ($d['Quantity'] ?? 0),
                        (string) ($d['Code'] ?? ''),
                        (string) ($d['DeliveryDate'] ?? '')
                    );
                } elseif ($rows !== []) {
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
                    $variants[] = $this->buildVariant(
                        $article,
                        $orderCode,
                        $apiPartNoRaw,
                        $apiPartNo,
                        $aggregatedQty,
                        $codes !== [] ? implode(' + ', array_keys($codes)) : '',
                        $firstDate
                    );
                }
            }

            if ($variants !== []) {
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

    /** @return array<string, mixed> */
    private function buildVariant(
        array $article,
        string $orderCode,
        string $apiPartNoRaw,
        string $apiPartNo,
        int $stock,
        string $depot,
        string $deliveryDate = ''
    ): array {
        return [
            'supplier_stock' => $stock,
            'price' => (float) ($article['PriceWoVat'] ?? 0),
            'currency' => $article['Currency'] ?? 'RON',
            'order_code' => $orderCode,
            'autonet_partno' => $apiPartNoRaw !== '' ? $apiPartNoRaw : $apiPartNo,
            'depot' => $depot,
            'is_blocked' => false,
            'delivery' => ['info_text' => $deliveryDate, 'plant_name' => null],
            'multiple_qty' => 1,
        ];
    }

    /** @return array{manufacturer:?string,db_name:?string,order_code:string} */
    private function resolveProductMeta(string $normalizedBaseCode, string $normalizedCode, string $apiPartNo = ''): array
    {
        if (isset($this->requestRowMap[$normalizedBaseCode])) {
            return $this->requestRowMap[$normalizedBaseCode];
        }

        if ($apiPartNo !== '') {
            $normalizedApiPartNo = preg_replace('/[.\s\-\/|\\\\]+/', '', $apiPartNo) ?? '';
            if ($normalizedApiPartNo !== '' && isset($this->requestRowMap[$normalizedApiPartNo])) {
                return $this->requestRowMap[$normalizedApiPartNo];
            }
        }

        $qwpCandidates = array_values(array_unique(array_filter([$apiPartNo, $normalizedCode, $normalizedBaseCode])));
        if ($qwpCandidates !== [] && $this->pdo !== null) {
            $ph = implode(',', array_fill(0, count($qwpCandidates), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT ArtNr FROM autonet_qwp_data WHERE ArtNr IN ($ph) OR RefNr IN ($ph) LIMIT 1"
            );
            $stmt->execute(array_merge($qwpCandidates, $qwpCandidates));
            $qwpRow = $stmt->fetch(PDO::FETCH_OBJ);
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
