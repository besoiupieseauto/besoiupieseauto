<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Core\Comenzi\OrderItemsModel;
use Config\Database;
use PDO;

/**
 * Date și payload Chart.js pentru tab-uri grafic pagină produs.
 */
final class ProductTabChartService
{
    /** @return array<string, array{label:string, hint:string, default_type:string}> */
    public static function sourceCatalog(): array
    {
        return [
            'price_diversity' => [
                'label' => 'Diversitate preț',
                'hint' => 'Preț magazin, bază, furnizor și media categoriei',
                'default_type' => 'bar',
            ],
            'purchase_stats' => [
                'label' => 'Statistici cumpărări',
                'hint' => 'Cantitate vândută pe luni (comenzi site)',
                'default_type' => 'line',
            ],
            'product_fields' => [
                'label' => 'Câmpuri produs (liber)',
                'hint' => 'Alegi manual ce câmpuri apar pe grafic',
                'default_type' => 'bar',
            ],
            'price_vs_category' => [
                'label' => 'Preț vs categorie',
                'hint' => 'Produsul față de min/medie/max în aceeași categorie',
                'default_type' => 'bar',
            ],
        ];
    }

    /** @return array<string, string> */
    public static function typeCatalog(): array
    {
        return [
            'bar' => 'Bară (coloane)',
            'line' => 'Linie (evoluție)',
            'doughnut' => 'Donut (proporții)',
        ];
    }

    /** @return array<string, string> */
    public static function periodCatalog(): array
    {
        return [
            '30d' => '30 zile',
            '90d' => '90 zile',
            '6m' => '6 luni',
            '12m' => '12 luni',
        ];
    }

    /**
     * Metrici disponibile per sursă grafic (bifabile în admin).
     *
     * @return array<string, array<string, array{label:string, hint:string}>>
     */
    public static function metricCatalog(): array
    {
        return [
            'price_diversity' => [
                'price_sale' => [
                    'label' => 'Preț magazin',
                    'hint' => 'pPrice — prețul afișat pe site',
                ],
                'price_base' => [
                    'label' => 'Preț bază',
                    'hint' => 'pBasePrice — înainte de adaos',
                ],
                'price_supplier' => [
                    'label' => 'Preț furnizor net',
                    'hint' => 'raw_json.supplier_price.net_price',
                ],
                'category_avg' => [
                    'label' => 'Medie categorie',
                    'hint' => 'Media prețurilor din aceeași pCategory',
                ],
            ],
            'price_vs_category' => [
                'cat_min' => [
                    'label' => 'Minim categorie',
                    'hint' => 'Cel mai mic preț din categorie',
                ],
                'product_price' => [
                    'label' => 'Acest produs',
                    'hint' => 'Prețul produsului curent',
                ],
                'cat_avg' => [
                    'label' => 'Medie categorie',
                    'hint' => 'Preț mediu în categorie',
                ],
                'cat_max' => [
                    'label' => 'Maxim categorie',
                    'hint' => 'Cel mai mare preț din categorie',
                ],
            ],
            'purchase_stats' => [
                'qty_sold' => [
                    'label' => 'Bucăți vândute',
                    'hint' => 'Cantitate totală pe lună (order_items)',
                ],
                'revenue' => [
                    'label' => 'Valoare (RON)',
                    'hint' => 'Suma line_total pe lună',
                ],
            ],
            'product_fields' => [],
        ];
    }

    /**
     * @return array<string, array{label:string, hint:string, group?:string}>
     */
    public static function extendedMetricCatalog(string $source): array
    {
        $out = self::metricCatalog()[$source] ?? [];
        foreach ($out as $id => $meta) {
            $out[$id]['group'] = 'chart';
        }

        foreach (ProductDescriptionTabsService::fieldCatalog() as $fieldId => $meta) {
            $key = 'field_' . $fieldId;
            if (isset($out[$key])) {
                continue;
            }
            $out[$key] = [
                'label' => (string) ($meta['label'] ?? $fieldId),
                'hint' => (string) ($meta['hint'] ?? ''),
                'group' => 'fields',
            ];
        }

        return $out;
    }

    /** @return list<array{id:string, label:string, enabled:bool}> */
    public static function defaultMetricsForSource(string $source): array
    {
        if ($source === 'product_fields') {
            return [
                ['id' => 'field_denumire', 'label' => 'Denumire / descriere scurtă', 'enabled' => true],
                ['id' => 'field_cod', 'label' => 'Cod articol / OEM', 'enabled' => true],
                ['id' => 'field_brand_piesa', 'label' => 'Producător piesă', 'enabled' => true],
            ];
        }

        $catalog = self::metricCatalog()[$source] ?? [];
        $out = [];
        foreach ($catalog as $id => $meta) {
            $out[] = [
                'id' => $id,
                'label' => (string) ($meta['label'] ?? $id),
                'enabled' => true,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $tab
     * @return list<array{id:string, label:string}>
     */
    private function resolveEnabledMetrics(array $tab, string $source): array
    {
        $catalog = self::extendedMetricCatalog($source);
        $configured = is_array($tab['chart_metrics'] ?? null) ? $tab['chart_metrics'] : [];

        if ($configured === []) {
            $configured = self::defaultMetricsForSource($source);
        }

        $out = [];
        foreach ($configured as $metric) {
            if (!is_array($metric) || empty($metric['enabled'])) {
                continue;
            }
            $id = trim((string) ($metric['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            if (!str_starts_with($id, 'custom_') && !isset($catalog[$id])) {
                continue;
            }
            $label = trim((string) ($metric['label'] ?? ''));
            if ($label === '') {
                $label = (string) ($catalog[$id]['label'] ?? $id);
            }
            $row = ['id' => $id, 'label' => $label];
            if (str_starts_with($id, 'custom_')) {
                $row['static_value'] = trim((string) ($metric['static_value'] ?? ''));
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $tab
     * @return array<string, mixed>|null
     */
    public function buildPayload(array $product, array $tab, bool $allowSample = false, array $ctx = []): ?array
    {
        $source = trim((string) ($tab['chart_source'] ?? 'price_diversity'));
        $type = trim((string) ($tab['chart_type'] ?? ''));
        $period = trim((string) ($tab['chart_period'] ?? '6m'));
        $title = trim((string) ($tab['chart_title'] ?? ''));

        $catalog = self::sourceCatalog();
        if (!isset($catalog[$source])) {
            $source = 'price_diversity';
        }
        if ($type === '' || !isset(self::typeCatalog()[$type])) {
            $type = (string) ($catalog[$source]['default_type'] ?? 'bar');
        }
        if ($title === '') {
            $title = (string) ($catalog[$source]['label'] ?? 'Grafic');
        }

        $payload = match ($source) {
            'purchase_stats' => $this->buildPurchaseStats($product, $tab, $period, $allowSample),
            'price_vs_category' => $this->buildPriceVsCategory($product, $tab, $allowSample, $ctx),
            'product_fields' => $this->buildConfigurableMetricsChart($product, $tab, $allowSample, $ctx, 'product_fields'),
            default => $this->buildConfigurableMetricsChart($product, $tab, $allowSample, $ctx, 'price_diversity'),
        };

        if ($payload === null) {
            return null;
        }

        $payload['type'] = $type;
        $payload['title'] = $title;
        $payload['source'] = $source;

        return $payload;
    }

    /**
     * Grafic bar configurabil: metrici preț + câmpuri produs + valori manuale.
     *
     * @param array<string, mixed> $product
     * @param array<string, mixed> $tab
     * @param array<string, mixed> $ctx
     */
    private function buildConfigurableMetricsChart(
        array $product,
        array $tab,
        bool $allowSample,
        array $ctx,
        string $source
    ): ?array {
        $enabled = $this->resolveEnabledMetrics($tab, $source);
        if ($enabled === []) {
            return null;
        }

        $sale = $this->parsePrice($product['pPrice'] ?? null);
        $base = $this->parsePrice($product['pBasePrice'] ?? null);
        $supplier = $this->supplierNetPrice($product);
        $category = trim((string) ($product['pCategory'] ?? ''));
        $catAvg = 0.0;
        if ($category !== '') {
            $catAvg = $this->categoryPriceStats($category)['avg'];
        }

        $priceValues = [
            'price_sale' => $sale,
            'price_base' => ($base > 0 && abs($base - $sale) > 0.009) ? $base : 0.0,
            'price_supplier' => $supplier,
            'category_avg' => $catAvg,
        ];
        $priceSamples = [
            'price_sale' => 189.99,
            'price_base' => 142.50,
            'price_supplier' => 118.40,
            'category_avg' => 165.00,
        ];

        $fieldValues = $this->resolveChartFieldValues($product, $ctx);
        $points = [];

        foreach ($enabled as $metric) {
            $id = $metric['id'];
            $val = 0.0;

            if (str_starts_with($id, 'custom_')) {
                $val = $this->parsePrice($metric['static_value'] ?? null);
                if ($val <= 0 && $allowSample) {
                    $val = 99.0;
                }
            } elseif (str_starts_with($id, 'field_')) {
                $fieldId = substr($id, 6);
                $text = trim((string) ($fieldValues[$fieldId] ?? ''));
                if ($text === '') {
                    if ($allowSample) {
                        $val = 1.0;
                    }
                } else {
                    $val = $this->numericFromText($text);
                    if ($val <= 0) {
                        $val = 1.0;
                    }
                }
            } else {
                $val = (float) ($priceValues[$id] ?? 0);
                if ($val <= 0 && $allowSample) {
                    $val = (float) ($priceSamples[$id] ?? 0);
                }
            }

            if ($val <= 0) {
                continue;
            }

            $points[] = [$metric['label'], $val];
        }

        if ($points === []) {
            return null;
        }

        return $this->barPayload(
            array_column($points, 0),
            [
                [
                    'label' => 'RON',
                    'data' => array_map(static fn (array $row): float => (float) $row[1], $points),
                    'backgroundColor' => $this->palette(count($points)),
                ],
            ],
            true
        );
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $ctx
     * @return array<string, string>
     */
    private function resolveChartFieldValues(array $product, array $ctx): array
    {
        $tabsService = new ProductDescriptionTabsService();
        $mergedCtx = array_merge([
            'product' => $product,
            'piece_name' => trim((string) ($product['pName'] ?? '')),
            'pBrand' => trim((string) ($product['pBrand'] ?? '')),
            'pCode' => trim((string) ($product['pCode'] ?? '')),
            'pMarca' => trim((string) ($product['pMarca'] ?? '')),
            'pModel' => trim((string) ($product['pModel'] ?? '')),
            'pMotorizare' => trim((string) ($product['pMotorizare'] ?? '')),
            'pCategory' => trim((string) ($product['pCategory'] ?? '')),
            'pSubcategory' => trim((string) ($product['pSubcategory'] ?? '')),
            'pCar' => trim((string) ($product['pCar'] ?? '')),
            'pCompatibilitati' => trim((string) ($product['pCompatibilitati'] ?? '')),
            'pOem' => trim((string) ($product['pOem'] ?? '')),
            'pShipping' => trim((string) ($product['pShipping'] ?? '')),
            'pWarranty' => trim((string) ($product['pWarranty'] ?? '')),
            'pReturn' => trim((string) ($product['pReturn'] ?? '')),
            'specs_text' => trim((string) ($product['pNote'] ?? '')),
            'description_html' => (string) ($ctx['description_html'] ?? ''),
            'entries' => is_array($ctx['entries'] ?? null) ? $ctx['entries'] : [],
        ], $ctx);

        return $tabsService->fieldValuesForChart($mergedCtx);
    }

    private function numericFromText(string $text): float
    {
        $text = trim($text);
        if ($text === '') {
            return 0.0;
        }

        $direct = $this->parsePrice($text);
        if ($direct > 0) {
            return $direct;
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)/', $text, $match)) {
            return $this->parsePrice($match[1]);
        }

        return 0.0;
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $tab
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>|null
     */
    private function buildPriceVsCategory(array $product, array $tab, bool $allowSample, array $ctx = []): ?array
    {
        $enabled = $this->resolveEnabledMetrics($tab, 'price_vs_category');
        if ($enabled === []) {
            return null;
        }

        $category = trim((string) ($product['pCategory'] ?? ''));
        $sale = $this->parsePrice($product['pPrice'] ?? null);

        $min = 0.0;
        $avg = 0.0;
        $max = 0.0;
        if ($category !== '') {
            $stats = $this->categoryPriceStats($category);
            $min = $stats['min'];
            $avg = $stats['avg'];
            $max = $stats['max'];
        }

        if (($sale <= 0 || $avg <= 0) && $allowSample) {
            $sale = 189.99;
            $min = 95.00;
            $avg = 165.00;
            $max = 320.00;
        }

        $values = [
            'cat_min' => $min,
            'product_price' => $sale,
            'cat_avg' => $avg,
            'cat_max' => $max,
        ];
        $colors = [
            'cat_min' => '#94a3b8',
            'product_price' => '#1abc9c',
            'cat_avg' => '#64748b',
            'cat_max' => '#cbd5e1',
        ];

        $labels = [];
        $data = [];
        $bg = [];
        foreach ($enabled as $metric) {
            $id = $metric['id'];
            $val = (float) ($values[$id] ?? 0);
            if ($val <= 0) {
                continue;
            }
            $labels[] = $metric['label'];
            $data[] = $val;
            $bg[] = $colors[$id] ?? '#64748b';
        }

        if ($labels === []) {
            return null;
        }

        return $this->barPayload(
            $labels,
            [
                [
                    'label' => 'RON',
                    'data' => $data,
                    'backgroundColor' => $bg,
                ],
            ],
            true
        );
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $tab
     * @return array<string, mixed>|null
     */
    private function buildPurchaseStats(array $product, array $tab, string $period, bool $allowSample): ?array
    {
        $enabled = $this->resolveEnabledMetrics($tab, 'purchase_stats');
        if ($enabled === []) {
            return null;
        }

        $enabledIds = array_column($enabled, 'id');
        $showQty = in_array('qty_sold', $enabledIds, true);
        $showRevenue = in_array('revenue', $enabledIds, true);
        if (!$showQty && !$showRevenue) {
            return null;
        }

        $months = $this->periodToMonths($period);
        $rows = $this->fetchPurchaseByMonth($product, $months);

        if ($rows === [] && $allowSample) {
            $rows = $this->samplePurchaseRows($months);
        }

        if ($rows === []) {
            return null;
        }

        $labels = array_column($rows, 'label');
        $qty = array_map(static fn (array $row): int => (int) $row['qty'], $rows);
        $revenue = array_map(static fn (array $row): float => (float) $row['revenue'], $rows);

        $labelQty = 'Bucăți vândute';
        $labelRevenue = 'Valoare (RON)';
        foreach ($enabled as $metric) {
            if ($metric['id'] === 'qty_sold') {
                $labelQty = $metric['label'];
            }
            if ($metric['id'] === 'revenue') {
                $labelRevenue = $metric['label'];
            }
        }

        $datasets = [];
        if ($showQty) {
            $datasets[] = [
                'label' => $labelQty,
                'data' => $qty,
                'borderColor' => '#1abc9c',
                'backgroundColor' => 'rgba(26, 188, 156, 0.15)',
                'fill' => true,
                'tension' => 0.35,
                'yAxisID' => 'y',
            ];
        }
        if ($showRevenue) {
            $datasets[] = [
                'label' => $labelRevenue,
                'data' => $revenue,
                'borderColor' => '#0ea5e9',
                'backgroundColor' => 'rgba(14, 165, 233, 0.08)',
                'fill' => false,
                'tension' => 0.35,
                'yAxisID' => $showQty ? 'y1' : 'y',
            ];
        }

        return [
            'labels' => $labels,
            'currency' => $showRevenue && !$showQty,
            'datasets' => $datasets,
            'dual_axis' => $showQty && $showRevenue,
        ];
    }

    /**
     * @param list<string> $labels
     * @param list<array<string, mixed>> $datasets
     * @return array<string, mixed>
     */
    private function barPayload(array $labels, array $datasets, bool $currency): array
    {
        return [
            'labels' => $labels,
            'currency' => $currency,
            'datasets' => $datasets,
        ];
    }

    /** @return array{min:float, max:float, avg:float} */
    private function categoryPriceStats(string $category): array
    {
        try {
            $pdo = Database::getDB();
            $stmt = $pdo->prepare(
                "SELECT MIN(CAST(REPLACE(REPLACE(pPrice, ',', '.'), ' ', '') AS DECIMAL(12,2))) AS min_p,
                        MAX(CAST(REPLACE(REPLACE(pPrice, ',', '.'), ' ', '') AS DECIMAL(12,2))) AS max_p,
                        AVG(CAST(REPLACE(REPLACE(pPrice, ',', '.'), ' ', '') AS DECIMAL(12,2))) AS avg_p
                 FROM produse
                 WHERE status <> '0'
                   AND pCategory = :cat
                   AND pPrice IS NOT NULL
                   AND TRIM(pPrice) <> ''
                   AND CAST(REPLACE(REPLACE(pPrice, ',', '.'), ' ', '') AS DECIMAL(12,2)) > 0"
            );
            $stmt->execute([':cat' => $category]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return ['min' => 0.0, 'max' => 0.0, 'avg' => 0.0];
            }

            return [
                'min' => round((float) ($row['min_p'] ?? 0), 2),
                'max' => round((float) ($row['max_p'] ?? 0), 2),
                'avg' => round((float) ($row['avg_p'] ?? 0), 2),
            ];
        } catch (\Throwable $e) {
            error_log('[ProductTabChartService] categoryPriceStats: ' . $e->getMessage());

            return ['min' => 0.0, 'max' => 0.0, 'avg' => 0.0];
        }
    }

    /**
     * @param array<string, mixed> $product
     * @return list<array{label:string, qty:int, revenue:float}>
     */
    private function fetchPurchaseByMonth(array $product, int $months): array
    {
        $model = new OrderItemsModel();
        if (!$model->tableExists()) {
            return [];
        }

        $randomnId = trim((string) ($product['randomn_id'] ?? ''));
        $productId = (int) ($product['id'] ?? 0);
        $oem = trim((string) ($product['pCode'] ?? ''));

        if ($randomnId === '' && $productId <= 0 && $oem === '') {
            return [];
        }

        try {
            $pdo = Database::getDB();
            $months = max(1, min(24, $months));
            $conds = [];
            $params = [];
            if ($randomnId !== '') {
                $conds[] = 'oi.randomn_id = :randomn_id';
                $params[':randomn_id'] = $randomnId;
            }
            if ($productId > 0) {
                $conds[] = 'oi.product_id = :product_id';
                $params[':product_id'] = $productId;
            }
            if ($oem !== '') {
                $conds[] = 'oi.oem_code = :oem_code';
                $params[':oem_code'] = $oem;
            }

            $sql = 'SELECT DATE_FORMAT(COALESCE(c.created_at, oi.created_at), "%Y-%m") AS ym,
                           SUM(oi.quantity) AS qty,
                           SUM(oi.line_total) AS revenue
                    FROM order_items oi
                    LEFT JOIN comenzi c ON c.id = oi.order_id
                    WHERE (' . implode(' OR ', $conds) . ')
                      AND COALESCE(c.created_at, oi.created_at) >= DATE_SUB(NOW(), INTERVAL ' . $months . ' MONTH)
                    GROUP BY ym
                    ORDER BY ym ASC';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $out = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!is_array($row)) {
                    continue;
                }
                $ym = (string) ($row['ym'] ?? '');
                if ($ym === '') {
                    continue;
                }
                $out[] = [
                    'label' => $this->formatMonthLabel($ym),
                    'qty' => (int) ($row['qty'] ?? 0),
                    'revenue' => round((float) ($row['revenue'] ?? 0), 2),
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            error_log('[ProductTabChartService] fetchPurchaseByMonth: ' . $e->getMessage());

            return [];
        }
    }

    /** @return list<array{label:string, qty:int, revenue:float}> */
    private function samplePurchaseRows(int $months): array
    {
        $samples = [
            ['qty' => 2, 'revenue' => 379.98],
            ['qty' => 5, 'revenue' => 949.95],
            ['qty' => 3, 'revenue' => 569.97],
            ['qty' => 8, 'revenue' => 1519.92],
            ['qty' => 4, 'revenue' => 759.96],
            ['qty' => 6, 'revenue' => 1139.94],
            ['qty' => 7, 'revenue' => 1329.93],
            ['qty' => 9, 'revenue' => 1709.91],
            ['qty' => 5, 'revenue' => 949.95],
            ['qty' => 11, 'revenue' => 2089.89],
            ['qty' => 8, 'revenue' => 1519.92],
            ['qty' => 10, 'revenue' => 1899.90],
        ];

        $count = min(max($months, 3), 12);
        $out = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            $ts = strtotime('-' . $i . ' months');
            if ($ts === false) {
                continue;
            }
            $idx = ($count - 1 - $i) % count($samples);
            $out[] = [
                'label' => $this->formatMonthLabel(date('Y-m', $ts)),
                'qty' => $samples[$idx]['qty'],
                'revenue' => $samples[$idx]['revenue'],
            ];
        }

        return $out;
    }

    private function formatMonthLabel(string $ym): string
    {
        $parts = explode('-', $ym);
        if (count($parts) !== 2) {
            return $ym;
        }

        static $months = [
            '01' => 'Ian', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
            '05' => 'Mai', '06' => 'Iun', '07' => 'Iul', '08' => 'Aug',
            '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dec',
        ];

        return ($months[$parts[1]] ?? $parts[1]) . ' ' . $parts[0];
    }

    private function periodToMonths(string $period): int
    {
        return match ($period) {
            '30d' => 1,
            '90d' => 3,
            default => (int) rtrim($period, 'm') ?: 6,
        };
    }

    /** @param array<string, mixed> $product */
    private function supplierNetPrice(array $product): float
    {
        $raw = json_decode((string) ($product['raw_json'] ?? ''), true);
        if (!is_array($raw)) {
            return 0.0;
        }

        $net = $raw['supplier_price']['net_price'] ?? null;
        if ($net === null || $net === '') {
            return 0.0;
        }

        return round((float) $net, 2);
    }

    private function parsePrice(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $normalized = str_replace([' ', ','], ['', '.'], (string) $value);
        if (!is_numeric($normalized)) {
            return 0.0;
        }

        return round((float) $normalized, 2);
    }

    /** @return list<string> */
    private function palette(int $count): array
    {
        $base = ['#1abc9c', '#0ea5e9', '#8b5cf6', '#f59e0b', '#64748b', '#ef4444'];
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = $base[$i % count($base)];
        }

        return $out;
    }
}
