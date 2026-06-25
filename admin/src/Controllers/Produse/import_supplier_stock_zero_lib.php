<?php

declare(strict_types=1);

/**
 * Reguli per furnizor: stoc zero / indisponibil la scanare feed.
 *
 * Câmpuri furnizori: stock_zero_mode, scan_include_zero_stock, scan_skip_unavailable.
 */

/** @var array<string, array<string, mixed>>|null */
$GLOBALS['__import_supplier_scan_rules_index'] = null;

function import_supplier_scan_rules_reset_cache(): void
{
    $GLOBALS['__import_supplier_scan_rules_index'] = null;
}

/** @return array<string, mixed> */
function import_supplier_scan_rules_defaults(): array
{
    return [
        'stock_zero_mode' => 'full',
        'scan_include_zero_stock' => 1,
        'scan_skip_unavailable' => 0,
    ];
}

/** @return array<string, array<string, mixed>> */
function import_supplier_scan_rules_index(): array
{
    if (
        isset($GLOBALS['__import_supplier_scan_rules_index'])
        && is_array($GLOBALS['__import_supplier_scan_rules_index'])
    ) {
        return $GLOBALS['__import_supplier_scan_rules_index'];
    }

    if (!function_exists('import_furnizori_catalog_codes')) {
        require_once __DIR__ . '/import_supplier_lib.php';
    }

    $index = [];
    $defaults = import_supplier_scan_rules_defaults();

    try {
        if (!class_exists(\Config\Database::class)) {
            require_once dirname(__DIR__, 3) . '/config/Database.php';
        }
        $pdo = \Config\Database::getDB();
        $stmt = $pdo->query(
            'SELECT code, stock_zero_mode, scan_include_zero_stock, scan_skip_unavailable
             FROM furnizori
             WHERE code IS NOT NULL AND TRIM(code) <> \'\''
        );
        if ($stmt !== false) {
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                if (!is_array($row)) {
                    continue;
                }
                $code = strtoupper(trim((string) ($row['code'] ?? '')));
                if ($code === '') {
                    continue;
                }
                $mode = strtolower(trim((string) ($row['stock_zero_mode'] ?? 'full')));
                if (!in_array($mode, ['hide', 'full', 'out_of_stock'], true)) {
                    $mode = 'full';
                }
                $index[$code] = [
                    'stock_zero_mode' => $mode,
                    'scan_include_zero_stock' => (int) ($row['scan_include_zero_stock'] ?? 1) === 1 ? 1 : 0,
                    'scan_skip_unavailable' => (int) ($row['scan_skip_unavailable'] ?? 0) === 1 ? 1 : 0,
                ];
            }
        }
    } catch (Throwable) {
        // Fallback la default — scanarea continuă fără reguli DB.
    }

    foreach (import_furnizori_catalog_codes() as $catalogCode) {
        $catalogCode = strtoupper(trim((string) $catalogCode));
        if ($catalogCode === '' || isset($index[$catalogCode])) {
            continue;
        }
        $index[$catalogCode] = $defaults;
    }

    $GLOBALS['__import_supplier_scan_rules_index'] = $index;

    return $index;
}

/** @return array<string, mixed> */
function import_supplier_scan_rules_for(string $supplierCode): array
{
    $code = strtoupper(trim($supplierCode));
    if ($code === '') {
        return import_supplier_scan_rules_defaults();
    }

    $index = import_supplier_scan_rules_index();

    return $index[$code] ?? import_supplier_scan_rules_defaults();
}

/** Produs marcat explicit indisponibil la furnizor (text), nu doar cantitate 0. */
function import_supplier_row_is_unavailable(array $row): bool
{
    if (!function_exists('import_supplier_row_stock_raw')) {
        require_once __DIR__ . '/import_supplier_lib.php';
    }

    $raw = import_supplier_row_stock_raw($row);
    if ($raw === '') {
        return false;
    }

    $lower = function_exists('mb_strtolower')
        ? mb_strtolower(trim($raw), 'UTF-8')
        : strtolower(trim($raw));

    foreach (['indisponibil', 'unavailable', 'epuizat', 'out of stock', 'out-of-stock', 'nu', 'no'] as $token) {
        if ($lower === $token || str_contains($lower, $token)) {
            return true;
        }
    }

    return false;
}

/** @return 'positive'|'zero'|'unknown' */
function import_supplier_entry_stock_status(array $entry): string
{
    $status = strtolower(trim((string) ($entry['stock_status'] ?? '')));
    if (in_array($status, ['positive', 'zero', 'unknown'], true)) {
        return $status;
    }

    if (!function_exists('import_parse_supplier_stock')) {
        require_once __DIR__ . '/import_supplier_lib.php';
    }

    $raw = trim((string) ($entry['stock_raw'] ?? ''));
    if ($raw === '') {
        return 'unknown';
    }

    $parsed = import_parse_supplier_stock($raw);
    if ($parsed === null) {
        return 'unknown';
    }

    return $parsed > 0 ? 'positive' : 'zero';
}

/**
 * Filtrează rândul CSV înainte de import conform regulilor furnizorului.
 */
function import_supplier_row_passes_supplier_scan_rules(array $row, string $supplierType): bool
{
    if (!function_exists('import_supplier_stock_status')) {
        require_once __DIR__ . '/import_supplier_lib.php';
    }

    $rules = import_supplier_scan_rules_for($supplierType);

    if ((int) ($rules['scan_skip_unavailable'] ?? 0) === 1 && import_supplier_row_is_unavailable($row)) {
        return false;
    }

    $stockStatus = import_supplier_stock_status($row);
    if ($stockStatus !== 'zero') {
        return true;
    }

    if ((int) ($rules['scan_include_zero_stock'] ?? 1) !== 1) {
        return false;
    }

    return ($rules['stock_zero_mode'] ?? 'full') !== 'hide';
}

/** @param array<string, mixed> $entry */
function import_supplier_enrich_entry_stock(array &$entry, array $row, string $supplierType): void
{
    if (!function_exists('import_supplier_row_stock_raw')) {
        require_once __DIR__ . '/import_supplier_lib.php';
    }

    $entry['stock_raw'] = import_supplier_row_stock_raw($row);
    $entry['stock_status'] = import_supplier_stock_status($row);
    $entry['stock_zero_mode'] = import_supplier_scan_rules_for($supplierType)['stock_zero_mode'] ?? 'full';
}

/**
 * Aplică acțiunea configurată (full / epuizat) pe produsul construit din feed.
 *
 * @param array<string, mixed> $product
 * @param array<string, mixed> $entry
 */
function import_supplier_apply_stock_zero_to_product(array &$product, array $entry): void
{
    $stockStatus = import_supplier_entry_stock_status($entry);
    $mode = strtolower(trim((string) ($entry['stock_zero_mode'] ?? import_supplier_scan_rules_for((string) ($entry['supplier'] ?? ''))['stock_zero_mode'] ?? 'full')));

    if ($stockStatus === 'positive') {
        if (!function_exists('import_parse_supplier_stock')) {
            require_once __DIR__ . '/import_supplier_lib.php';
        }
        $parsed = import_parse_supplier_stock(trim((string) ($entry['stock_raw'] ?? '')));
        $product['pStock'] = $parsed !== null && $parsed > 0
            ? (string) max(1, (int) round($parsed))
            : '1';

        return;
    }

    if ($stockStatus !== 'zero') {
        $product['pStock'] = trim((string) ($product['pStock'] ?? '1')) !== '' ? (string) $product['pStock'] : '1';

        return;
    }

    $product['__stock_zero_applied'] = $mode;
    $product['pStock'] = match ($mode) {
        'out_of_stock' => '0',
        default => '1',
    };
}
