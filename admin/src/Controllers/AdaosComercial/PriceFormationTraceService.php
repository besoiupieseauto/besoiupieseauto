<?php

declare(strict_types=1);

namespace Evasystem\Controllers\AdaosComercial;

use Config\Database;
use Evasystem\Core\AppCache;
use Evasystem\Core\Produse\ProduseModel;
use PDO;
use Throwable;

require_once dirname(__DIR__) . '/Produse/import_supplier_lib.php';

final class PriceFormationTraceService
{
    private AdaosComercialService $markupService;
    private ProduseModel $produseModel;

    public function __construct(
        ?AdaosComercialService $markupService = null,
        ?ProduseModel $produseModel = null
    ) {
        $this->markupService = $markupService ?? new AdaosComercialService();
        $this->produseModel = $produseModel ?? new ProduseModel();
    }

    /** @return array<string, mixed> */
    public function traceByProductIdentifier(string $identifier): array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return $this->error('Introduce cod produs, OEM sau ID.');
        }

        $product = $this->findProduct($identifier);
        if ($product === null) {
            return $this->error('Produsul nu a fost găsit.');
        }

        return $this->success($this->buildTrace($product, 'product'));
    }

    /** @return array<string, mixed> */
    public function traceByImportRowId(int $importRowId): array
    {
        if ($importRowId <= 0) {
            return $this->error('ID import invalid.');
        }

        $row = $this->findImportRow($importRowId);
        if ($row === null) {
            return $this->error('Rândul din coada import nu a fost găsit.');
        }

        $product = $this->mapImportRowToProduct($row);

        return $this->success($this->buildTrace($product, 'import', [
            'import_id' => $importRowId,
            'import_status' => (string) ($row['status'] ?? ''),
        ]));
    }

    /** @return array<string, mixed> */
    public function traceImportBatch(?string $supplier = null, int $limit = 25, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $supplierCode = $supplier !== null && trim($supplier) !== ''
            ? import_supplier_normalize_code($supplier)
            : '';

        $pdo = Database::getDB();
        $where = ['1=1'];
        $params = [];
        if ($supplierCode !== '') {
            $where[] = 'UPPER(TRIM(pSupplier)) = :supplier';
            $params[':supplier'] = $supplierCode;
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM import_produse WHERE ' . $whereSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = 'SELECT * FROM import_produse WHERE ' . $whereSql
            . ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $product = $this->mapImportRowToProduct($row);
            $trace = $this->buildTrace($product, 'import', [
                'import_id' => (int) ($row['id'] ?? 0),
                'import_status' => (string) ($row['status'] ?? ''),
            ]);
            $items[] = [
                'import_id' => (int) ($row['id'] ?? 0),
                'code' => (string) ($product['pCode'] ?? ''),
                'name' => (string) ($product['pName'] ?? ''),
                'supplier' => import_supplier_normalize_code((string) ($product['pSupplier'] ?? '')),
                'trace' => $trace,
            ];
        }

        return [
            'success' => true,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'supplier' => $supplierCode,
            'items' => $items,
        ];
    }

    /**
     * Furnizori pentru filtrul „coadă import” — catalog + rânduri reale din import_produse.
     *
     * @return list<array{code:string,label:string,count:int}>
     */
    public function listImportQueueSuppliers(): array
    {
        return AppCache::remember('pfl_import_suppliers_v1', 120, function (): array {
            return $this->loadImportQueueSuppliers();
        });
    }

    /** @return list<array{code:string,label:string,count:int}> */
    private function loadImportQueueSuppliers(): array
    {
        $map = [];
        $counts = [];

        try {
            $pdo = Database::getDB();
            foreach ($pdo->query(
                "SELECT UPPER(TRIM(code)) AS supplier_code, TRIM(name) AS supplier_label
                 FROM furnizori
                 WHERE code IS NOT NULL AND TRIM(code) <> ''"
            ) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $code = import_supplier_normalize_code((string) ($row['supplier_code'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $label = trim((string) ($row['supplier_label'] ?? ''));
                $map[$code] = $label !== '' ? $label : $code;
            }

            $countStmt = $pdo->query(
                "SELECT UPPER(TRIM(pSupplier)) AS supplier_code, COUNT(*) AS row_count
                 FROM import_produse
                 WHERE TRIM(IFNULL(pSupplier, '')) <> ''
                 GROUP BY UPPER(TRIM(pSupplier))
                 ORDER BY row_count DESC
                 LIMIT 120"
            );
            if ($countStmt !== false) {
                foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $code = import_supplier_normalize_code((string) ($row['supplier_code'] ?? ''));
                    if ($code === '') {
                        continue;
                    }
                    $counts[$code] = (int) ($row['row_count'] ?? 0);
                    if (!isset($map[$code])) {
                        $map[$code] = $code;
                    }
                }
            }
        } catch (Throwable) {
            // fallback la catalog static
        }

        foreach (import_supplier_definitions() as $code => $def) {
            $code = import_supplier_normalize_code((string) $code);
            if ($code === '') {
                continue;
            }
            if (!isset($map[$code])) {
                $map[$code] = trim((string) ($def['name'] ?? $code));
            }
        }

        $priorityMap = import_supplier_priority_map();
        $items = [];
        foreach ($map as $code => $label) {
            $items[] = [
                'code' => $code,
                'label' => $label !== '' ? $label : $code,
                'count' => (int) ($counts[$code] ?? 0),
            ];
        }

        usort($items, static function (array $a, array $b) use ($priorityMap): int {
            $countA = (int) ($a['count'] ?? 0);
            $countB = (int) ($b['count'] ?? 0);
            if ($countA > 0 && $countB === 0) {
                return -1;
            }
            if ($countB > 0 && $countA === 0) {
                return 1;
            }
            if ($countA !== $countB) {
                return $countB <=> $countA;
            }

            $rankA = import_supplier_priority_rank((string) ($a['code'] ?? ''), $priorityMap);
            $rankB = import_supplier_priority_rank((string) ($b['code'] ?? ''), $priorityMap);
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        return $items;
    }

    /** @return array<string, mixed> */
    public function listImportQueueSuppliersResponse(): array
    {
        return [
            'success' => true,
            'suppliers' => $this->listImportQueueSuppliers(),
        ];
    }

    /** @param array<string, mixed> $product @param array<string, mixed> $meta @return array<string, mixed> */
    private function buildTrace(array $product, string $source, array $meta = []): array
    {
        $supplier = import_supplier_normalize_code((string) ($product['pSupplier'] ?? ''));
        $purchase = $this->parseAmount($product['pBasePrice'] ?? $product['pPrice'] ?? '');
        $finalStored = $this->parseAmount($product['pPrice'] ?? '');

        $compensatorPercent = $supplier !== '' ? import_supplier_feed_markup_percent($supplier) : 0.0;
        $feedPrice = $purchase > 0
            ? import_supplier_reverse_feed_csv_price($purchase, $compensatorPercent)
            : 0.0;
        $compensatorDelta = max(0.0, $purchase - $feedPrice);

        $matchedRule = $this->markupService->findFirstMatchingRule($product, $purchase);
        $ruleId = (int) ($product['pMarkupRuleId'] ?? 0);
        if ($matchedRule === null && $ruleId > 0) {
            $matchedRule = $this->markupService->getById($ruleId);
        }

        $breakdown = $this->markupService->computeCommercialBreakdown($purchase, $matchedRule);
        $brandFilter = trim((string) ($matchedRule['brand_filter'] ?? ''));
        $isBrandRule = $brandFilter !== '';
        $globalDelta = (float) ($breakdown['global_markup_delta'] ?? 0);
        $brandDelta = (float) ($breakdown['conditional_markup_delta'] ?? 0);

        $afterGlobal = $purchase + $globalDelta;
        $afterBrand = $afterGlobal + $brandDelta;
        $vatPercent = $this->markupService->getCommercialVatPercent();
        $vatDelta = max(0.0, (float) ($breakdown['after_vat'] ?? 0) - (float) ($breakdown['after_markup'] ?? 0));
        $finalCalculated = (float) ($breakdown['final'] ?? 0);
        $finalPrice = $finalStored > 0 ? $finalStored : $finalCalculated;

        $steps = [
            $this->step('feed', 'Preț feed', $feedPrice, 'Preț din CSV furnizor' . ($supplier !== '' ? ' (' . $supplier . ')' : '')),
            $this->step(
                'compensator',
                '+Compensator',
                $compensatorDelta,
                $this->formatPercentLabel($compensatorPercent),
                $compensatorPercent
            ),
            $this->step('purchase', '=Achiziție', $purchase, 'Preț bază fără TVA comercial (pBasePrice)'),
            $this->step(
                'markup_global',
                '+Adaos global',
                $globalDelta,
                $this->formatPercentLabel($this->markupService->getGlobalCommercialMarkupPercent())
            ),
            $this->step(
                'markup_brand',
                '+Adaos brand',
                $brandDelta,
                $this->ruleStepDetail($matchedRule, $isBrandRule || $brandDelta > 0.00001)
            ),
            $this->step(
                'vat',
                '+TVA',
                $vatDelta,
                $this->formatPercentLabel($vatPercent) . ' pe preț după adaos',
                $vatPercent
            ),
            $this->step('final', '=Preț final', $finalPrice, 'Preț magazin (pPrice)'),
        ];

        if (($breakdown['round_delta'] ?? 0) > 0.00001) {
            $steps[] = $this->step(
                'round',
                'Rotunjire',
                (float) $breakdown['round_delta'],
                'Rotunjire globală magazin'
            );
        }

        return [
            'source' => $source,
            'meta' => array_merge($meta, [
                'code' => (string) ($product['pCode'] ?? ''),
                'name' => (string) ($product['pName'] ?? ''),
                'brand' => (string) ($product['pBrand'] ?? $product['pMarca'] ?? ''),
                'supplier' => $supplier,
                'rule_id' => $matchedRule['id'] ?? null,
                'rule_name' => (string) ($matchedRule['name'] ?? $product['pMarkupRuleName'] ?? ''),
            ]),
            'steps' => $steps,
            'summary' => [
                'feed' => $this->formatAmount($feedPrice),
                'purchase' => $this->formatAmount($purchase),
                'final' => $this->formatAmount($finalPrice),
                'compensator_percent' => $compensatorPercent,
                'vat_percent' => $vatPercent,
            ],
            'checks_ok' => abs($finalCalculated - $finalStored) < 0.02 || $finalStored <= 0,
        ];
    }

    /** @return array<string, mixed> */
    private function step(
        string $key,
        string $label,
        float $amount,
        string $detail = '',
        ?float $percent = null
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'amount' => $this->formatAmount($amount),
            'amount_raw' => round($amount, 4),
            'detail' => $detail,
            'percent' => $percent !== null ? round($percent, 4) : null,
        ];
    }

    /** @param array<string, mixed>|null $rule */
    private function ruleStepDetail(?array $rule, bool $active): string
    {
        if (!$active || $rule === null) {
            return '— (fără regulă aplicată)';
        }

        $name = trim((string) ($rule['name'] ?? ''));
        $type = (string) ($rule['adjustment_type'] ?? 'percentage');
        $value = (float) ($rule['adjustment_value'] ?? 0);
        $adjustment = $type === 'fixed'
            ? '+' . $this->formatAmount($value) . ' lei'
            : '+' . $this->formatAmount($value) . '%';

        return ($name !== '' ? $name . ' · ' : '') . $adjustment;
    }

    private function formatPercentLabel(float $percent): string
    {
        if ($percent <= 0.0001) {
            return '0%';
        }

        return '+' . rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.') . '%';
    }

    /** @return array<string, mixed> */
    private function success(array $trace): array
    {
        return ['success' => true, 'data' => $trace];
    }

    /** @return array<string, mixed> */
    private function error(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }

    /** @return array<string, mixed>|null */
    private function findProduct(string $identifier): ?array
    {
        $pdo = Database::getDB();
        $stmt = $pdo->prepare(
            'SELECT * FROM produse
             WHERE randomn_id = :id OR pCode = :code OR id = :num_id
             LIMIT 1'
        );
        $numId = is_numeric($identifier) ? (int) $identifier : 0;
        $stmt->execute([
            ':id' => $identifier,
            ':code' => $identifier,
            ':num_id' => $numId > 0 ? $numId : -1,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    private function findImportRow(int $id): ?array
    {
        $pdo = Database::getDB();
        $stmt = $pdo->prepare('SELECT * FROM import_produse WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function mapImportRowToProduct(array $row): array
    {
        return [
            'pCode' => (string) ($row['pCode'] ?? ''),
            'pName' => (string) ($row['pName'] ?? $row['name'] ?? ''),
            'pBrand' => (string) ($row['pBrand'] ?? ''),
            'pMarca' => (string) ($row['pMarca'] ?? ''),
            'pCategory' => (string) ($row['pCategory'] ?? ''),
            'pSubcategory' => (string) ($row['pSubcategory'] ?? ''),
            'pSupplier' => (string) ($row['pSupplier'] ?? ''),
            'pBasePrice' => (string) ($row['pBasePrice'] ?? $row['pPrice'] ?? ''),
            'pPrice' => (string) ($row['pPrice'] ?? ''),
            'pMarkupRuleId' => $row['pMarkupRuleId'] ?? null,
            'pMarkupRuleName' => $row['pMarkupRuleName'] ?? null,
        ];
    }

    private function parseAmount(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = str_replace([' ', ','], ['', '.'], (string) $value);
        $normalized = preg_replace('/[^0-9.\-]/', '', $normalized) ?? '0';
        if ($normalized === '' || $normalized === '-' || $normalized === '.' || $normalized === '-.') {
            return 0.0;
        }

        return (float) $normalized;
    }

    private function formatAmount(float $value): string
    {
        if ($this->markupService->usesIntegerStorePrices()) {
            return (string) (int) round($value);
        }

        $formatted = number_format($value, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }
}
