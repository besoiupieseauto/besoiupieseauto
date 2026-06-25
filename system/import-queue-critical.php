<?php
declare(strict_types=1);

/**
 * tm_099 — date critice lipsă în coada import (categorie, brand, preț, imagine).
 */

require_once __DIR__ . '/import-image-validate.php';

/** @return array<int, array{code: string, label: string}> */
function besoiu_import_row_critical_flags(array $row): array
{
    $flags = [];

    $category = trim((string) ($row['pCategory'] ?? ''));
    if ($category === '' || $category === '—' || strcasecmp($category, 'n/a') === 0) {
        $flags[] = ['code' => 'missing_category', 'label' => 'Fără categorie'];
    }

    $brand = trim((string) ($row['pBrand'] ?? ''));
    if ($brand === '' || $brand === '—' || strcasecmp($brand, 'n/a') === 0) {
        $flags[] = ['code' => 'missing_brand', 'label' => 'Fără brand'];
    }

    $priceRaw = trim((string) ($row['pPrice'] ?? ''));
    $price = $priceRaw === '' ? 0.0 : (float) $priceRaw;
    if ($price <= 0.0) {
        $flags[] = ['code' => 'zero_price', 'label' => 'Preț 0'];
    }

    if (!besoiu_import_row_has_trusted_image($row)) {
        $flags[] = ['code' => 'missing_image', 'label' => 'Fără imagine'];
    }

    return $flags;
}

function besoiu_import_row_has_critical_gaps(array $row): bool
{
    return besoiu_import_row_critical_flags($row) !== [];
}

/** Produsele cu date critice lipsă nu pot fi publicate automat (cron / publică toate). */
function besoiu_import_row_blocks_auto_publish(array $row): bool
{
    return besoiu_import_row_has_critical_gaps($row);
}

/** @param array<int, array<string, mixed>> $rows
 *  @return array{publishable: array<int, array<string, mixed>>, blocked: int}
 */
function besoiu_import_filter_auto_publishable_rows(array $rows): array
{
    $publishable = [];
    $blocked = 0;

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (besoiu_import_row_blocks_auto_publish($row)) {
            ++$blocked;
            continue;
        }
        $publishable[] = $row;
    }

    return ['publishable' => $publishable, 'blocked' => $blocked];
}
