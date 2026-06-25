<?php
declare(strict_types=1);

function import_table_has_column(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
         LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    $cache[$key] = (bool)$stmt->fetchColumn();

    return $cache[$key];
}

function import_product_identity(array $row): array
{
    $codeNorm = import_normalize_product_code((string)($row['pCode'] ?? ''));
    $brandNorm = import_normalize_supplier_brand((string)($row['pBrand'] ?? ''));

    return [
        'pCodeNorm' => $codeNorm,
        'pBrandNorm' => $brandNorm,
        'identity_key' => $codeNorm . '|' . str_replace(' ', '', $brandNorm),
    ];
}

function import_apply_identity_to_row(array $row): array
{
    $identity = import_product_identity($row);
    $row['pCodeNorm'] = $identity['pCodeNorm'];
    $row['pBrandNorm'] = $identity['pBrandNorm'];

    return $row;
}

function import_resolve_publish_mode(string $mode): string
{
    $mode = strtolower(trim($mode));

    return in_array($mode, ['skip', 'update', 'force'], true) ? $mode : 'skip';
}

function import_find_existing_product(PDO $pdo, array $row): ?array
{
    $identity = import_product_identity($row);
    if ($identity['pCodeNorm'] === '') {
        return null;
    }

    if (import_table_has_column($pdo, 'produse', 'pCodeNorm')) {
        $stmt = $pdo->prepare(
            'SELECT id, pName, pCode, pBrand, pPrice, pImages, randomn_id
             FROM produse
             WHERE pCodeNorm = ? AND pBrandNorm = ?
             ORDER BY id ASC
             LIMIT 1'
        );
        $stmt->execute([$identity['pCodeNorm'], $identity['pBrandNorm']]);
        $found = $stmt->fetch(PDO::FETCH_ASSOC);

        return $found ?: null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, pName, pCode, pBrand, pPrice, pImages, randomn_id
         FROM produse
         WHERE REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(pCode)), ' ', ''), '-', ''), '.', ''), '/', '') = ?
         LIMIT 50"
    );
    $stmt->execute([$identity['pCodeNorm']]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
        $candidateIdentity = import_product_identity($candidate);
        if ($candidateIdentity['pBrandNorm'] === $identity['pBrandNorm']) {
            return $candidate;
        }
    }

    return null;
}

function import_live_product_columns(): array
{
    return [
        'pName', 'pCode', 'pBrand', 'pMarca', 'pModel', 'pMotorizare', 'pCar',
        'pPrice', 'pBasePrice', 'pStock', 'pCategory', 'pSubcategory', 'pCompatibilitati',
        'pOem', 'pSupplier', 'pState', 'pCity', 'pNote', 'pNoteWebsite', 'pNoteMarketplace', 'pImages', 'pImageSource',
        'pShipping', 'pWarranty', 'pReturn', 'pWhatsapp',
        'pMarkupRuleId', 'pMarkupRuleName', 'pMarkupAppliedAt',
        'pCurierLivrare',
        'status', 'randomn_id', 'id_users', 'connect_id',
    ];
}

/** @return array<int, string> */
function import_live_insert_columns(PDO $pdo): array
{
    $columns = import_live_product_columns();
    if (import_table_has_column($pdo, 'produse', 'pCodeNorm')) {
        $columns[] = 'pCodeNorm';
        $columns[] = 'pBrandNorm';
    }

    return array_values(array_unique(array_filter(
        $columns,
        static fn (string $column): bool => import_table_has_column($pdo, 'produse', $column)
    )));
}

function import_staging_base_columns(): array
{
    return [
        'pName', 'pCode', 'pBrand', 'pMarca', 'pModel', 'pMotorizare', 'pCar',
        'pPrice', 'pBasePrice', 'pStock', 'pCategory', 'pSubcategory', 'pCompatibilitati',
        'pOem', 'pSupplier', 'pState', 'pCity', 'pNote', 'pNoteWebsite', 'pNoteMarketplace', 'pImages', 'pImageSource',
        'pShipping', 'pWarranty', 'pReturn', 'pWhatsapp',
        'pMarkupRuleId', 'pMarkupRuleName', 'pMarkupAppliedAt',
        'raw_json', 'status',
    ];
}

function import_staging_insert_columns(PDO $pdo): array
{
    $columns = import_staging_base_columns();
    if (import_table_has_column($pdo, 'import_produse', 'pCodeNorm')) {
        $columns[] = 'pCodeNorm';
        $columns[] = 'pBrandNorm';
    }

    return array_values(array_filter(
        $columns,
        static fn (string $column): bool => import_table_has_column($pdo, 'import_produse', $column)
    ));
}

/** @return array<int, string> */
function import_staging_nullable_columns(): array
{
    return [
        'pBasePrice',
        'pMarkupRuleId',
        'pMarkupRuleName',
        'pMarkupAppliedAt',
        'pCodeNorm',
        'pBrandNorm',
        'pNoteWebsite',
        'pNoteMarketplace',
    ];
}

function import_prepare_staging_insert(PDO $pdo, array $row): array
{
    $row = import_apply_identity_to_row($row);
    $nullable = import_staging_nullable_columns();
    $prepared = [];
    foreach (import_staging_insert_columns($pdo) as $column) {
        if (array_key_exists($column, $row)) {
            $prepared[$column] = $row[$column];
            continue;
        }
        $prepared[$column] = in_array($column, $nullable, true) ? null : '';
    }

    return $prepared;
}

function import_apply_markup_to_live_row(array $row): array
{
    $markupService = new \Evasystem\Controllers\AdaosComercial\AdaosComercialService();
    $pricing = $markupService->applyAutomaticMarkup([
        'pName' => $row['pName'] ?? '',
        'pCode' => $row['pCode'] ?? '',
        'pBrand' => $row['pBrand'] ?? '',
        'pMarca' => $row['pMarca'] ?? '',
        'pModel' => $row['pModel'] ?? '',
        'pMotorizare' => $row['pMotorizare'] ?? '',
        'pCar' => $row['pCar'] ?? '',
        'pBasePrice' => $row['pBasePrice'] ?? ($row['pPrice'] ?? ''),
        'pStock' => $row['pStock'] ?? '',
        'pCategory' => $row['pCategory'] ?? '',
        'pSubcategory' => $row['pSubcategory'] ?? '',
        'pCompatibilitati' => $row['pCompatibilitati'] ?? '',
        'pOem' => $row['pOem'] ?? '',
        'pSupplier' => $row['pSupplier'] ?? '',
        'pState' => $row['pState'] ?? '',
        'pCity' => $row['pCity'] ?? '',
        'pNote' => $row['pNote'] ?? '',
        'pImages' => $row['pImages'] ?? '',
        'pImageSource' => $row['pImageSource'] ?? '',
        'pShipping' => $row['pShipping'] ?? '',
        'pWarranty' => $row['pWarranty'] ?? '',
        'pReturn' => $row['pReturn'] ?? '',
        'pWhatsapp' => $row['pWhatsapp'] ?? '',
    ]);

    return array_merge($row, $pricing['data']);
}

function import_build_live_insert_data(PDO $pdo, array $row): array
{
    $row = import_apply_markup_to_live_row($row);
    $row = import_apply_identity_to_row($row);

    $columns = import_live_insert_columns($pdo);
    $nullableColumns = [
        'pBasePrice', 'pMarkupRuleId', 'pMarkupRuleName', 'pMarkupAppliedAt',
        'pCodeNorm', 'pBrandNorm', 'pNoteWebsite', 'pNoteMarketplace', 'pCurierLivrare',
    ];
    $data = [];
    foreach ($columns as $column) {
        if (array_key_exists($column, $row)) {
            $data[$column] = $row[$column];
            continue;
        }

        $data[$column] = in_array($column, $nullableColumns, true) ? null : '';
    }

    $data['status'] = 1;
    $data['randomn_id'] = bin2hex(random_bytes(8));
    $data['id_users'] = (string)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 126);
    $data['connect_id'] = (string)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 126);

    return [
        'columns' => $columns,
        'data' => $data,
    ];
}

function import_insert_live_product(PDO $pdo, array $row): int
{
    $payload = import_build_live_insert_data($pdo, $row);
    $columns = $payload['columns'];
    $data = $payload['data'];
    $placeholders = implode(',', array_map(static fn (string $column): string => ':' . $column, $columns));
    $stmt = $pdo->prepare('INSERT INTO produse (`' . implode('`,`', $columns) . '`) VALUES (' . $placeholders . ')');
    $executeData = [];
    foreach ($columns as $column) {
        $executeData[$column] = $data[$column] ?? null;
    }
    $stmt->execute($executeData);

    return (int)$pdo->lastInsertId();
}

function import_existing_product_images_empty(?array $existing): bool
{
    if ($existing === null) {
        return true;
    }

    if (!function_exists('besoiu_import_row_image_url')) {
        require_once dirname(__DIR__, 4) . '/system/import-image-validate.php';
    }

    return besoiu_import_row_image_url($existing) === '';
}

function import_update_live_product(PDO $pdo, int $existingId, array $row, ?array $existing = null): int
{
    $row = import_apply_markup_to_live_row($row);
    $row = import_apply_identity_to_row($row);

    $updateFields = [
        'pName', 'pCode', 'pBrand', 'pMarca', 'pModel', 'pMotorizare', 'pCar',
        'pPrice', 'pBasePrice', 'pStock', 'pCategory', 'pSubcategory', 'pCompatibilitati',
        'pOem', 'pSupplier', 'pState', 'pCity', 'pNote', 'pImageSource',
        'pShipping', 'pWarranty', 'pReturn', 'pWhatsapp',
        'pMarkupRuleId', 'pMarkupRuleName', 'pMarkupAppliedAt',
    ];

    if (import_table_has_column($pdo, 'produse', 'pCodeNorm')) {
        $updateFields[] = 'pCodeNorm';
        $updateFields[] = 'pBrandNorm';
    }

    if (!import_existing_product_images_empty($existing)) {
        $newUrl = import_row_image_url($row);
        $newSource = (string) ($row['pImageSource'] ?? '');
        $oldUrl = import_row_image_url($existing);
        $oldSource = (string) ($existing['pImageSource'] ?? '');
        $forceImages = !empty($row['__force_image_update']);
        $canReplace = $forceImages
            || ($newUrl !== '' && (
                $oldUrl === ''
                || (import_image_url_is_trusted($newUrl, $newSource)
                    && !import_image_url_is_trusted($oldUrl, $oldSource))
            ));
        if (!$canReplace || trim($newUrl) === '') {
            unset($row['pImages']);
        } else {
            $updateFields[] = 'pImages';
        }
    } else {
        $updateFields[] = 'pImages';
    }

    $sets = [];
    $data = ['row_id' => $existingId];
    foreach ($updateFields as $field) {
        if (!array_key_exists($field, $row)) {
            continue;
        }
        $sets[] = "`{$field}` = :{$field}";
        $data[$field] = $row[$field];
    }

    if ($sets === []) {
        return $existingId;
    }

    $sql = 'UPDATE produse SET ' . implode(', ', $sets) . ' WHERE id = :row_id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);

    return $existingId;
}

function import_publish_prepared_row(PDO $pdo, array $prepared, string $publishMode): array
{
    if (function_exists('import_ensure_publish_image')) {
        $prepared = import_ensure_publish_image($prepared);
    }

    $publishMode = import_resolve_publish_mode($publishMode);
    $existing = import_find_existing_product($pdo, $prepared);

    if ($existing === null) {
        $productId = import_insert_live_product($pdo, $prepared);
        import_sync_products_oem($pdo, $productId, $prepared, 'import');

        return [
            'action' => 'inserted',
            'product_id' => $productId,
            'existing_id' => null,
        ];
    }

    $existingId = (int)$existing['id'];
    if ($publishMode === 'force') {
        $productId = import_insert_live_product($pdo, $prepared);
        import_sync_products_oem($pdo, $productId, $prepared, 'import');

        return [
            'action' => 'forced',
            'product_id' => $productId,
            'existing_id' => $existingId,
        ];
    }

    if ($publishMode === 'update') {
        $productId = import_update_live_product($pdo, $existingId, $prepared, $existing);
        import_sync_products_oem($pdo, $productId, $prepared, 'import');

        return [
            'action' => 'updated',
            'product_id' => $productId,
            'existing_id' => $existingId,
        ];
    }

    return [
        'action' => 'skipped',
        'product_id' => $existingId,
        'existing_id' => $existingId,
    ];
}

function import_sync_products_oem(PDO $pdo, int $productId, array $product, string $source = 'import'): void
{
    if ($productId <= 0) {
        return;
    }

    require_once dirname(__DIR__, 4) . '/system/products_oem.php';

    try {
        products_oem_sync_product($pdo, $productId, $product, $source);
    } catch (Throwable $exception) {
        error_log('[import_sync_products_oem] product_id=' . $productId . ' ' . $exception->getMessage());
    }
}

function import_finalize_staging_row(PDO $pdo, int $importId, array $publishResult): void
{
    $productId = (int)($publishResult['product_id'] ?? 0);
    $action = (string)($publishResult['action'] ?? 'inserted');

    if ($action === 'skipped') {
        if (import_table_has_column($pdo, 'import_produse', 'conflict_product_id')) {
            $pdo->prepare(
                "UPDATE import_produse
                 SET status='conflict_live', imported_product_id=?, conflict_product_id=?, conflict_reason='duplicate_live'
                 WHERE id=?"
            )->execute([$productId, $productId, $importId]);
            return;
        }

        $pdo->prepare("UPDATE import_produse SET status='deleted' WHERE id=?")->execute([$importId]);
        return;
    }

    $pdo->prepare("UPDATE import_produse SET status='imported', imported_product_id=? WHERE id=?")
        ->execute([$productId, $importId]);
}

function import_build_publish_message(array $stats): string
{
    $parts = [];
    if (($stats['added'] ?? 0) > 0) {
        $parts[] = 'Adaugate: ' . (int)$stats['added'];
    }
    if (($stats['updated'] ?? 0) > 0) {
        $parts[] = 'Actualizate: ' . (int)$stats['updated'];
    }
    if (($stats['skipped'] ?? 0) > 0) {
        $parts[] = 'Omise (duplicate): ' . (int)$stats['skipped'];
    }
    if (($stats['forced'] ?? 0) > 0) {
        $parts[] = 'Fortate ca duplicate: ' . (int)$stats['forced'];
    }

    if ($parts === []) {
        return 'Niciun produs procesat.';
    }

    return implode('. ', $parts) . '.';
}

function import_collect_publish_stats(array &$stats, array $publishResult, bool $tecdocUsed = false): void
{
    $action = (string)($publishResult['action'] ?? 'inserted');
    if ($action === 'inserted') {
        $stats['added'] = (int)($stats['added'] ?? 0) + 1;
    } elseif ($action === 'updated') {
        $stats['updated'] = (int)($stats['updated'] ?? 0) + 1;
    } elseif ($action === 'skipped') {
        $stats['skipped'] = (int)($stats['skipped'] ?? 0) + 1;
        $stats['conflicts'][] = [
            'import_id' => (int)($publishResult['import_id'] ?? 0),
            'existing_id' => (int)($publishResult['existing_id'] ?? 0),
            'pCode' => (string)($publishResult['pCode'] ?? ''),
            'pBrand' => (string)($publishResult['pBrand'] ?? ''),
        ];
    } elseif ($action === 'forced') {
        $stats['forced'] = (int)($stats['forced'] ?? 0) + 1;
    }

    if ($tecdocUsed) {
        $stats['tecdoc'] = (int)($stats['tecdoc'] ?? 0) + 1;
    }
}
