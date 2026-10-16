<?php
declare(strict_types=1);

namespace Besoiu\Modules\SupplierSearch\Service\Parsers;

/**
 * Transformă fișele JSON din webshop-ul Bardi Auto în rânduri de căutare.
 * Prețul folosit este netul de achiziție (billing, apoi unit).
 */
final class BardiAutoParser
{
    public static function normalizeCode(string $code): string
    {
        return preg_replace('/[.\s\-\/|\\\\]+/', '', trim($code)) ?? '';
    }

    /**
     * @param array{products?:array<int,array<string,mixed>>}|mixed $rawResponse
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $query, mixed $rawResponse): array
    {
        if (!is_array($rawResponse)) {
            return [];
        }

        $products = is_array($rawResponse['products'] ?? null) ? $rawResponse['products'] : [];
        $entries = [];

        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }

            $pn = trim((string) ($product['pn'] ?? $product['_match_code'] ?? ''));
            if ($pn === '') {
                continue;
            }

            $price = $this->netPrice(is_array($product['prices'] ?? null) ? $product['prices'] : []);
            if ($price <= 0) {
                continue;
            }

            $brand = trim((string) ($product['brand'] ?? $product['_match_brand'] ?? ''));
            if (strtoupper($brand) === 'DEPO' || strtoupper($brand) === 'LORO') {
                $brand = 'ABAKUS';
            }

            $name = $this->productName($product);
            $baseCode = self::normalizeCode($pn);
            if ($baseCode === '') {
                continue;
            }

            $variants = $this->variants($product, $pn, $price);
            if ($variants === []) {
                continue;
            }

            if (!isset($entries[$baseCode])) {
                $entries[$baseCode] = [
                    'code' => $baseCode,
                    'mfrpn' => $baseCode,
                    'manufacturer' => $brand !== '' ? $brand : null,
                    'db_name' => null,
                    'name' => $name !== '' ? $name : null,
                    'ean' => null,
                    'material' => $pn,
                    'supplier_name' => 'bardi',
                    'variants' => [],
                ];
            }

            foreach ($variants as $variant) {
                $entries[$baseCode]['variants'][] = $variant;
            }
        }

        return array_values(array_filter(
            $entries,
            static fn (array $entry): bool => !empty($entry['variants'])
        ));
    }

    /**
     * @param array<string, mixed> $prices
     */
    private function netPrice(array $prices): float
    {
        $net = is_array($prices['net'] ?? null) ? $prices['net'] : [];
        $billing = $this->amount($net['billing'] ?? 0);
        if ($billing > 0) {
            return $billing;
        }

        return $this->amount($net['unit'] ?? 0);
    }

    /**
     * @param array<string, mixed> $product
     * @return array<int, array<string, mixed>>
     */
    private function variants(array $product, string $pn, float $price): array
    {
        $lines = is_array($product['inStock'] ?? null) ? $product['inStock'] : [];
        $qty = 0;
        $depot = 'Bardi';
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $lineQty = (int) ($line['inStock'] ?? 0);
            if ($lineQty <= 0) {
                continue;
            }
            $qty += $lineQty;
            $branch = trim((string) ($line['branch'] ?? ''));
            if ($branch === '') {
                continue;
            }
            if ($depot === 'Bardi' || $this->isTimisoara($branch)) {
                $depot = $branch;
            }
        }

        return [$this->variant($pn, $price, $qty, $depot, $this->deliveryHint($product))];
    }

    /** @return array<string, mixed> */
    private function variant(string $pn, float $price, int $qty, string $branch, string $delivery): array
    {
        return [
            'supplier_stock' => $qty,
            'price' => $price,
            'currency' => 'RON',
            'order_code' => $pn,
            'variant_code' => $pn,
            'api_lookup_code' => $pn,
            'depot' => $branch,
            'is_blocked' => false,
            'delivery' => [
                'info_text' => $delivery,
                'plant_name' => $branch,
            ],
            'multiple_qty' => 1,
        ];
    }

    /** @param array<string, mixed> $product */
    private function deliveryHint(array $product): string
    {
        $table = $product['deliveryTable'] ?? null;
        if (is_string($table)) {
            $plain = $this->plain($table);

            return $plain !== '' ? $plain : 'Verifica stoc';
        }
        if (!is_array($table)) {
            return 'Verifica stoc';
        }

        $items = $table['personaly']['items'] ?? [];
        if (is_array($items)) {
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                if (!$this->isTimisoara((string) ($item['branch'] ?? ''))) {
                    continue;
                }
                $plain = $this->plain((string) ($item['text'] ?? ''));
                if ($plain !== '') {
                    return $plain;
                }
            }
        }

        $delivery = $this->plain((string) ($table['delivery']['info'] ?? ''));

        return $delivery !== '' ? $delivery : 'Verifica stoc';
    }

    private function isTimisoara(string $branch): bool
    {
        $folded = strtolower(str_replace(['ș', 'ş', 'ț', 'ţ', 'ă', 'â', 'î'], ['s', 's', 't', 't', 'a', 'a', 'i'], $branch));

        return str_contains($folded, 'timis');
    }

    private function plain(string $html): string
    {
        $text = str_ireplace(['<br>', '<br/>', '<br />'], ' ', $html);
        $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s*\(.*$/', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /** @param array<string, mixed> $product */
    private function productName(array $product): string
    {
        $name = $product['name'] ?? $product['_match_name'] ?? '';
        if (is_array($name)) {
            $name = $name[0] ?? '';
        }

        return trim((string) $name);
    }

    private function amount(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $raw = trim((string) $value);
        $raw = str_ireplace(['lei', 'ron'], '', $raw);
        $raw = str_replace(' ', '', $raw);
        if ($raw === '') {
            return 0.0;
        }
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (str_contains($raw, ',')) {
            $raw = str_replace(',', '.', $raw);
        }

        return (float) $raw;
    }
}
