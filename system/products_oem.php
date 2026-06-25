<?php

declare(strict_types=1);



require_once __DIR__ . '/product-code-normalize.php';



/**

 * Cross-reference OEM pentru produse (echivalențe cod).

 */



function products_oem_table_exists(PDO $pdo): bool

{

    static $cache = null;

    if ($cache !== null) {

        return $cache;

    }



    $stmt = $pdo->prepare(

        'SELECT COUNT(*) FROM information_schema.TABLES

         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'

    );

    $stmt->execute(['products_oem']);

    $cache = (int) $stmt->fetchColumn() > 0;



    return $cache;

}



function products_oem_max_code_length(): int

{

    return 120;

}



function products_oem_normalize(string $code): string

{

    $norm = besoiu_normalize_product_code($code);

    $max = products_oem_max_code_length();



    return strlen($norm) > $max ? substr($norm, 0, $max) : $norm;

}



function products_oem_fit_display_code(string $code): string

{

    $code = trim($code);

    if ($code === '') {

        return '';

    }



    $max = products_oem_max_code_length();

    if (strlen($code) <= $max) {

        return $code;

    }



    if (preg_match('/^([^:]+):\s*(.+)$/u', $code, $matches)) {

        $brand = trim($matches[1]);

        $codePart = trim($matches[2]);

        $prefix = $brand . ' : ';

        $budget = $max - strlen($prefix);

        if ($budget >= 4) {

            return $prefix . substr($codePart, 0, $budget);

        }

    }



    return substr($code, 0, $max);

}



/** @return array<int, string> */

function products_oem_split_text(string $text): array

{

    $text = trim(str_replace(["\r\n", "\r", "\n", ';', '|'], ',', $text));

    if ($text === '') {

        return [];

    }



    $parts = [];

    foreach (preg_split('/\s*,\s*/', $text) ?: [] as $part) {

        $part = trim($part);

        if ($part !== '') {

            $parts[] = $part;

        }

    }



    return $parts;

}



/** @return array<int, string> */

function products_oem_codes_from_text(string $value): array

{

    $value = trim($value);

    if ($value === '') {

        return [];

    }



    if (!preg_match_all('/[A-Z0-9][A-Z0-9.\-\/]{2,}/i', $value, $matches)) {

        return products_oem_split_text($value);

    }



    $unique = [];

    foreach ($matches[0] as $code) {

        $code = trim((string) $code);

        $norm = products_oem_normalize($code);

        if ($norm === '' || strlen($norm) < 3 || isset($unique[$norm])) {

            continue;

        }

        $unique[$norm] = products_oem_fit_display_code($code);

    }



    return array_values($unique);

}



/** @param array<string, mixed> $row */

function products_oem_codes_from_raw_row(array $row): array

{

    $codes = [];

    $keys = [

        'art cross', 'art code 2', 'art code 1', 'art_cross', 'art_code_1', 'art_code_2',

        'ART_CROSS', 'ART_CODE_1', 'ART_CODE_2', 'coduri echivalente',

        'oem', 'oe', 'oem number', 'references', 'reference',

    ];



    foreach ($keys as $key) {

        if (!array_key_exists($key, $row)) {

            continue;

        }

        foreach (products_oem_codes_from_text((string) $row[$key]) as $code) {

            $codes[] = $code;

        }

    }



    return $codes;

}



/** @param array<string, mixed> $raw */

function products_oem_codes_from_raw_json(array $raw): array

{

    $codes = [];



    $rows = $raw['rows'] ?? [];

    if (is_array($rows)) {

        foreach ($rows as $row) {

            if (!is_array($row)) {

                continue;

            }

            foreach (products_oem_codes_from_raw_row($row) as $code) {

                $codes[] = $code;

            }

        }

    }



    $summary = $raw['product_summary'] ?? [];

    if (is_array($summary)) {

        $block = $summary['codes'] ?? [];

        if (is_array($block)) {

            foreach (['coduri_oem', 'coduri_alternative', 'toate_codurile', 'cod_principal'] as $key) {

                $value = $block[$key] ?? null;

                if (is_string($value)) {

                    foreach (products_oem_codes_from_text($value) as $code) {

                        $codes[] = $code;

                    }

                    continue;

                }

                if (!is_array($value)) {

                    continue;

                }

                foreach ($value as $item) {

                    if (!is_string($item)) {

                        continue;

                    }

                    foreach (products_oem_codes_from_text($item) as $code) {

                        $codes[] = $code;

                    }

                }

            }

        }

    }



    foreach (['tecdoc_file', 'tecdoc_api'] as $section) {

        $meta = $raw[$section] ?? null;

        if (!is_array($meta)) {

            continue;

        }

        foreach (['art_code_1', 'query_code', 'matched_code'] as $key) {

            $value = trim((string) ($meta[$key] ?? ''));

            if ($value !== '') {

                foreach (products_oem_codes_from_text($value) as $code) {

                    $codes[] = $code;

                }

            }

        }

    }



    return $codes;

}



/** @return array<int, string> */

function products_oem_extract_codes(array $product): array

{

    $codes = [];

    $seen = [];



    $add = static function (string $code) use (&$codes, &$seen): void {

        $code = products_oem_fit_display_code($code);

        if ($code === '') {

            return;

        }

        $norm = products_oem_normalize($code);

        if ($norm === '' || strlen($norm) < 3 || isset($seen[$norm])) {

            return;

        }

        $seen[$norm] = true;

        $codes[] = $code;

    };



    foreach (products_oem_split_text((string) ($product['pOem'] ?? '')) as $part) {

        if (preg_match('/^([^:]+):\s*(.+)$/u', $part, $matches)) {

            $add(trim($matches[2]));

            continue;

        }

        foreach (products_oem_codes_from_text($part) as $code) {

            $add($code);

        }

    }



    $primary = trim((string) ($product['pCode'] ?? ''));

    if ($primary !== '') {

        $add($primary);

    }



    $raw = json_decode((string) ($product['raw_json'] ?? '{}'), true);

    if (is_array($raw)) {

        foreach (products_oem_codes_from_raw_json($raw) as $code) {

            $add($code);

        }

    }



    return $codes;

}



function products_oem_delete_for_product(PDO $pdo, int $productId): void

{

    if ($productId <= 0 || !products_oem_table_exists($pdo)) {

        return;

    }



    $stmt = $pdo->prepare('DELETE FROM products_oem WHERE product_id = ?');

    $stmt->execute([$productId]);

}



function products_oem_sync_product(PDO $pdo, int $productId, array $product, string $source = 'import'): int

{

    if ($productId <= 0 || !products_oem_table_exists($pdo)) {

        return 0;

    }



    $codes = products_oem_extract_codes($product);

    products_oem_delete_for_product($pdo, $productId);



    if ($codes === []) {

        return 0;

    }



    $brand = trim((string) ($product['pBrand'] ?? ''));

    $primaryNorm = products_oem_normalize((string) ($product['pCode'] ?? ''));

    $insert = $pdo->prepare(

        'INSERT INTO products_oem (product_id, oem_code, oem_norm, brand, is_primary, source)

         VALUES (?, ?, ?, ?, ?, ?)'

    );



    $count = 0;

    foreach ($codes as $code) {

        $display = products_oem_fit_display_code($code);

        $norm = products_oem_normalize($display);

        if ($norm === '' || strlen($norm) < 3) {

            continue;

        }

        $insert->execute([

            $productId,

            $display,

            $norm,

            $brand !== '' ? $brand : null,

            $primaryNorm !== '' && $norm === $primaryNorm ? 1 : 0,

            $source,

        ]);

        $count++;

    }



    return $count;

}



/** @return array<int, int> */

function products_oem_find_product_ids(PDO $pdo, string $query, int $limit = 200): array

{

    if (!products_oem_table_exists($pdo)) {

        return [];

    }



    $norm = products_oem_normalize($query);

    if ($norm === '') {

        return [];

    }



    $stmt = $pdo->prepare(

        'SELECT DISTINCT product_id

         FROM products_oem

         WHERE oem_norm = ? OR oem_norm LIKE ? OR oem_code LIKE ?

         LIMIT ' . max(1, min(500, $limit))

    );

    $like = '%' . $norm . '%';

    $stmt->execute([$norm, $like, '%' . trim($query) . '%']);



    return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'product_id'));

}



/** @return array{processed:int,synced:int,codes:int} */

function products_oem_backfill_all(PDO $pdo, int $batchSize = 500, int $offset = 0): array

{

    if (!products_oem_table_exists($pdo)) {

        return ['processed' => 0, 'synced' => 0, 'codes' => 0];

    }



    $batchSize = max(1, min(2000, $batchSize));

    $offset = max(0, $offset);



    $stmt = $pdo->prepare(

        'SELECT id, pCode, pBrand, pOem, raw_json

         FROM produse

         ORDER BY id ASC

         LIMIT ' . $batchSize . ' OFFSET ' . $offset

    );

    $stmt->execute();



    $processed = 0;

    $synced = 0;

    $codes = 0;



    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

        $processed++;

        try {

            $count = products_oem_sync_product($pdo, (int) $row['id'], $row, 'backfill');

        } catch (Throwable $exception) {

            error_log('[products_oem_backfill] product_id=' . (int) $row['id'] . ' ' . $exception->getMessage());

            continue;

        }

        if ($count > 0) {

            $synced++;

            $codes += $count;

        }

    }



    return ['processed' => $processed, 'synced' => $synced, 'codes' => $codes];

}



/** @return array{total_products:int,total_codes:int} */

function products_oem_stats(PDO $pdo): array

{

    if (!products_oem_table_exists($pdo)) {

        return ['total_products' => 0, 'total_codes' => 0];

    }



    $totalCodes = (int) $pdo->query('SELECT COUNT(*) FROM products_oem')->fetchColumn();

    $totalProducts = (int) $pdo->query('SELECT COUNT(DISTINCT product_id) FROM products_oem')->fetchColumn();



    return [

        'total_products' => $totalProducts,

        'total_codes' => $totalCodes,

    ];

}


