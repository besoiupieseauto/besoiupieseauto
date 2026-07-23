<?php
declare(strict_types=1);

/**
 * tm_097 — Export CSV unificat din coada import (produse validate post-validare).
 */

require_once __DIR__ . '/import-queue-critical.php';

/** @return array<int, string> */
function import_queue_export_csv_headers(): array
{
    return [
        'import_id',
        'pCode',
        'pName',
        'pBrand',
        'pMarca',
        'pModel',
        'pMotorizare',
        'pPrice',
        'pBasePrice',
        'pStock',
        'pCategory',
        'pSubcategory',
        'pOem',
        'pCompatibilitati',
        'pSupplier',
        'pNote',
        'pNoteWebsite',
        'pNoteMarketplace',
        'pImage',
        'pImages',
        'pImageSource',
        'pShipping',
        'pWarranty',
        'pReturn',
        'pWhatsapp',
        'pCurierLivrare',
        'pMarkupRuleName',
        'validated_at',
    ];
}

function import_queue_export_first_image(array $row): string
{
    $url = besoiu_import_row_image_url($row);
    if ($url !== '') {
        return $url;
    }

    $decoded = json_decode((string) ($row['pImages'] ?? '[]'), true);
    if (!is_array($decoded)) {
        return '';
    }

    foreach ($decoded as $candidate) {
        $candidateUrl = trim((string) $candidate);
        if ($candidateUrl !== '') {
            return $candidateUrl;
        }
    }

    return '';
}

/** @return array<int, string> */
function import_queue_export_all_images(array $row): array
{
    $urls = [];
    $decoded = json_decode((string) ($row['pImages'] ?? '[]'), true);
    if (is_array($decoded)) {
        foreach ($decoded as $candidate) {
            $candidateUrl = trim((string) $candidate);
            if ($candidateUrl !== '') {
                $urls[] = $candidateUrl;
            }
        }
    }

    $primary = import_queue_export_first_image($row);
    if ($primary !== '' && !in_array($primary, $urls, true)) {
        $urls[] = $primary;
    }

    return $urls;
}

/** @return array<int, string> */
function import_queue_export_row_values(array $row): array
{
    $images = import_queue_export_all_images($row);
    $note = trim((string) ($row['pNote'] ?? ''));
    if ($note !== '' && str_contains($note, '<')) {
        $note = trim(preg_replace('/\s+/u', ' ', strip_tags($note)) ?? '');
    }

    return [
        (string) (int) ($row['id'] ?? 0),
        (string) ($row['pCode'] ?? ''),
        (string) ($row['pName'] ?? ''),
        (string) ($row['pBrand'] ?? ''),
        (string) ($row['pMarca'] ?? ''),
        (string) ($row['pModel'] ?? ''),
        (string) ($row['pMotorizare'] ?? ''),
        (string) ($row['pPrice'] ?? ''),
        (string) ($row['pBasePrice'] ?? ''),
        (string) ($row['pStock'] ?? '0'),
        (string) ($row['pCategory'] ?? ''),
        (string) ($row['pSubcategory'] ?? ''),
        (string) ($row['pOem'] ?? ''),
        (string) ($row['pCompatibilitati'] ?? ''),
        (string) ($row['pSupplier'] ?? ''),
        $note,
        (string) ($row['pNoteWebsite'] ?? ''),
        (string) ($row['pNoteMarketplace'] ?? ''),
        import_queue_export_first_image($row),
        implode('|', $images),
        (string) ($row['pImageSource'] ?? ''),
        (string) ($row['pShipping'] ?? ''),
        (string) ($row['pWarranty'] ?? ''),
        (string) ($row['pReturn'] ?? ''),
        (string) ($row['pWhatsapp'] ?? ''),
        (string) ($row['pCurierLivrare'] ?? ''),
        (string) ($row['pMarkupRuleName'] ?? ''),
        date('Y-m-d H:i:s'),
    ];
}

/**
 * @param array<int, int> $ids
 * @return array<int, array<string, mixed>>
 */
function import_queue_export_fetch_validated_rows(PDO $pdo, string $supplier = '', array $ids = []): array
{
    $sql = 'SELECT * FROM import_produse WHERE status = ?';
    $params = ['pending'];

    if ($supplier !== '') {
        $sql .= ' AND pSupplier = ?';
        $params[] = $supplier;
    }

    $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
    if ($ids !== []) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql .= " AND id IN ({$placeholders})";
        foreach ($ids as $id) {
            $params[] = $id;
        }
    }

    $sql .= ' ORDER BY id ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $validated = [];
    foreach ($rows as $row) {
        if (!is_array($row) || besoiu_import_row_has_critical_gaps($row)) {
            continue;
        }
        $validated[] = $row;
    }

    return $validated;
}

/** @param array<int, array<string, mixed>> $rows */
function import_queue_export_csv_content(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    if ($handle === false) {
        return '';
    }

    fputcsv($handle, import_queue_export_csv_headers(), ';');

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        fputcsv($handle, import_queue_export_row_values($row), ';');
    }

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    return is_string($csv) ? $csv : '';
}

function import_queue_export_filename(string $supplier = ''): string
{
    $suffix = $supplier !== '' ? '_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', strtoupper($supplier)) : '';
    return 'import_queue_validated' . $suffix . '_' . date('Y-m-d_His') . '.csv';
}

/**
 * Format CSV PieseAuto / Autopro (Excel marketplace).
 * Coloane fixe: ID; titlu; categorie; descriere; monedă; preț; cantitate
 * Titlu = canal marketplace din Formare carte (nu titlul website).
 * Descriere = Base / pNote marketplace (HTML păstrat).
 * BaseLinker folosește alt flux (API) — nu trece pe aici.
 *
 * @return array<int, string>
 */
function import_queue_export_autopro_csv_headers(): array
{
    return [
        'ID',
        'titlu',
        'categorie',
        'descriere',
        'monedă',
        'preț',
        'cantitate',
    ];
}

function import_queue_export_autopro_plain_text(string $value): string
{
    $text = trim($value);
    if ($text !== '' && str_contains($text, '<')) {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
    }

    return $text;
}

/** Păstrează HTML Base pentru upload marketplace; normalizează doar spațiile excesive. */
function import_queue_export_autopro_description_html(string $value): string
{
    $html = trim($value);
    if ($html === '') {
        return '';
    }
    // Evită break-uri Excel pe CR; păstrează markup-ul.
    $html = str_replace(["\r\n", "\r"], "\n", $html);

    return $html;
}

function import_queue_export_autopro_category(array $row): string
{
    $category = trim((string) ($row['pCategory'] ?? ''));
    $subcategory = trim((string) ($row['pSubcategory'] ?? ''));

    if ($category === '' || $category === '—' || strcasecmp($category, 'n/a') === 0) {
        return $subcategory;
    }
    if ($subcategory === '' || $subcategory === '—' || strcasecmp($subcategory, 'n/a') === 0) {
        return $category;
    }

    // Model PieseAuto Autopro: „Categorie -> Subcategorie” (nu „>”).
    return $category . ' -> ' . $subcategory;
}

function import_queue_export_autopro_description(array $row): string
{
    // Marketplace: preferă nota de canal, apoi descrierea Base din coadă / raw_json.
    $candidates = [
        (string) ($row['pNoteMarketplace'] ?? ''),
        (string) ($row['pNote'] ?? ''),
        (string) ($row['pNoteWebsite'] ?? ''),
    ];

    $raw = $row['raw_json'] ?? null;
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (is_array($raw)) {
        $summary = is_array($raw['product_summary'] ?? null) ? $raw['product_summary'] : [];
        $candidates[] = (string) ($summary['description'] ?? '');
        $candidates[] = (string) ($raw['description'] ?? '');
    }

    foreach ($candidates as $candidate) {
        $html = import_queue_export_autopro_description_html((string) $candidate);
        if ($html !== '') {
            return $html;
        }
    }

    $title = import_queue_export_autopro_marketplace_title($row);
    if ($title !== '') {
        return $title;
    }

    return import_queue_export_autopro_plain_text((string) ($row['pName'] ?? ''));
}

function import_queue_export_autopro_id(array $row): string
{
    $code = trim((string) ($row['pCode'] ?? ''));
    if ($code !== '') {
        return $code;
    }

    return (string) (int) ($row['id'] ?? 0);
}

function import_queue_export_autopro_marketplace_title(array $row): string
{
    $title = '';
    if (class_exists(\Besoiu\Services\ProductCardFormationService::class)) {
        try {
            $dual = \Besoiu\Services\ProductCardFormationService::resolveDualTitlesFromRow($row);
            $title = rtrim(trim((string) ($dual['marketplace'] ?? '')), "|\t ");
        } catch (Throwable) {
            $title = '';
        }
    }
    if ($title === '' && function_exists('besoiu_resolve_marketplace_product_title')) {
        $title = rtrim(trim(besoiu_resolve_marketplace_product_title($row)), "|\t ");
    }
    if ($title === '') {
        $title = rtrim(trim((string) ($row['pNameMarketplace'] ?? '')), "|\t ");
    }
    if ($title === '') {
        $title = rtrim(trim((string) ($row['pName'] ?? '')), "|\t ");
    }

    return $title;
}

/** @return array<int, string> */
function import_queue_export_autopro_row_values(array $row): array
{
    $priceRaw = trim((string) ($row['pPrice'] ?? ''));

    return [
        import_queue_export_autopro_id($row),
        import_queue_export_autopro_marketplace_title($row),
        import_queue_export_autopro_category($row),
        import_queue_export_autopro_description($row),
        'RON',
        $priceRaw === '' ? '0' : $priceRaw,
        (string) max(0, (int) ($row['pStock'] ?? 0)),
    ];
}

/** @param array<int, array<string, mixed>> $rows */
function import_queue_export_autopro_csv_content(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    if ($handle === false) {
        return '';
    }

    fputcsv($handle, import_queue_export_autopro_csv_headers(), ';');

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        fputcsv($handle, import_queue_export_autopro_row_values($row), ';');
    }

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    return is_string($csv) ? $csv : '';
}

function import_queue_export_autopro_filename(string $supplier = ''): string
{
    $suffix = $supplier !== '' ? '_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', strtoupper($supplier)) : '';
    return 'pieseauto_marketplace' . $suffix . '_' . date('Y-m-d_His') . '.csv';
}
