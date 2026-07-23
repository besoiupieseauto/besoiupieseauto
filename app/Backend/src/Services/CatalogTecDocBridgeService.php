<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use PDO;
use Throwable;

/**
 * TecDoc + VIN → context vehicul și produse compatibile din stoc BD.
 */
final class CatalogTecDocBridgeService
{
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot
            ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
    }

    private function tecdocStockPath(): ?string
    {
        $candidates = [
            $this->projectRoot . '/app/Legacy/tecdoc_stock.php',
            (defined('BESOIU_LEGACY') ? (string) BESOIU_LEGACY : '') . '/tecdoc_stock.php',
            $this->projectRoot . '/system/tecdoc_stock.php',
            $this->projectRoot . '/app/Import/Scraper/system/tecdoc_stock.php',
        ];
        foreach ($candidates as $path) {
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{vehicle_context: string, products: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function enrich(string $message, array $filters = [], int $limit = 10, ?PDO $pdo = null): array
    {
        $pdo = $pdo ?? Database::getDB();
        $out = [
            'vehicle_context' => '',
            'products' => [],
            'meta' => [],
        ];

        $vin = trim((string) ($filters['vin'] ?? ''));
        if ($vin === '' && preg_match('/\b([A-HJ-NPR-Z0-9]{17})\b/i', $message, $m)) {
            $vin = strtoupper($m[1]);
        }

        $tecdocStock = $this->tecdocStockPath();
        if ($tecdocStock === null) {
            return $out;
        }

        try {
            require_once $tecdocStock;

            if ($vin !== '' && function_exists('tecdoc_public_search_by_vin')) {
                $searchFilters = array_filter([
                    'vin' => $vin,
                    'oem' => (string) ($filters['oem'] ?? ''),
                    'name' => (string) ($filters['name'] ?? ''),
                    'category' => (string) ($filters['category'] ?? ''),
                ]);
                $vinResult = tecdoc_public_search_by_vin($searchFilters);
                $out['meta']['vin'] = $vin;
                $out['meta']['car_id'] = (int) ($vinResult['car_id'] ?? 0);
                $out['vehicle_context'] = $this->formatVehicleContext($vinResult);
                $products = is_array($vinResult['products'] ?? null) ? $vinResult['products'] : [];
                $out['products'] = $this->normalizeProducts(array_slice($products, 0, $limit));

                return $out;
            }

            $oem = trim((string) ($filters['oem'] ?? ''));
            if ($oem !== '' && function_exists('tecdoc_public_search_core')) {
                $oemResult = tecdoc_public_search_core(['oem' => $oem, 'name' => (string) ($filters['name'] ?? '')]);
                $products = is_array($oemResult['products'] ?? null) ? $oemResult['products'] : [];
                $out['products'] = $this->normalizeProducts(array_slice($products, 0, $limit));
                $out['meta']['oem'] = $oem;
                $out['meta']['notice'] = (string) ($oemResult['notice'] ?? '');
            }
        } catch (Throwable $e) {
            $out['meta']['error'] = $e->getMessage();
        }

        return $out;
    }

    /** @param array<string, mixed> $vinResult */
    public function formatVehicleContext(array $vinResult): string
    {
        $vehicle = is_array($vinResult['vehicle'] ?? null) ? $vinResult['vehicle'] : [];
        if ($vehicle === [] && empty($vinResult['vin'])) {
            return '';
        }

        $lines = ['### Vehicul TecDoc (din VIN)'];
        if (!empty($vinResult['vin'])) {
            $lines[] = '- VIN: ' . (string) $vinResult['vin'];
        }
        if (!empty($vehicle['manu_name'])) {
            $lines[] = '- Marcă: ' . (string) $vehicle['manu_name'];
        }
        if (!empty($vehicle['model_name'])) {
            $lines[] = '- Model: ' . (string) $vehicle['model_name'];
        }
        if (!empty($vehicle['type_engine_name'])) {
            $lines[] = '- Motorizare: ' . (string) $vehicle['type_engine_name'];
        }
        if (!empty($vinResult['notice'])) {
            $lines[] = '- Notă: ' . mb_substr((string) $vinResult['notice'], 0, 200, 'UTF-8');
        }

        return implode("\n", $lines);
    }

    /** @param list<array<string, mixed>> $products @return list<array<string, mixed>> */
    private function normalizeProducts(array $products): array
    {
        $out = [];
        foreach ($products as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'randomn_id' => (string) ($row['randomn_id'] ?? $row['id'] ?? ''),
                'name' => (string) ($row['name'] ?? $row['pName'] ?? ''),
                'code' => (string) ($row['code'] ?? $row['pCode'] ?? ''),
                'oem' => (string) ($row['oem'] ?? $row['pOem'] ?? ''),
                'brand' => (string) ($row['brand'] ?? $row['pBrand'] ?? ''),
                'category' => (string) ($row['category'] ?? $row['pCategory'] ?? ''),
                'subcategory' => (string) ($row['subcategory'] ?? $row['pSubcategory'] ?? ''),
                'price' => (string) ($row['price'] ?? $row['pPrice'] ?? ''),
                'stock' => (int) ($row['stock'] ?? $row['pStock'] ?? 0),
                'in_stock' => (int) ($row['stock'] ?? $row['pStock'] ?? 0) > 0,
            ];
        }

        return $out;
    }
}
