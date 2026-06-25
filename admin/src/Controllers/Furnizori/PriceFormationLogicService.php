<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Furnizori;

use Evasystem\Core\Furnizori\PriceFormationLogicModel;

require_once dirname(__DIR__) . '/Produse/import_supplier_lib.php';

final class PriceFormationLogicService
{
    private const DEFAULT_SCAN_ORDER = ['AUTOTOTAL', 'AUTONET', 'MATEROM', 'AUTOPARTNER', 'ELIT', 'INTERCARS'];

    private const ALLOWED_BRAND_VERIFY = ['exact', 'contains', 'ignore'];
    private const ALLOWED_STOCK_VERIFY = ['skip_zero', 'include_zero', 'require_positive', 'require_known'];
    private const DEFAULT_COMPARE_TIER_SIZE = 3;

    private const ALLOWED_PRICE_STRATEGY = [
        'lowest_then_priority',
        'priority_first',
        'hierarchical_top3_lowest',
        'hierarchical_top3_first_stock',
    ];

    private ?array $cachedConfig = null;

    public function __construct(
        private readonly PriceFormationLogicModel $model = new PriceFormationLogicModel()
    ) {
    }

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        if ($this->cachedConfig !== null) {
            return $this->cachedConfig;
        }

        $stored = $this->model->loadConfig();
        $this->cachedConfig = $this->normalizeConfig(is_array($stored) ? $stored : []);

        return $this->cachedConfig;
    }

    /** @param array<string, mixed> $raw @return array<string, mixed> */
    public function saveConfig(array $raw): array
    {
        $config = $this->normalizeConfig($raw);
        if (!$this->model->saveConfig($config)) {
            throw new \RuntimeException('Configurarea logicii de pret nu a putut fi salvata.');
        }

        $this->cachedConfig = $config;
        import_price_logic_reset_cache();

        return $config;
    }

    /** @return array<string, int> */
    public function getPriorityMap(): array
    {
        $config = $this->getConfig();
        $map = [];
        $rank = 1;
        foreach ($config['scan_order'] as $code) {
            $map[$code] = $rank;
            $rank++;
        }

        return $map;
    }

    /** @return array<int, string> */
    public function getOmitSuppliers(): array
    {
        return $this->getConfig()['omit_suppliers'];
    }

    public function isSupplierOmitted(string $supplierCode): bool
    {
        $code = $this->normalizeSupplierCode($supplierCode);

        return $code !== '' && in_array($code, $this->getOmitSuppliers(), true);
    }

    public function isSupplierBlocked(string $supplierCode): bool
    {
        return import_furnizor_is_blocked($supplierCode);
    }

    /** @return array<string, array<int, string>> */
    public function getIgnoredBrandsBySupplier(): array
    {
        return $this->getConfig()['ignore_brands_by_supplier'] ?? [];
    }

    public function isBrandIgnoredForSupplier(string $supplierCode, string $brand): bool
    {
        $code = $this->normalizeSupplierCode($supplierCode);
        if ($code === '' || trim($brand) === '') {
            return false;
        }

        $ignored = $this->getIgnoredBrandsBySupplier()[$code] ?? [];
        foreach ($ignored as $pattern) {
            if ($this->brandMatchesIgnored($brand, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /** @param array{price?:float,supplier?:string,brand?:string}|null $existing */
    public function shouldReplacePrice(?array $existing, float $newPrice, string $newSupplier, ?string $newBrand = null): bool
    {
        if ($this->isSupplierBlocked($newSupplier)) {
            return false;
        }

        if ($this->isSupplierOmitted($newSupplier)) {
            return false;
        }

        if ($existing !== null && $this->isSupplierOmitted((string) ($existing['supplier'] ?? ''))) {
            return true;
        }

        $config = $this->getConfig();
        $priorityMap = $this->getPriorityMap();

        if ($existing === null) {
            return true;
        }

        $existingPrice = (float) ($existing['price'] ?? 0);
        $existingSupplier = (string) ($existing['supplier'] ?? '');
        $existingBrand = (string) ($existing['brand'] ?? '');

        if (!$this->passesBrandVerification($existingBrand, $newBrand ?? '', $config['brand_verify'])) {
            return false;
        }

        $strategy = (string) ($config['price_strategy'] ?? 'hierarchical_top3_lowest');
        if ($this->isHierarchicalStrategy($strategy)) {
            return $this->shouldReplacePriceHierarchical(
                $existing,
                $existingPrice,
                $existingSupplier,
                $newPrice,
                $newSupplier,
                $strategy,
                $config,
                $priorityMap
            );
        }

        if ($strategy === 'priority_first') {
            $newRank = import_supplier_priority_rank($newSupplier, $priorityMap);
            $existingRank = import_supplier_priority_rank($existingSupplier, $priorityMap);

            if ($newRank !== $existingRank) {
                return $newRank < $existingRank;
            }

            return $newPrice + 0.0001 < $existingPrice;
        }

        if ($newPrice + 0.0001 < $existingPrice) {
            return true;
        }

        if (abs($newPrice - $existingPrice) <= 0.0001) {
            return import_supplier_priority_rank($newSupplier, $priorityMap)
                < import_supplier_priority_rank($existingSupplier, $priorityMap);
        }

        return false;
    }

    public function isHierarchicalStrategy(string $strategy): bool
    {
        return in_array($strategy, ['hierarchical_top3_lowest', 'hierarchical_top3_first_stock'], true);
    }

    /** @param array<string, mixed> $config @param array<string, int> $priorityMap */
    public function getSupplierCompareTier(string $supplierCode, array $config, array $priorityMap): int
    {
        $tierSize = max(1, (int) ($config['compare_tier_size'] ?? self::DEFAULT_COMPARE_TIER_SIZE));
        $rank = import_supplier_priority_rank($supplierCode, $priorityMap);

        return $rank <= $tierSize ? 0 : $rank - $tierSize;
    }

    /**
     * @param array{price?:float,supplier?:string,brand?:string} $existing
     * @param array<string, mixed> $config
     * @param array<string, int> $priorityMap
     */
    private function shouldReplacePriceHierarchical(
        array $existing,
        float $existingPrice,
        string $existingSupplier,
        float $newPrice,
        string $newSupplier,
        string $strategy,
        array $config,
        array $priorityMap
    ): bool {
        $newTier = $this->getSupplierCompareTier($newSupplier, $config, $priorityMap);
        $existingTier = $this->getSupplierCompareTier($existingSupplier, $config, $priorityMap);

        if ($newTier < $existingTier) {
            return true;
        }

        if ($newTier > $existingTier) {
            return false;
        }

        if ($strategy === 'hierarchical_top3_first_stock') {
            $newRank = import_supplier_priority_rank($newSupplier, $priorityMap);
            $existingRank = import_supplier_priority_rank($existingSupplier, $priorityMap);

            if ($newRank !== $existingRank) {
                return $newRank < $existingRank;
            }

            return $newPrice + 0.0001 < $existingPrice;
        }

        if ($newPrice + 0.0001 < $existingPrice) {
            return true;
        }

        if (abs($newPrice - $existingPrice) <= 0.0001) {
            return import_supplier_priority_rank($newSupplier, $priorityMap)
                < import_supplier_priority_rank($existingSupplier, $priorityMap);
        }

        return false;
    }

    public function passesStockVerification($stockValue, string $mode): bool
    {
        if ($stockValue === null || (is_string($stockValue) && trim($stockValue) === '')) {
            return $this->passesStockStatus('unknown', $mode);
        }

        $parsed = function_exists('import_parse_supplier_stock')
            ? import_parse_supplier_stock(is_scalar($stockValue) ? (string) $stockValue : null)
            : (is_numeric($stockValue) ? (float) $stockValue : null);

        if ($parsed === null) {
            return $this->passesStockStatus('unknown', $mode);
        }

        return $this->passesStockStatus($parsed > 0 ? 'positive' : 'zero', $mode);
    }

    public function passesStockStatus(string $status, string $mode): bool
    {
        $mode = mb_strtolower(trim($mode));

        return match ($mode) {
            'include_zero' => true,
            'require_positive', 'require_known' => $status === 'positive',
            default => $status !== 'zero',
        };
    }

    public function passesBrandVerification(string $existingBrand, string $newBrand, string $mode): bool
    {
        return match ($mode) {
            'ignore' => true,
            'contains' => $existingBrand === '' || $newBrand === ''
                || str_contains($this->normalizeBrand($existingBrand), $this->normalizeBrand($newBrand))
                || str_contains($this->normalizeBrand($newBrand), $this->normalizeBrand($existingBrand)),
            default => $existingBrand === '' || $newBrand === '' || $this->normalizeBrand($existingBrand) === $this->normalizeBrand($newBrand),
        };
    }

    /** @return array<string, mixed> */
    public function testConfig(?array $override = null): array
    {
        $config = $this->normalizeConfig($override ?? $this->getConfig());
        $priorityMap = [];
        $rank = 1;
        foreach ($config['scan_order'] as $code) {
            if (!in_array($code, $config['omit_suppliers'], true)) {
                $priorityMap[$code] = $rank;
                $rank++;
            }
        }

        $samples = [
            ['code' => 'ABC123', 'price' => 120.0, 'supplier' => 'AUTONET', 'brand' => 'BOSCH', 'stock' => 5],
            ['code' => 'ABC123', 'price' => 115.0, 'supplier' => 'MATEROM', 'brand' => 'BOSCH', 'stock' => 0],
            ['code' => 'ABC123', 'price' => 118.0, 'supplier' => 'ELIT', 'brand' => 'BOSCH', 'stock' => 2],
            ['code' => 'XYZ999', 'price' => 80.0, 'supplier' => 'AUTOTOTAL', 'brand' => 'MANN', 'stock' => 1],
            ['code' => 'XYZ999', 'price' => 75.0, 'supplier' => 'INTERCARS', 'brand' => 'MANN-FILTER', 'stock' => 3],
            ['code' => 'MAN001', 'price' => 50.0, 'supplier' => 'ELIT', 'brand' => 'MAN', 'stock' => 4],
            ['code' => 'MAN001', 'price' => 45.0, 'supplier' => 'AUTOTOTAL', 'brand' => 'MAN', 'stock' => 2],
        ];

        $winner = null;
        $trace = [];

        foreach ($samples as $sample) {
            $supplier = (string) $sample['supplier'];
            if ($this->isSupplierBlocked($supplier)) {
                $trace[] = [
                    'code' => $sample['code'],
                    'supplier' => $supplier,
                    'action' => 'omis',
                    'reason' => 'Furnizor blocat',
                ];
                continue;
            }

            if (in_array($supplier, $config['omit_suppliers'], true)) {
                $trace[] = [
                    'code' => $sample['code'],
                    'supplier' => $supplier,
                    'action' => 'omis',
                    'reason' => 'Furnizor exclus din configurare',
                ];
                continue;
            }

            $brand = (string) ($sample['brand'] ?? '');
            if ($this->isBrandIgnoredForSupplier($supplier, $brand)) {
                $trace[] = [
                    'code' => $sample['code'],
                    'supplier' => $supplier,
                    'action' => 'omis',
                    'reason' => 'Brand ignorat pentru furnizor: ' . $brand,
                ];
                continue;
            }

            $sampleStatus = ((float) ($sample['stock'] ?? 0)) > 0 ? 'positive' : 'zero';
            if (!$this->passesStockStatus($sampleStatus, (string) $config['stock_verify'])) {
                $trace[] = [
                    'code' => $sample['code'],
                    'supplier' => $supplier,
                    'action' => 'respins',
                    'reason' => 'Stoc invalid — regula stock_verify=' . $config['stock_verify'],
                ];
                continue;
            }

            $key = $sample['code'];
            $existing = $winner[$key] ?? null;
            $candidate = [
                'price' => (float) $sample['price'],
                'supplier' => $supplier,
                'brand' => (string) $sample['brand'],
            ];

            if ($existing === null || $this->shouldReplacePrice($existing, $candidate['price'], $candidate['supplier'], $candidate['brand'])) {
                if ($existing !== null) {
                    $trace[] = [
                        'code' => $key,
                        'supplier' => $supplier,
                        'action' => 'inlocuieste',
                        'reason' => 'Noul pret/furnizor castiga conform regulii ' . $config['price_strategy'],
                        'previous_supplier' => $existing['supplier'],
                        'previous_price' => $existing['price'],
                        'new_price' => $candidate['price'],
                    ];
                } else {
                    $trace[] = [
                        'code' => $key,
                        'supplier' => $supplier,
                        'action' => 'selectat',
                        'reason' => 'Prima potrivire valida',
                        'new_price' => $candidate['price'],
                    ];
                }
                $winner[$key] = $candidate;
            } else {
                $trace[] = [
                    'code' => $key,
                    'supplier' => $supplier,
                    'action' => 'respins',
                    'reason' => 'Pretul/furnizorul existent ramane mai bun',
                ];
            }
        }

        $winners = [];
        foreach ($winner ?? [] as $code => $entry) {
            $winners[] = [
                'code' => (string) $code,
                'supplier' => (string) ($entry['supplier'] ?? ''),
                'price' => (float) ($entry['price'] ?? 0),
                'brand' => (string) ($entry['brand'] ?? ''),
            ];
        }

        return [
            'config' => $config,
            'priority_map' => $priorityMap,
            'winners' => $winners,
            'trace' => $trace,
        ];
    }

    /** @return array<int, array{code:string,name:string,stock_zero_mode:string,scan_include_zero_stock:int,scan_skip_unavailable:int}> */
    public function listAvailableSuppliers(): array
    {
        $rulesLib = dirname(__DIR__) . '/Produse/import_supplier_stock_zero_lib.php';
        if (is_file($rulesLib)) {
            require_once $rulesLib;
        }

        $items = [];
        foreach (import_furnizori_catalog_codes() as $code) {
            if ($this->isSupplierBlocked($code)) {
                continue;
            }

            $catalog = import_furnizori_catalog();
            $rules = function_exists('import_supplier_scan_rules_for')
                ? import_supplier_scan_rules_for($code)
                : [
                    'stock_zero_mode' => 'full',
                    'scan_include_zero_stock' => 1,
                    'scan_skip_unavailable' => 0,
                ];
            $items[] = [
                'code' => $code,
                'name' => (string) ($catalog[$code]['name'] ?? $code),
                'stock_zero_mode' => (string) ($rules['stock_zero_mode'] ?? 'full'),
                'scan_include_zero_stock' => (int) ($rules['scan_include_zero_stock'] ?? 1),
                'scan_skip_unavailable' => (int) ($rules['scan_skip_unavailable'] ?? 0),
            ];
        }

        return $items;
    }

    /** @param array<string, mixed> $raw @return array<string, mixed> */
    private function normalizeConfig(array $raw): array
    {
        $available = import_furnizori_catalog_codes();
        $scanOrder = [];
        foreach ($raw['scan_order'] ?? self::DEFAULT_SCAN_ORDER as $code) {
            $normalized = $this->normalizeSupplierCode((string) $code);
            if (
                $normalized !== ''
                && in_array($normalized, $available, true)
                && !in_array($normalized, $scanOrder, true)
                && !$this->isSupplierBlocked($normalized)
            ) {
                $scanOrder[] = $normalized;
            }
        }
        foreach ($available as $code) {
            if (!in_array($code, $scanOrder, true) && !$this->isSupplierBlocked($code)) {
                $scanOrder[] = $code;
            }
        }

        $omit = [];
        foreach ($raw['omit_suppliers'] ?? [] as $code) {
            $normalized = $this->normalizeSupplierCode((string) $code);
            if ($normalized !== '' && in_array($normalized, $available, true)) {
                $omit[] = $normalized;
            }
        }

        $brandVerify = mb_strtolower(trim((string) ($raw['brand_verify'] ?? 'exact')));
        if (!in_array($brandVerify, self::ALLOWED_BRAND_VERIFY, true)) {
            $brandVerify = 'exact';
        }

        $stockVerify = mb_strtolower(trim((string) ($raw['stock_verify'] ?? 'skip_zero')));
        if (!in_array($stockVerify, self::ALLOWED_STOCK_VERIFY, true)) {
            $stockVerify = 'skip_zero';
        }

        $priceStrategy = mb_strtolower(trim((string) ($raw['price_strategy'] ?? 'hierarchical_top3_lowest')));
        if (!in_array($priceStrategy, self::ALLOWED_PRICE_STRATEGY, true)) {
            $priceStrategy = 'hierarchical_top3_lowest';
        }

        // tm_034: instalări vechi fără compare_tier_size trec la comparare ierarhică top 3.
        if (!array_key_exists('compare_tier_size', $raw) && $priceStrategy === 'lowest_then_priority') {
            $priceStrategy = 'hierarchical_top3_lowest';
        }

        $compareTierSize = (int) ($raw['compare_tier_size'] ?? self::DEFAULT_COMPARE_TIER_SIZE);
        if ($compareTierSize < 1) {
            $compareTierSize = self::DEFAULT_COMPARE_TIER_SIZE;
        }
        if ($compareTierSize > 10) {
            $compareTierSize = 10;
        }

        $ignoreBrands = [];
        $rawIgnore = $raw['ignore_brands_by_supplier'] ?? [];
        if (is_array($rawIgnore)) {
            foreach ($rawIgnore as $supplierCode => $brands) {
                $supplier = $this->normalizeSupplierCode((string) $supplierCode);
                if ($supplier === '' || !in_array($supplier, $available, true)) {
                    continue;
                }
                if (!is_array($brands)) {
                    continue;
                }
                $list = [];
                foreach ($brands as $brandName) {
                    $normalizedBrand = $this->normalizeBrand((string) $brandName);
                    if ($normalizedBrand !== '' && !in_array($normalizedBrand, $list, true)) {
                        $list[] = $normalizedBrand;
                    }
                }
                if ($list !== []) {
                    $ignoreBrands[$supplier] = $list;
                }
            }
        }

        return [
            'scan_order' => $scanOrder,
            'omit_suppliers' => array_values(array_unique($omit)),
            'ignore_brands_by_supplier' => $ignoreBrands,
            'brand_verify' => $brandVerify,
            'stock_verify' => $stockVerify,
            'price_strategy' => $priceStrategy,
            'compare_tier_size' => $compareTierSize,
        ];
    }

    private function brandMatchesIgnored(string $brand, string $ignoredPattern): bool
    {
        $brandNorm = $this->normalizeBrand($brand);
        $pattern = $this->normalizeBrand($ignoredPattern);
        if ($pattern === '' || $brandNorm === '') {
            return false;
        }

        if ($brandNorm === $pattern) {
            return true;
        }

        if (str_starts_with($brandNorm, $pattern)) {
            return true;
        }

        return preg_match(
            '/(^|[^A-Z0-9])' . preg_quote($pattern, '/') . '([^A-Z0-9]|$)/u',
            $brandNorm
        ) === 1;
    }

    private function normalizeSupplierCode(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return function_exists('mb_strtoupper')
            ? mb_strtoupper($value, 'UTF-8')
            : strtoupper($value);
    }

    private function normalizeBrand(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return function_exists('mb_strtoupper')
            ? mb_strtoupper($value, 'UTF-8')
            : strtoupper($value);
    }
}
