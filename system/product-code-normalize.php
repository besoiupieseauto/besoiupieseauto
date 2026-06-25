<?php
declare(strict_types=1);

/**
 * Algoritm canonic de normalizare cod produs (furnizori ↔ BD).
 * Elimină spații, cratime, punctuație și caractere speciale; păstrează A-Z0-9 uppercase.
 */
function besoiu_normalize_product_code(string $code): string
{
    $code = strtoupper(trim($code));
    $code = preg_replace('/[\s\-\/_.]/', '', $code) ?? $code;

    return preg_replace('/[^A-Z0-9]/', '', $code) ?? '';
}

/** Expresie SQL pentru compararea codurilor normalizate (fără caractere speciale comune). */
function besoiu_sql_normalized_pcode_expr(string $column = 'pCode'): string
{
    $column = preg_replace('/[^a-zA-Z0-9_.`]/', '', $column) ?: 'pCode';

    return "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM({$column}), ' ', ''), '-', ''), '.', ''), '/', ''), '_', ''))";
}

/** @return array<int, string> Variante unice (normalizate + original curățat) pentru căutare. */
function besoiu_product_code_search_variants(string $code): array
{
    $raw = trim($code);
    $norm = besoiu_normalize_product_code($raw);
    $variants = [];

    foreach ([$norm, strtoupper($raw)] as $variant) {
        if ($variant !== '' && !in_array($variant, $variants, true)) {
            $variants[] = $variant;
        }
    }

    return $variants;
}
