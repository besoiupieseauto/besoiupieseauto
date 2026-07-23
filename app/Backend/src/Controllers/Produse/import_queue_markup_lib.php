<?php
declare(strict_types=1);

/**
 * Adaos comercial pe coada import (import_produse) — selectate sau filtrate.
 */

use Besoiu\Services\AdaosComercial\AdaosComercialService;
use Besoiu\Services\ImportReviewQueueService;

/**
 * @return array{
 *   supplier: string,
 *   status: string,
 *   lane: string,
 *   marca: string,
 *   model: string,
 *   motorizare: string,
 *   brand: string,
 *   an: string
 * }
 */
function import_queue_normalize_markup_filters(array $input): array
{
    return [
        'supplier' => trim((string) ($input['supplier'] ?? '')),
        'status' => trim((string) ($input['status'] ?? 'pending')),
        'lane' => trim((string) ($input['lane'] ?? 'standard')),
        'marca' => trim((string) ($input['marca'] ?? '')),
        'model' => trim((string) ($input['model'] ?? '')),
        'motorizare' => trim((string) ($input['motorizare'] ?? '')),
        'brand' => trim((string) ($input['brand'] ?? '')),
        'an' => trim((string) ($input['an'] ?? '')),
    ];
}

/**
 * @param list<int> $ids
 * @return array{success:bool,message:string,updated_count?:int,matched_count?:int,zero_base_count?:int,total_delta?:string}
 */
function import_queue_apply_markup(PDO $pdo, array $ids, array $filters, array $options): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    $filters = import_queue_normalize_markup_filters($filters);

    $mode = trim((string) ($options['mode'] ?? 'conditional'));
    if (!in_array($mode, ['global', 'conditional', 'rule'], true)) {
        $mode = 'conditional';
    }

    $ruleId = (int) ($options['rule_id'] ?? 0);
    if ($mode === 'rule' && $ruleId <= 0) {
        return ['success' => false, 'message' => 'Selectează o regulă de adaos.'];
    }

    $rows = import_queue_fetch_rows_for_markup($pdo, $ids, $filters);
    if ($rows === []) {
        return ['success' => false, 'message' => 'Nu există produse în coadă pentru selecția/filtrele alese.'];
    }

    $markupService = new AdaosComercialService();
    $markupService->getGlobalCommercialMarkupPercent();
    $markupService->getCommercialVatPercent();
    $markupService->getGlobalPriceRoundMode();
    $matchConditional = $mode === 'conditional';
    $explicitRuleId = $mode === 'rule' ? $ruleId : null;

    if ($mode === 'rule') {
        $rule = $markupService->getById($ruleId);
        if ($rule === null) {
            return ['success' => false, 'message' => 'Regula de adaos selectată nu există.'];
        }
    }

    $updateStmt = $pdo->prepare(
        'UPDATE import_produse SET
            pBasePrice = :pBasePrice,
            pPrice = :pPrice,
            pMarkupRuleId = :pMarkupRuleId,
            pMarkupRuleName = :pMarkupRuleName,
            pMarkupAppliedAt = :pMarkupAppliedAt
         WHERE id = :id AND status = :status'
    );

    $updatedCount = 0;
    $zeroBaseCount = 0;
    $totalDelta = 0.0;
    $statusFilter = $filters['status'] !== '' ? $filters['status'] : 'pending';

    $pdo->beginTransaction();
    try {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $baseRaw = trim((string) ($row['pBasePrice'] ?? ''));
            if ($baseRaw === '') {
                $baseRaw = trim((string) ($row['pPrice'] ?? ''));
            }
            if ($baseRaw === '' || (float) str_replace(',', '.', $baseRaw) <= 0) {
                $zeroBaseCount++;
                continue;
            }

            $pricing = $markupService->applyAutomaticMarkup(
                $row,
                $row,
                $matchConditional,
                $explicitRuleId,
                true
            );

            $data = $pricing['data'] ?? [];
            $updateStmt->execute([
                ':pBasePrice' => $data['pBasePrice'] ?? null,
                ':pPrice' => $data['pPrice'] ?? null,
                ':pMarkupRuleId' => $data['pMarkupRuleId'] ?? null,
                ':pMarkupRuleName' => $data['pMarkupRuleName'] ?? null,
                ':pMarkupAppliedAt' => $data['pMarkupAppliedAt'] ?? null,
                ':id' => (int) ($row['id'] ?? 0),
                ':status' => $statusFilter,
            ]);

            if ($updateStmt->rowCount() > 0) {
                $updatedCount++;
                $totalDelta += (float) ($pricing['delta'] ?? 0);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    $modeLabel = match ($mode) {
        'conditional' => 'reguli active',
        'rule' => 'regula #' . $ruleId,
        default => 'setări Adaos comercial',
    };

    $message = 'Preț final recalculat (' . $modeLabel . ') pe ' . $updatedCount . ' produse din coadă (preț bază păstrat de la import).';
    if ($zeroBaseCount > 0) {
        $message .= ' ' . $zeroBaseCount . ' produse sărite (fără preț bază).';
    }

    return [
        'success' => $updatedCount > 0,
        'message' => $message,
        'updated_count' => $updatedCount,
        'matched_count' => count($rows),
        'zero_base_count' => $zeroBaseCount,
        'total_delta' => number_format($totalDelta, 2, '.', ''),
    ];
}

/**
 * @param list<int> $ids
 * @return list<array<string, mixed>>
 */
function import_queue_fetch_rows_for_markup(PDO $pdo, array $ids, array $filters): array
{
    $filters = import_queue_normalize_markup_filters($filters);
    $service = new ImportReviewQueueService($pdo);

    if ($ids !== []) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $status = $filters['status'] !== '' ? $filters['status'] : 'pending';
        $stmt = $pdo->prepare(
            'SELECT id, status, pCode, pName, pBrand, pMarca, pModel, pMotorizare, pCategory, pSubcategory,
                    pPrice, pBasePrice, pStock, pSupplier, pMarkupRuleId, pMarkupRuleName, pMarkupAppliedAt,
                    pCompatibilitati, pOem, pNote, pImages, pImageSource, raw_json
             FROM import_produse
             WHERE id IN (' . $placeholders . ') AND status = ?
             ORDER BY id DESC'
        );
        $params = array_merge($ids, [$status]);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $whereData = $service->buildQueueWhere(
        $filters['status'],
        $filters['supplier'],
        $filters['lane'],
        $filters
    );
    $whereSql = $whereData['where'] !== [] ? ' WHERE ' . implode(' AND ', $whereData['where']) : '';

    $sql = 'SELECT id, status, pCode, pName, pBrand, pMarca, pModel, pMotorizare, pCategory, pSubcategory,
                   pPrice, pBasePrice, pStock, pSupplier, pMarkupRuleId, pMarkupRuleName, pMarkupAppliedAt,
                   pCompatibilitati, pOem, pNote, pImages, pImageSource, raw_json
            FROM import_produse' . $whereSql . ' ORDER BY id DESC LIMIT 5000';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($whereData['params']);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
