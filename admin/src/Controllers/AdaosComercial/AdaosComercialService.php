<?php
declare(strict_types=1);

namespace Evasystem\Controllers\AdaosComercial;

use Config\Database;
use Evasystem\Core\AdaosComercial\AdaosComercialModel;
use Evasystem\Core\Furnizori\PriceFormationLogicModel;
use Evasystem\Core\Produse\ProduseModel;
use Throwable;

final class AdaosComercialService
{
    public const GLOBAL_ROUND_NONE = 'none';
    public const GLOBAL_ROUND_NEXT_INTEGER = 'next_integer';
    public const GLOBAL_ROUND_TO = 'round_to';

    private AdaosComercialModel $model;
    private ProduseModel $produseModel;
    private ?array $activeRules = null;
    private ?float $commercialVatPercent = null;
    private ?float $globalCommercialMarkupPercent = null;
    /** @var array{mode:string,value:float}|null */
    private ?array $globalPriceRoundSettings = null;

    public function __construct(?AdaosComercialModel $model = null, ?ProduseModel $produseModel = null)
    {
        $this->model = $model ?? new AdaosComercialModel();
        $this->produseModel = $produseModel ?? new ProduseModel();
    }

    public function getAll(): array
    {
        return $this->model->findAll();
    }

    public function getById(int $id): ?array
    {
        return $this->model->findById($id);
    }

    public function create(array $payload): int
    {
        return $this->model->insert($payload);
    }

    public function update(int $id, array $payload): bool
    {
        return $this->model->update($id, $payload);
    }

    public function delete(int $id): bool
    {
        return $this->model->delete($id);
    }

    public function toggleActive(int $id, bool $active): bool
    {
        return $this->model->update($id, ['is_active' => $active ? 1 : 0]);
    }

    public function getActiveRules(): array
    {
        if ($this->activeRules !== null) {
            return $this->activeRules;
        }

        $this->activeRules = array_values(array_filter(
            $this->model->findAll(),
            static function (array $rule): bool {
                return (int)($rule['is_active'] ?? 0) === 1;
            }
        ));

        return $this->activeRules;
    }

    /**
     * Calculează prețul pe baza procesată (pBasePrice = furnizor + adaos feed).
     * Regulile condiționale (brand/prag) se aplică DOAR când $matchConditionalRules = true
     * (previzualizare explicită, reaplicare manuală). Import/salvare = adaos global din config + TVA.
     */
    public function applyAutomaticMarkup(
        array $payload,
        ?array $existingProduct = null,
        bool $matchConditionalRules = false,
        ?int $explicitRuleId = null,
        bool $forceRecalculate = false
    ): array {
        $payloadBaseExplicit = array_key_exists('pBasePrice', $payload)
            && trim((string)$payload['pBasePrice']) !== '';
        $baseRaw = $this->resolveBaseRaw($payload, $existingProduct);

        if ($baseRaw === null) {
            $prepared = $payload;
            $prepared['pBasePrice'] = null;
            $prepared['pPrice'] = $this->nullableStringValue($payload['pPrice'] ?? ($existingProduct['pPrice'] ?? null));
            $prepared['pMarkupRuleId'] = null;
            $prepared['pMarkupRuleName'] = null;
            $prepared['pMarkupAppliedAt'] = null;

            return [
                'data' => $prepared,
                'rule' => null,
                'base_price' => '',
                'final_price' => $prepared['pPrice'] !== null ? (string)$prepared['pPrice'] : '',
                'delta' => '0',
                'applied' => false,
            ];
        }

        $basePrice = $this->parseAmount($baseRaw);
        if (!$payloadBaseExplicit && $basePrice <= 0 && $existingProduct !== null) {
            $existingPrice = $this->parseAmount($existingProduct['pPrice'] ?? '');
            if ($existingPrice > 0) {
                $basePrice = $existingPrice;
            }
        }

        $formattedBase = $this->formatAmount($basePrice);
        $existingFormattedBase = $existingProduct !== null
            ? $this->formatAmount($this->parseAmount($existingProduct['pBasePrice'] ?? ''))
            : '';

        if (
            !$forceRecalculate
            && !$matchConditionalRules
            && $existingProduct !== null
            && $existingFormattedBase === $formattedBase
            && trim((string)($existingProduct['pPrice'] ?? '')) !== ''
        ) {
            $prepared = $payload;
            $prepared['pBasePrice'] = $formattedBase;
            $prepared['pPrice'] = $this->nullableStringValue($existingProduct['pPrice'] ?? null);
            $prepared['pMarkupRuleId'] = $existingProduct['pMarkupRuleId'] ?? null;
            $prepared['pMarkupRuleName'] = $existingProduct['pMarkupRuleName'] ?? null;
            $prepared['pMarkupAppliedAt'] = $existingProduct['pMarkupAppliedAt'] ?? null;

            $finalPrice = $this->parseAmount((string)$prepared['pPrice']);

            return [
                'data' => $prepared,
                'rule' => null,
                'base_price' => $formattedBase,
                'final_price' => $this->formatAmount($finalPrice),
                'delta' => $this->formatAmount($finalPrice - $basePrice),
                'applied' => false,
            ];
        }

        $candidate = array_merge($existingProduct ?? [], $payload);
        $matchedRule = null;

        if ($explicitRuleId !== null && $explicitRuleId > 0) {
            $matchedRule = $this->getById($explicitRuleId);
        } elseif ($matchConditionalRules) {
            foreach ($this->getActiveRules() as $rule) {
                if ($this->matchesRule($candidate, $rule, $basePrice)) {
                    $matchedRule = $rule;
                    break;
                }
            }
        }

        $finalPrice = $this->computeStorePrice($basePrice, $matchedRule);

        $prepared = $payload;
        $prepared['pBasePrice'] = $formattedBase;
        $prepared['pPrice'] = $this->formatAmount($finalPrice);
        $prepared['pMarkupRuleId'] = $matchedRule['id'] ?? null;
        $prepared['pMarkupRuleName'] = $matchedRule['name'] ?? null;
        $prepared['pMarkupAppliedAt'] = $matchedRule ? date('Y-m-d H:i:s') : null;

        return [
            'data' => $prepared,
            'rule' => $matchedRule,
            'base_price' => $formattedBase,
            'final_price' => $this->formatAmount($finalPrice),
            'delta' => $this->formatAmount($finalPrice - $basePrice),
            'applied' => abs($finalPrice - $basePrice) >= 0.00001,
        ];
    }

    public function reapplyGlobalMarkupToAll(): array
    {
        $products = $this->produseModel->all();
        $pdo = Database::getDB();

        $totalCount = count($products);
        $updatedCount = 0;
        $skippedCount = 0;
        $zeroBaseCount = 0;
        $totalDelta = 0.0;

        $pdo->beginTransaction();

        try {
            foreach ($products as $product) {
                $baseRaw = $product['pBasePrice'] ?? $product['pPrice'] ?? '';
                $basePrice = $this->parseAmount($baseRaw);
                if ($basePrice <= 0) {
                    $zeroBaseCount++;
                    continue;
                }

                $ruleId = (int) ($product['pMarkupRuleId'] ?? 0);
                $conditionalRule = ($ruleId > 0) ? $this->getById($ruleId) : null;

                $finalPrice = $this->computeStorePrice($basePrice, $conditionalRule);
                $targetBase = $this->formatAmount($basePrice);
                $targetPrice = $this->formatAmount($finalPrice);
                $targetRuleId = $conditionalRule !== null ? ((int) ($conditionalRule['id'] ?? 0) ?: null) : null;
                $targetRuleName = $conditionalRule !== null ? (string) ($conditionalRule['name'] ?? '') : '';

                $currentBase = $this->formatAmount($this->parseAmount($product['pBasePrice'] ?? ''));
                $currentPrice = $this->formatAmount($this->parseAmount($product['pPrice'] ?? ''));
                $currentRuleId = ($product['pMarkupRuleId'] ?? null);
                $currentRuleId = ($currentRuleId === null || $currentRuleId === '') ? null : (int) $currentRuleId;
                $currentRuleName = trim((string) ($product['pMarkupRuleName'] ?? ''));

                if (
                    $currentBase === $targetBase
                    && $currentPrice === $targetPrice
                    && $currentRuleId === $targetRuleId
                    && $currentRuleName === $targetRuleName
                ) {
                    continue;
                }

                $updateData = [
                    'pBasePrice' => $targetBase,
                    'pPrice' => $targetPrice,
                    'pMarkupRuleId' => $targetRuleId,
                    'pMarkupRuleName' => $targetRuleName !== '' ? $targetRuleName : null,
                    'pMarkupAppliedAt' => $conditionalRule ? date('Y-m-d H:i:s') : ($product['pMarkupAppliedAt'] ?? null),
                ];

                if ($this->produseModel->update($this->productIdentifier($product), $updateData)) {
                    $updatedCount++;
                    $totalDelta += ($finalPrice - $this->parseAmount($product['pPrice'] ?? ''));
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        return [
            'total_count' => $totalCount,
            'updated_count' => $updatedCount,
            'skipped_count' => $skippedCount,
            'zero_base_count' => $zeroBaseCount,
            'global_markup_percent' => $this->getGlobalCommercialMarkupPercent(),
            'total_delta' => $this->formatAmount($totalDelta),
        ];
    }

    private function resolveBaseRaw(array $payload, ?array $existingProduct): ?string
    {
        $payloadBase = trim((string)($payload['pBasePrice'] ?? ''));
        if ($payloadBase !== '') {
            return $payloadBase;
        }

        $payloadPrice = trim((string)($payload['pPrice'] ?? ''));
        if ($payloadPrice !== '') {
            return $payloadPrice;
        }

        $existingBase = trim((string)($existingProduct['pBasePrice'] ?? ''));
        if ($existingBase !== '' && $this->parseAmount($existingBase) > 0) {
            return $existingBase;
        }

        $existingPrice = trim((string)($existingProduct['pPrice'] ?? ''));
        if ($existingPrice !== '') {
            return $existingPrice;
        }

        if ($existingBase !== '') {
            return $existingBase;
        }

        return null;
    }

    private function nullableStringValue($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string)$value);
        return $trimmed === '' ? null : $trimmed;
    }

    public function previewRule(int $id, int $limit = 25): array
    {
        $rule = $this->requireRule($id);
        $products = $this->produseModel->all();

        $matchedCount = 0;
        $changedCount = 0;
        $zeroBaseCount = 0;
        $missingBaseCount = 0;
        $totalDelta = 0.0;
        $preview = [];

        foreach ($products as $product) {
            $baseRaw = $product['pBasePrice'] ?? $product['pPrice'] ?? '';
            $basePrice = $this->parseAmount($baseRaw);
            if (!$this->matchesRule($product, $rule, $basePrice)) {
                continue;
            }

            $matchedCount++;
            if (trim((string)$baseRaw) === '') {
                $missingBaseCount++;
            }
            if ($basePrice <= 0) {
                $zeroBaseCount++;
            }
            $finalPrice = $this->computeStorePrice($basePrice, $rule);
            $delta = $finalPrice - $basePrice;

            if (abs($delta) >= 0.00001) {
                $changedCount++;
                $totalDelta += $delta;
            }

            if (count($preview) < $limit) {
                $preview[] = [
                    'id' => (string)($product['randomn_id'] ?? $product['id'] ?? ''),
                    'name' => (string)($product['pName'] ?? $product['name'] ?? 'Produs fără nume'),
                    'category' => (string)($product['pCategory'] ?? $product['pSubcategory'] ?? ''),
                    'brand' => (string)($product['pBrand'] ?? $product['pMarca'] ?? ''),
                    'base_price' => $this->formatAmount($basePrice),
                    'final_price' => $this->formatAmount($finalPrice),
                    'delta' => $this->formatAmount($delta),
                ];
            }
        }

        return [
            'rule' => $rule,
            'matched_count' => $matchedCount,
            'changed_count' => $changedCount,
            'zero_base_count' => $zeroBaseCount,
            'missing_base_count' => $missingBaseCount,
            'total_delta' => $this->formatAmount($totalDelta),
            'products' => $preview,
        ];
    }

    public function applyRule(int $id): array
    {
        $rule = $this->requireRule($id);
        $products = $this->produseModel->all();
        $pdo = Database::getDB();

        $matchedCount = 0;
        $updatedCount = 0;
        $priceChangedCount = 0;
        $metadataUpdatedCount = 0;
        $zeroBaseCount = 0;
        $missingBaseCount = 0;
        $totalDelta = 0.0;

        $pdo->beginTransaction();

        try {
            foreach ($products as $product) {
                $baseRaw = $product['pBasePrice'] ?? $product['pPrice'] ?? '';
                $basePrice = $this->parseAmount($baseRaw);
                if (!$this->matchesRule($product, $rule, $basePrice)) {
                    continue;
                }

                $matchedCount++;
                if (trim((string)$baseRaw) === '') {
                    $missingBaseCount++;
                }
                if ($basePrice <= 0) {
                    $zeroBaseCount++;
                }
                $finalPrice = $this->computeStorePrice($basePrice, $rule);
                $delta = $finalPrice - $basePrice;
                $priceChanged = abs($delta) >= 0.00001;

                $identifier = $this->productIdentifier($product);
                $targetBase = $this->formatAmount($basePrice);
                $targetPrice = $this->formatAmount($finalPrice);
                $targetRuleId = (int)($rule['id'] ?? 0) ?: null;
                $targetRuleName = (string)($rule['name'] ?? '');

                $currentBase = $this->formatAmount($this->parseAmount($product['pBasePrice'] ?? ''));
                $currentPrice = $this->formatAmount($this->parseAmount($product['pPrice'] ?? ''));
                $currentRuleId = ($product['pMarkupRuleId'] ?? null);
                $currentRuleId = ($currentRuleId === null || $currentRuleId === '') ? null : (int)$currentRuleId;
                $currentRuleName = trim((string)($product['pMarkupRuleName'] ?? ''));
                $currentAppliedAt = trim((string)($product['pMarkupAppliedAt'] ?? ''));

                $needsPriceUpdate = $currentBase !== $targetBase || $currentPrice !== $targetPrice;
                $needsRuleUpdate = $currentRuleId !== $targetRuleId
                    || $currentRuleName !== $targetRuleName
                    || $currentAppliedAt === '';

                if (!$needsPriceUpdate && !$needsRuleUpdate) {
                    continue;
                }

                $updateData = [
                    'pBasePrice' => $targetBase,
                    'pPrice' => $targetPrice,
                    'pMarkupRuleId' => $targetRuleId,
                    'pMarkupRuleName' => $targetRuleName,
                    'pMarkupAppliedAt' => date('Y-m-d H:i:s'),
                ];

                $saved = $this->produseModel->update($identifier, $updateData);

                if ($saved) {
                    $updatedCount++;
                    if ($priceChanged) {
                        $priceChangedCount++;
                        $totalDelta += $delta;
                    } else {
                        $metadataUpdatedCount++;
                    }
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        return [
            'rule' => $rule,
            'matched_count' => $matchedCount,
            'updated_count' => $updatedCount,
            'price_changed_count' => $priceChangedCount,
            'metadata_updated_count' => $metadataUpdatedCount,
            'zero_base_count' => $zeroBaseCount,
            'missing_base_count' => $missingBaseCount,
            'total_delta' => $this->formatAmount($totalDelta),
        ];
    }

    /**
     * Aplică explicit o regulă pe produsele selectate manual (fără potrivire automată după filtre).
     *
     * @param list<string> $productIds
     */
    public function applyRuleToProductIds(int $ruleId, array $productIds): array
    {
        $rule = $this->requireRule($ruleId);
        $productIds = array_values(array_unique(array_filter(array_map('strval', $productIds))));

        if ($productIds === []) {
            throw new \InvalidArgumentException('Nu ai selectat produse pentru aplicarea adaosului.');
        }

        $pdo = Database::getDB();
        $requestedCount = count($productIds);
        $updatedCount = 0;
        $notFoundCount = 0;
        $zeroBaseCount = 0;
        $missingBaseCount = 0;
        $priceChangedCount = 0;
        $metadataUpdatedCount = 0;
        $totalDelta = 0.0;

        $pdo->beginTransaction();

        try {
            foreach ($productIds as $productId) {
                $product = $this->produseModel->find($productId);
                if (!$product) {
                    $notFoundCount++;
                    continue;
                }

                $baseRaw = $product['pBasePrice'] ?? $product['pPrice'] ?? '';
                if (trim((string) $baseRaw) === '') {
                    $missingBaseCount++;
                }

                $basePrice = $this->parseAmount($baseRaw);
                if ($basePrice <= 0) {
                    $existingPrice = $this->parseAmount($product['pPrice'] ?? '');
                    if ($existingPrice > 0) {
                        $basePrice = $existingPrice;
                    } else {
                        $zeroBaseCount++;
                    }
                }

                $finalPrice = $this->computeStorePrice($basePrice, $rule);
                $delta = $finalPrice - $basePrice;
                $priceChanged = abs($delta) >= 0.00001;

                $identifier = $this->productIdentifier($product);
                $targetBase = $this->formatAmount($basePrice);
                $targetPrice = $this->formatAmount($finalPrice);
                $targetRuleId = (int) ($rule['id'] ?? 0) ?: null;
                $targetRuleName = (string) ($rule['name'] ?? '');

                $currentBase = $this->formatAmount($this->parseAmount($product['pBasePrice'] ?? ''));
                $currentPrice = $this->formatAmount($this->parseAmount($product['pPrice'] ?? ''));
                $currentRuleId = ($product['pMarkupRuleId'] ?? null);
                $currentRuleId = ($currentRuleId === null || $currentRuleId === '') ? null : (int) $currentRuleId;
                $currentRuleName = trim((string) ($product['pMarkupRuleName'] ?? ''));
                $currentAppliedAt = trim((string) ($product['pMarkupAppliedAt'] ?? ''));

                $needsPriceUpdate = $currentBase !== $targetBase || $currentPrice !== $targetPrice;
                $needsRuleUpdate = $currentRuleId !== $targetRuleId
                    || $currentRuleName !== $targetRuleName
                    || $currentAppliedAt === '';

                if (!$needsPriceUpdate && !$needsRuleUpdate) {
                    continue;
                }

                $updateData = [
                    'pBasePrice' => $targetBase,
                    'pPrice' => $targetPrice,
                    'pMarkupRuleId' => $targetRuleId,
                    'pMarkupRuleName' => $targetRuleName,
                    'pMarkupAppliedAt' => date('Y-m-d H:i:s'),
                ];

                if ($this->produseModel->update($identifier, $updateData)) {
                    $updatedCount++;
                    if ($priceChanged) {
                        $priceChangedCount++;
                        $totalDelta += $delta;
                    } else {
                        $metadataUpdatedCount++;
                    }
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        return [
            'rule' => $rule,
            'requested_count' => $requestedCount,
            'updated_count' => $updatedCount,
            'not_found_count' => $notFoundCount,
            'price_changed_count' => $priceChangedCount,
            'metadata_updated_count' => $metadataUpdatedCount,
            'zero_base_count' => $zeroBaseCount,
            'missing_base_count' => $missingBaseCount,
            'total_delta' => $this->formatAmount($totalDelta),
        ];
    }

    private function requireRule(int $id): array
    {
        $rule = $this->model->findById($id);
        if (!$rule) {
            throw new \RuntimeException('Regula selectată nu a fost găsită.');
        }

        return $rule;
    }

    public function findFirstMatchingRule(array $product, float $basePrice): ?array
    {
        if ($basePrice <= 0) {
            return null;
        }

        foreach ($this->getActiveRules() as $rule) {
            if ($this->matchesRule($product, $rule, $basePrice)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Descompune adaos comercial + TVA + rotunjire pentru log formare preț.
     *
     * @return array{
     *   global_markup_delta:float,
     *   conditional_markup_delta:float,
     *   markup_delta:float,
     *   after_markup:float,
     *   after_vat:float,
     *   final:float,
     *   round_delta:float
     * }
     */
    public function computeCommercialBreakdown(float $basePrice, ?array $rule): array
    {
        if ($basePrice <= 0) {
            return [
                'global_markup_delta' => 0.0,
                'conditional_markup_delta' => 0.0,
                'markup_delta' => 0.0,
                'after_markup' => 0.0,
                'after_vat' => 0.0,
                'final' => 0.0,
                'round_delta' => 0.0,
            ];
        }

        $globalDelta = $this->computeGlobalCommercialMarkupDelta($basePrice);
        $conditionalDelta = $rule !== null ? $this->computeRuleMarkupDelta($basePrice, $rule) : 0.0;
        $afterMarkup = $basePrice + $globalDelta + $conditionalDelta;

        if ($rule !== null) {
            $roundTo = (float) ($rule['round_to'] ?? 0);
            if ($roundTo > 0) {
                $afterMarkup = ceil($afterMarkup / $roundTo) * $roundTo;
            }
        }

        $markupDelta = $afterMarkup - $basePrice;
        $afterVat = $this->applyCommercialVat($afterMarkup);
        $final = $this->applyGlobalPriceRound($afterVat);
        $roundDelta = max(0.0, $final - $afterVat);

        return [
            'global_markup_delta' => round($globalDelta, 4),
            'conditional_markup_delta' => round($conditionalDelta, 4),
            'markup_delta' => round($markupDelta, 4),
            'after_markup' => round($afterMarkup, 4),
            'after_vat' => round($afterVat, 4),
            'final' => round($final, 4),
            'round_delta' => round($roundDelta, 4),
        ];
    }

    private function matchesRule(array $product, array $rule, float $basePrice): bool
    {
        $categoryFilter = $this->normalizeText($rule['category_filter'] ?? '');
        if ($categoryFilter !== '') {
            $productCategories = array_filter([
                $this->normalizeText($product['pCategory'] ?? ''),
                $this->normalizeText($product['pSubcategory'] ?? ''),
            ]);

            if (!in_array($categoryFilter, $productCategories, true)) {
                return false;
            }
        }

        $brandFilter = $this->normalizeText($rule['brand_filter'] ?? '');
        if ($brandFilter !== '') {
            $productBrands = array_filter([
                $this->normalizeText($product['pBrand'] ?? ''),
                $this->normalizeText($product['pMarca'] ?? ''),
            ]);

            if (!in_array($brandFilter, $productBrands, true)) {
                return false;
            }
        }

        $priceMin = $rule['price_min'] !== null && $rule['price_min'] !== '' ? (float)$rule['price_min'] : null;
        $priceMax = $rule['price_max'] !== null && $rule['price_max'] !== '' ? (float)$rule['price_max'] : null;

        // „Peste X RON” = strict mai mare decât pragul (ex: peste 2000 → 2000.01+)
        if ($priceMin !== null && $basePrice <= $priceMin) {
            return false;
        }

        if ($priceMax !== null && $basePrice > $priceMax) {
            return false;
        }

        return true;
    }

    public function getCommercialVatPercent(): float
    {
        if ($this->commercialVatPercent !== null) {
            return $this->commercialVatPercent;
        }

        $model = new PriceFormationLogicModel();
        $config = $model->loadConfig() ?? [];
        $vat = (float) ($config['commercial_vat_percent'] ?? 21.0);
        $this->commercialVatPercent = max(0.0, min(100.0, $vat));

        return $this->commercialVatPercent;
    }

    public function saveCommercialVatPercent(float $percent): bool
    {
        $model = new PriceFormationLogicModel();
        $config = $model->loadConfig() ?? [];
        $config['commercial_vat_percent'] = max(0.0, min(100.0, $percent));
        $this->commercialVatPercent = $config['commercial_vat_percent'];

        return $model->saveConfig($config);
    }

    public function getGlobalCommercialMarkupPercent(): float
    {
        if ($this->globalCommercialMarkupPercent !== null) {
            return $this->globalCommercialMarkupPercent;
        }

        $model = new PriceFormationLogicModel();
        $config = $model->loadConfig() ?? [];
        $percent = (float) ($config['global_commercial_markup_percent'] ?? 0.0);
        $this->globalCommercialMarkupPercent = max(0.0, min(1000.0, $percent));

        return $this->globalCommercialMarkupPercent;
    }

    public function saveGlobalCommercialMarkupPercent(float $percent): bool
    {
        $model = new PriceFormationLogicModel();
        $config = $model->loadConfig() ?? [];
        $config['global_commercial_markup_percent'] = max(0.0, min(1000.0, $percent));
        $this->globalCommercialMarkupPercent = $config['global_commercial_markup_percent'];

        return $model->saveConfig($config);
    }

    /** @return array{mode:string,value:float} */
    public function getGlobalPriceRoundSettings(): array
    {
        if ($this->globalPriceRoundSettings !== null) {
            return $this->globalPriceRoundSettings;
        }

        $model = new PriceFormationLogicModel();
        $config = $model->loadConfig() ?? [];
        $mode = mb_strtolower(trim((string) ($config['global_price_round_mode'] ?? self::GLOBAL_ROUND_NONE)));
        if (!in_array($mode, [self::GLOBAL_ROUND_NEXT_INTEGER, self::GLOBAL_ROUND_TO], true)) {
            $mode = self::GLOBAL_ROUND_NONE;
        }

        $value = (float) ($config['global_price_round_value'] ?? 1);
        if ($value <= 0) {
            $value = 1.0;
        }

        $this->globalPriceRoundSettings = [
            'mode' => $mode,
            'value' => $value,
        ];

        return $this->globalPriceRoundSettings;
    }

    public function getGlobalPriceRoundMode(): string
    {
        return $this->getGlobalPriceRoundSettings()['mode'];
    }

    public function getGlobalPriceRoundValue(): float
    {
        return $this->getGlobalPriceRoundSettings()['value'];
    }

    public function saveGlobalPriceRoundSettings(string $mode, ?float $value = null): bool
    {
        $normalizedMode = mb_strtolower(trim($mode));
        if (!in_array($normalizedMode, [self::GLOBAL_ROUND_NONE, self::GLOBAL_ROUND_NEXT_INTEGER, self::GLOBAL_ROUND_TO], true)) {
            throw new \InvalidArgumentException('Modul de rotunjire globală este invalid.');
        }

        $roundValue = 1.0;
        if ($normalizedMode === self::GLOBAL_ROUND_TO) {
            $roundValue = max(0.01, (float) ($value ?? 0));
            if ($roundValue <= 0) {
                throw new \InvalidArgumentException('Valoarea rotunjirii trebuie să fie mai mare decât 0.');
            }
        }

        $model = new PriceFormationLogicModel();
        $config = $model->loadConfig() ?? [];
        $config['global_price_round_mode'] = $normalizedMode;
        $config['global_price_round_value'] = $roundValue;
        $this->globalPriceRoundSettings = [
            'mode' => $normalizedMode,
            'value' => $roundValue,
        ];

        return $model->saveConfig($config);
    }

    public function applyGlobalPriceRound(float $amount): float
    {
        $settings = $this->getGlobalPriceRoundSettings();
        $mode = $settings['mode'];

        if ($mode === self::GLOBAL_ROUND_NEXT_INTEGER) {
            return max(0.0, (float) ceil($amount - 0.00001));
        }

        if ($mode === self::GLOBAL_ROUND_TO) {
            $roundTo = max(0.01, (float) $settings['value']);

            return max(0.0, ceil($amount / $roundTo) * $roundTo);
        }

        return max(0.0, $amount);
    }

    public function usesIntegerStorePrices(): bool
    {
        return $this->getGlobalPriceRoundMode() !== self::GLOBAL_ROUND_NONE;
    }

    private function applyCommercialVat(float $amount): float
    {
        $vatPercent = $this->getCommercialVatPercent();
        if ($vatPercent <= 0.0001) {
            return max(0, round($amount, 2));
        }

        return max(0, round($amount * (1 + ($vatPercent / 100)), 2));
    }

    public function computeStorePrice(float $basePrice, ?array $conditionalRule = null): float
    {
        if ($basePrice <= 0) {
            return 0.0;
        }

        $globalDelta = $this->computeGlobalCommercialMarkupDelta($basePrice);
        $conditionalDelta = $conditionalRule !== null
            ? $this->computeRuleMarkupDelta($basePrice, $conditionalRule)
            : 0.0;
        $afterMarkup = $basePrice + $globalDelta + $conditionalDelta;

        if ($conditionalRule !== null) {
            $roundTo = (float) ($conditionalRule['round_to'] ?? 0);
            if ($roundTo > 0) {
                $afterMarkup = ceil($afterMarkup / $roundTo) * $roundTo;
            }
        }

        return $this->applyGlobalPriceRound($this->applyCommercialVat($afterMarkup));
    }

    private function computeGlobalCommercialMarkupDelta(float $basePrice): float
    {
        if ($basePrice <= 0) {
            return 0.0;
        }

        $percent = $this->getGlobalCommercialMarkupPercent();
        if ($percent <= 0.0001) {
            return 0.0;
        }

        return $basePrice * ($percent / 100);
    }

    private function computeRuleMarkupDelta(float $basePrice, array $rule): float
    {
        if ($basePrice <= 0) {
            return 0.0;
        }

        $type = (string) ($rule['adjustment_type'] ?? 'percentage');
        $value = (float) ($rule['adjustment_value'] ?? 0);

        if ($type === 'fixed') {
            return $value;
        }

        return $basePrice * ($value / 100);
    }

    private function calculateFinalPrice(float $basePrice, array $rule): float
    {
        return $this->computeStorePrice($basePrice, $rule);
    }

    private function parseAmount($value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float)$value;
        }

        $normalized = str_replace([' ', ','], ['', '.'], (string)$value);
        $normalized = preg_replace('/[^0-9.\-]/', '', $normalized) ?? '0';

        if ($normalized === '' || $normalized === '-' || $normalized === '.'
            || $normalized === '-.') {
            return 0.0;
        }

        return (float)$normalized;
    }

    private function formatAmount(float $value): string
    {
        if ($this->usesIntegerStorePrices()) {
            $formatted = number_format($value, 0, '.', '');
        } else {
            $formatted = number_format($value, 2, '.', '');
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted === '-0' ? '0' : $formatted;
    }

    private function normalizeText(string $value): string
    {
        return mb_strtolower(trim($value), 'UTF-8');
    }

    private function productIdentifier(array $product): string
    {
        $randomId = (string)($product['randomn_id'] ?? '');
        if ($randomId !== '') {
            return $randomId;
        }

        return (string)($product['id'] ?? '');
    }
}
