<?php
declare(strict_types=1);

function import_table_has_column(PDO $pdo, string $table, string $column, bool $refresh = false): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if ($refresh) {
        unset($cache[$key]);
    }
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

/**
 * Asigură coloana pNameMarketplace pe coadă + catalog (titlu marketplace, separat de pName = site).
 */
function import_ensure_name_marketplace_columns(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    foreach (['import_produse', 'produse'] as $table) {
        if (import_table_has_column($pdo, $table, 'pNameMarketplace', true)) {
            continue;
        }
        try {
            $pdo->exec(
                "ALTER TABLE `{$table}`
                 ADD COLUMN `pNameMarketplace` VARCHAR(500) NULL DEFAULT NULL
                 AFTER `pName`"
            );
            import_table_has_column($pdo, $table, 'pNameMarketplace', true);
        } catch (Throwable $e) {
            error_log('[import_ensure_name_marketplace_columns] ' . $table . ': ' . $e->getMessage());
        }
    }
}

/**
 * Flag TecDoc: produs deja matchuit (titlu/descriere/structură) — cronul actualizează doar preț/stoc.
 */
function import_ensure_tecdoc_matched_columns(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    foreach (['import_produse', 'produse'] as $table) {
        if (!import_table_has_column($pdo, $table, 'tecdoc_matched', true)) {
            try {
                $pdo->exec(
                    "ALTER TABLE `{$table}`
                     ADD COLUMN `tecdoc_matched` TINYINT(1) NOT NULL DEFAULT 0"
                );
                import_table_has_column($pdo, $table, 'tecdoc_matched', true);
            } catch (Throwable $e) {
                error_log('[import_ensure_tecdoc_matched_columns] ' . $table . '.tecdoc_matched: ' . $e->getMessage());
            }
        }
        if (!import_table_has_column($pdo, $table, 'tecdoc_matched_at', true)) {
            try {
                $pdo->exec(
                    "ALTER TABLE `{$table}`
                     ADD COLUMN `tecdoc_matched_at` DATETIME NULL DEFAULT NULL"
                );
                import_table_has_column($pdo, $table, 'tecdoc_matched_at', true);
            } catch (Throwable $e) {
                error_log('[import_ensure_tecdoc_matched_columns] ' . $table . '.tecdoc_matched_at: ' . $e->getMessage());
            }
        }
    }
}

function import_row_looks_tecdoc_matched(array $row): bool
{
    if (!empty($row['force_tecdoc_rematch']) || !empty($row['force_rematch'])) {
        return false;
    }
    if (!empty($row['tecdoc_matched'])) {
        return true;
    }

    $name = trim((string) ($row['pName'] ?? ''));
    $note = trim((string) ($row['pNoteWebsite'] ?? $row['pNote'] ?? ''));
    if ($name === '' || $note === '') {
        return false;
    }

    $hasStructure = str_contains($note, 'Specificatii tehnice:')
        || str_contains($note, 'Specificații tehnice:')
        || str_contains($note, 'tecdoc-desc-sheet')
        || str_contains($note, 'Compatibil cu urmatoarele modele auto:');
    if (!$hasStructure) {
        return false;
    }

    $raw = json_decode((string) ($row['raw_json'] ?? ''), true);
    if (!is_array($raw)) {
        return true;
    }
    if (!empty($raw['force_tecdoc_rematch']) || !empty($raw['force_rematch'])) {
        return false;
    }
    $status = strtolower(trim((string) ($raw['match_status'] ?? '')));
    if ($status !== '' && !in_array($status, ['exact', 'probable', 'conflict'], true)) {
        return false;
    }

    return !empty($raw['tecdoc_import_enrichment']['found'])
        || !empty($raw['import_pro_card'])
        || !empty($raw['import_base_applied'])
        || $status !== '';
}

/**
 * @return array{table:string,id:int,row:array<string,mixed>}|null
 */
function import_lookup_tecdoc_matched_record(PDO $pdo, string $code, string $brand = ''): ?array
{
    import_ensure_tecdoc_matched_columns($pdo);
    $probe = import_apply_identity_to_row(['pCode' => $code, 'pBrand' => $brand]);
    $codeNorm = (string) ($probe['pCodeNorm'] ?? '');
    if ($codeNorm === '') {
        return null;
    }
    $brandNorm = (string) ($probe['pBrandNorm'] ?? '');

    $tables = [
        ['produse', 'COALESCE(status, 1) <> 0'],
        ['import_produse', "status = 'pending'"],
    ];
    foreach ($tables as [$table, $statusSql]) {
        $hasNorm = import_table_has_column($pdo, $table, 'pCodeNorm');
        if ($hasNorm) {
            $sql = "SELECT * FROM `{$table}` WHERE pCodeNorm = ?"
                . ($brandNorm !== '' ? ' AND pBrandNorm = ?' : '')
                . " AND {$statusSql} ORDER BY id DESC LIMIT 8";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($brandNorm !== '' ? [$codeNorm, $brandNorm] : [$codeNorm]);
        } else {
            $sql = "SELECT * FROM `{$table}` WHERE TRIM(pCode) = ? AND {$statusSql} ORDER BY id DESC LIMIT 8";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([trim($code)]);
        }
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if ($brandNorm !== '' && !$hasNorm
                && import_normalize_supplier_brand((string) ($row['pBrand'] ?? '')) !== $brandNorm) {
                continue;
            }
            if (!import_row_looks_tecdoc_matched($row) && empty($row['tecdoc_matched'])) {
                continue;
            }
            return ['table' => $table, 'id' => (int) $row['id'], 'row' => $row];
        }
    }

    return null;
}

function import_mark_row_tecdoc_matched(PDO $pdo, string $table, int $id, bool $matched = true): void
{
    if ($id <= 0 || !in_array($table, ['produse', 'import_produse'], true)) {
        return;
    }
    import_ensure_tecdoc_matched_columns($pdo);
    if (!import_table_has_column($pdo, $table, 'tecdoc_matched')) {
        return;
    }
    $sets = ['tecdoc_matched = ?'];
    $params = [$matched ? 1 : 0];
    if (import_table_has_column($pdo, $table, 'tecdoc_matched_at')) {
        $sets[] = 'tecdoc_matched_at = ?';
        $params[] = $matched ? date('Y-m-d H:i:s') : null;
    }
    $params[] = $id;
    $pdo->prepare('UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1')
        ->execute($params);
}

function import_cron_product_force_rematch(array $product): bool
{
    if (!empty($product['force_tecdoc_rematch']) || !empty($product['force_rematch'])) {
        return true;
    }
    $raw = $product['raw_json'] ?? null;
    if (is_string($raw)) {
        $raw = json_decode($raw, true);
    }
    if (is_array($raw) && (!empty($raw['force_tecdoc_rematch']) || !empty($raw['force_rematch']))) {
        return true;
    }

    return false;
}

/**
 * True = produs deja matchuit → cronul sare peste rebuild TecDoc (titlu/descriere/structură).
 */
function import_cron_should_skip_tecdoc_rematch(PDO $pdo, array $scanProduct): bool
{
    if (import_cron_product_force_rematch($scanProduct)) {
        return false;
    }
    $code = trim((string) (
        $scanProduct['sku_supplier']
        ?? $scanProduct['art_nr']
        ?? $scanProduct['pCode']
        ?? $scanProduct['sku']
        ?? ''
    ));
    $brand = trim((string) (
        $scanProduct['matched_brand']
        ?? $scanProduct['force_brand']
        ?? $scanProduct['brand']
        ?? $scanProduct['pBrand']
        ?? ''
    ));
    if ($code === '') {
        return false;
    }

    return import_lookup_tecdoc_matched_record($pdo, $code, $brand) !== null;
}

/**
 * Actualizează doar preț/stoc pe produsul deja matchuit (fără rematch TecDoc).
 *
 * @param array<string, mixed> $scanProduct
 * @param array{table:string,id:int,row:array<string,mixed>} $existing
 */
function import_cron_update_price_stock_only(PDO $pdo, array $scanProduct, array $existing): bool
{
    $table = (string) ($existing['table'] ?? '');
    $id = (int) ($existing['id'] ?? 0);
    if ($id <= 0 || !in_array($table, ['produse', 'import_produse'], true)) {
        return false;
    }

    $price = null;
    foreach (['price_csv', 'price_net', 'pBasePrice', 'price', 'pPrice'] as $key) {
        if (isset($scanProduct[$key]) && is_numeric($scanProduct[$key]) && (float) $scanProduct[$key] > 0) {
            $price = round((float) $scanProduct[$key], 2);
            break;
        }
    }
    $stock = null;
    foreach (['stock', 'pStock', 'qty', 'quantity'] as $key) {
        if (array_key_exists($key, $scanProduct) && trim((string) $scanProduct[$key]) !== '') {
            $stock = trim((string) $scanProduct[$key]);
            break;
        }
    }

    $sets = [];
    $params = [];
    if ($price !== null) {
        // Pe catalog: pBasePrice = achiziție; pe coadă actualizăm ambele dacă există.
        if (import_table_has_column($pdo, $table, 'pBasePrice')) {
            $sets[] = 'pBasePrice = ?';
            $params[] = (string) $price;
        }
        if ($table === 'import_produse') {
            $sets[] = 'pPrice = ?';
            $params[] = (string) $price;
        }
    }
    if ($stock !== null && import_table_has_column($pdo, $table, 'pStock')) {
        $sets[] = 'pStock = ?';
        $params[] = $stock;
    }
    if ($sets === []) {
        return false;
    }
    $params[] = $id;
    $pdo->prepare('UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1')
        ->execute($params);

    // Reafirmă flag-ul matched (inclusiv backfill pe rânduri detectate heuristic).
    import_mark_row_tecdoc_matched($pdo, $table, $id, true);

    return true;
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

function import_find_pending_staging_id(PDO $pdo, array $row): ?int
{
    $identity = import_product_identity($row);
    if ($identity['pCodeNorm'] === '') {
        return null;
    }

    if (import_table_has_column($pdo, 'import_produse', 'pCodeNorm')) {
        $stmt = $pdo->prepare(
            'SELECT id FROM import_produse
             WHERE pCodeNorm = ? AND pBrandNorm = ? AND status = ?
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([$identity['pCodeNorm'], $identity['pBrandNorm'], 'pending']);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    $code = trim((string) ($row['pCode'] ?? ''));
    $brand = trim((string) ($row['pBrand'] ?? ''));
    if ($code === '') {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT id FROM import_produse
         WHERE pCode = ? AND pBrand = ? AND status = ?
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([$code, $brand, 'pending']);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
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
        'pName', 'pNameMarketplace', 'pCode', 'pBrand', 'pMarca', 'pModel', 'pMotorizare', 'pCar',
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
        'pName', 'pNameMarketplace', 'pCode', 'pBrand', 'pMarca', 'pModel', 'pMotorizare', 'pCar',
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
    if (import_table_has_column($pdo, 'import_produse', 'import_lane')) {
        $columns[] = 'import_lane';
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
        'pNameMarketplace',
    ];
}

function import_prepare_staging_insert(PDO $pdo, array $row): array
{
    $row = import_apply_identity_to_row($row);
    $nullable = import_staging_nullable_columns();
    $prepared = [];
    foreach (import_staging_insert_columns($pdo) as $column) {
        if (array_key_exists($column, $row)) {
            $value = $row[$column];
            // Coloane nullable (ex. pBasePrice = DECIMAL): '' e respins de MySQL în
            // mod strict (STRICT_TRANS_TABLES → eroare 1366). Normalizează '' → NULL.
            if (($value === '' || $value === false) && in_array($column, $nullable, true)) {
                $value = null;
            }
            $prepared[$column] = $value;
            continue;
        }
        $prepared[$column] = in_array($column, $nullable, true) ? null : '';
    }

    return $prepared;
}

function import_apply_markup_to_live_row(array $row): array
{
    // Prețul final nu se recalculează automat la publicare.
    // Se setează doar când alegi produsele și aplici Adaos comercial (manual).
    return $row;
}

function import_build_live_insert_data(PDO $pdo, array $row): array
{
    $row = import_apply_markup_to_live_row($row);
    $row = import_apply_identity_to_row($row);

    $columns = import_live_insert_columns($pdo);
    $nullableColumns = [
        'pBasePrice', 'pMarkupRuleId', 'pMarkupRuleName', 'pMarkupAppliedAt',
        'pCodeNorm', 'pBrandNorm', 'pNoteWebsite', 'pNoteMarketplace', 'pNameMarketplace',
    ];
    $data = [];
    foreach ($columns as $column) {
        if (array_key_exists($column, $row)) {
            $value = $row[$column];
            // Coloane nullable (ex. pBasePrice = DECIMAL): '' e respins de MySQL în
            // mod strict (STRICT_TRANS_TABLES → eroare 1366). Normalizează '' → NULL.
            if (($value === '' || $value === false) && in_array($column, $nullableColumns, true)) {
                $value = null;
            }
            $data[$column] = $value;
            continue;
        }

        $data[$column] = in_array($column, $nullableColumns, true) ? null : '';
    }

    if (
        array_key_exists('pNameMarketplace', $data)
        && trim((string) ($data['pNameMarketplace'] ?? '')) === ''
        && trim((string) ($data['pName'] ?? '')) !== ''
    ) {
        $data['pNameMarketplace'] = (string) $data['pName'];
    }

    // Coloana din DB este NOT NULL DEFAULT 'Da' — importul nu are voie sa trimita NULL
    if (!isset($data['pCurierLivrare']) || !in_array(trim((string) $data['pCurierLivrare']), ['Da', 'Nu'], true)) {
        $data['pCurierLivrare'] = 'Da';
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
        import_require_system_file('import-image-validate.php');
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
        if (function_exists('import_mark_row_tecdoc_matched') && import_row_looks_tecdoc_matched($prepared)) {
            import_mark_row_tecdoc_matched($pdo, 'produse', $productId, true);
        }

        $result = [
            'action' => 'inserted',
            'product_id' => $productId,
            'existing_id' => null,
        ];
        import_rag_index_after_publish($pdo, $result, $prepared);

        return $result;
    }

    $existingId = (int)$existing['id'];
    if ($publishMode === 'force') {
        $productId = import_insert_live_product($pdo, $prepared);
        import_sync_products_oem($pdo, $productId, $prepared, 'import');
        if (function_exists('import_mark_row_tecdoc_matched') && import_row_looks_tecdoc_matched($prepared)) {
            import_mark_row_tecdoc_matched($pdo, 'produse', $productId, true);
        }

        $result = [
            'action' => 'forced',
            'product_id' => $productId,
            'existing_id' => $existingId,
        ];
        import_rag_index_after_publish($pdo, $result, $prepared);

        return $result;
    }

    if ($publishMode === 'update') {
        $productId = import_update_live_product($pdo, $existingId, $prepared, $existing);
        import_sync_products_oem($pdo, $productId, $prepared, 'import');
        if (function_exists('import_mark_row_tecdoc_matched') && import_row_looks_tecdoc_matched($prepared)) {
            import_mark_row_tecdoc_matched($pdo, 'produse', $productId, true);
        }

        $result = [
            'action' => 'updated',
            'product_id' => $productId,
            'existing_id' => $existingId,
        ];
        import_rag_index_after_publish($pdo, $result, $prepared);

        return $result;
    }

    return [
        'action' => 'skipped',
        'product_id' => $existingId,
        'existing_id' => $existingId,
    ];
}

/** @param array<string, mixed> $publishResult @param array<string, mixed> $prepared */
function import_rag_index_after_publish(PDO $pdo, array $publishResult, array $prepared = []): void
{
    $action = (string) ($publishResult['action'] ?? '');
    if (!in_array($action, ['inserted', 'updated', 'forced'], true)) {
        return;
    }

    $productId = (int) ($publishResult['product_id'] ?? 0);
    if ($productId <= 0) {
        return;
    }

    try {
        if (!class_exists(\Besoiu\Services\AiRag\AiProductCatalogRagIndexerService::class)) {
            return;
        }

        $indexer = \Besoiu\Services\AiRag\AiProductCatalogRagIndexerService::create();
        $indexer->queueProductId($pdo, $productId);
    } catch (Throwable $exception) {
        error_log('[import_rag_index] product_id=' . $productId . ' ' . $exception->getMessage());
    }
}

function import_sync_products_oem(PDO $pdo, int $productId, array $product, string $source = 'import'): void
{
    if ($productId <= 0) {
        return;
    }

    import_require_system_file('products_oem.php');

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
    if (($stats['rag_indexed'] ?? 0) > 0) {
        $parts[] = 'Indexate AI/RAG: ' . (int)$stats['rag_indexed'];
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
