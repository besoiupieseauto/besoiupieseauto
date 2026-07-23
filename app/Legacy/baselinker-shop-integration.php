<?php

declare(strict_types=1);

/**
 * tm_110 — Fișier integrare magazin BaseLinker (Shops API protocol).
 * Permite „Import din magazin” — sincronizare continuă fără limită 30MB fișier.
 *
 * @see https://developers.baselinker.com/shops_api/
 */

const BASELINKER_SHOP_INTEGRATION_VERSION = '1.0.0-tm110';
const BASELINKER_SHOP_PRODUCTS_PER_PAGE = 500;

/** @return list<string> */
function baselinker_shop_supported_methods(): array
{
    return [
        'SupportedMethods',
        'FileVersion',
        'ProductsCategories',
        'ProductsList',
        'ProductsData',
        'ProductsPrices',
        'ProductsQuantity',
    ];
}

function baselinker_shop_storage_dir(): string
{
    return dirname(__DIR__) . '/storage/feeds/baselinker';
}

function baselinker_shop_ensure_storage(): void
{
    $dir = baselinker_shop_storage_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

/** @return array<string, mixed> */
function baselinker_shop_default_meta(): array
{
    return [
        'integration_version' => BASELINKER_SHOP_INTEGRATION_VERSION,
        'bl_pass' => '',
        'generated_at' => null,
        'active_products' => 0,
    ];
}

/** @return array<string, mixed> */
function baselinker_shop_load_meta(): array
{
    baselinker_shop_ensure_storage();
    $path = baselinker_shop_storage_dir() . '/shop-integration-meta.json';
    if (!is_readable($path)) {
        return baselinker_shop_default_meta();
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded)
        ? array_merge(baselinker_shop_default_meta(), $decoded)
        : baselinker_shop_default_meta();
}

/** @param array<string, mixed> $meta */
function baselinker_shop_save_meta(array $meta): void
{
    baselinker_shop_ensure_storage();
    $path = baselinker_shop_storage_dir() . '/shop-integration-meta.json';
    file_put_contents(
        $path,
        json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function baselinker_shop_site_base_url(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        return $scheme . '://' . $host;
    }

    return 'http://besoiupieseauto.ro.test';
}

function baselinker_shop_resolve_bl_pass(PDO $pdo): string
{
    $meta = baselinker_shop_load_meta();
    $existing = trim((string) ($meta['bl_pass'] ?? ''));
    if ($existing !== '') {
        return $existing;
    }

    $envPass = trim((string) (getenv('BASELINKER_SHOP_BL_PASS') ?: ($_ENV['BASELINKER_SHOP_BL_PASS'] ?? '')));
    if ($envPass !== '') {
        $meta['bl_pass'] = $envPass;
        baselinker_shop_save_meta($meta);

        return $envPass;
    }

    $newPass = bin2hex(random_bytes(16));
    $meta['bl_pass'] = $newPass;
    $meta['generated_at'] = gmdate('c');
    baselinker_shop_save_meta($meta);

    return $newPass;
}

function baselinker_shop_validate_bl_pass(string $provided, PDO $pdo): bool
{
    $expected = baselinker_shop_resolve_bl_pass($pdo);

    return $expected !== '' && hash_equals($expected, $provided);
}

/** @return array<string, string> */
function baselinker_shop_public_urls(string $blPass): array
{
    $base = rtrim(baselinker_shop_site_base_url(), '/');
    $fileUrl = $base . '/api/baselinker-shop-integration.php';

    return [
        'integration_file' => $fileUrl,
        'integration_file_short' => $base . '/baselinker.php',
        'docs' => 'https://developers.baselinker.com/shops_api/',
        'help_import' => 'https://base.com/en-EN/help/knowledgebase/importing-products-from-a-store-or-wholesaler/',
    ];
}

function baselinker_shop_count_active_products(PDO $pdo): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM produse WHERE status <> :inactive');
    $stmt->execute([':inactive' => '0']);

    return (int) $stmt->fetchColumn();
}

/** @return list<array<string, mixed>> */
function baselinker_shop_fetch_products(PDO $pdo, int $page, int $perPage): array
{
    $offset = max(0, ($page - 1) * $perPage);
    $stmt = $pdo->prepare(
        'SELECT randomn_id, pCode, pName, pPrice, pStock, pBrand, pOem, pCategory, pNoteMarketplace, pNoteWebsite, pImages
         FROM produse
         WHERE status <> :inactive
         ORDER BY randomn_id ASC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':inactive', '0', PDO::PARAM_STR);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
}

/** @param array<string, mixed> $row */
function baselinker_shop_map_list_item(array $row, string $siteBaseUrl): array
{
    $productId = (string) ($row['randomn_id'] ?? '');
    $sku = trim((string) ($row['pCode'] ?? ''));
    if ($sku === '') {
        $sku = $productId;
    }

    return [
        'product_id' => $productId,
        'sku' => $sku,
        'name' => trim((string) ($row['pName'] ?? '')) ?: ('Produs #' . $productId),
        'price' => round((float) ($row['pPrice'] ?? 0), 2),
        'quantity' => max(0, (int) ($row['pStock'] ?? 0)),
        'url' => $siteBaseUrl . '/produs/' . rawurlencode($productId),
    ];
}

/** @param array<string, mixed> $row */
function baselinker_shop_map_detail_item(array $row, string $siteBaseUrl): array
{
    $base = baselinker_shop_map_list_item($row, $siteBaseUrl);
    $description = trim((string) ($row['pNoteMarketplace'] ?? ''));
    if ($description === '') {
        $description = trim((string) ($row['pNoteWebsite'] ?? ''));
    }

    $images = [];
    $rawImages = $row['pImages'] ?? '[]';
    if (is_string($rawImages)) {
        $decoded = json_decode($rawImages, true);
        if (is_array($decoded)) {
            foreach ($decoded as $img) {
                if (!is_string($img) || trim($img) === '') {
                    continue;
                }
                $path = trim($img);
                if (!preg_match('#^https?://#i', $path)) {
                    $path = rtrim($siteBaseUrl, '/') . '/' . ltrim(str_replace('\\', '/', $path), '/');
                }
                $images[] = $path;
            }
        }
    }

    $base['description'] = $description;
    $base['images'] = $images;
    $base['brand'] = trim((string) ($row['pBrand'] ?? ''));
    $base['ean'] = '';
    $base['category_id'] = trim((string) ($row['pCategory'] ?? '')) ?: '0';

    return $base;
}

/** @param array<string, mixed> $post */
function baselinker_shop_dispatch(PDO $pdo, array $post): array
{
    $action = trim((string) ($post['action'] ?? ''));
    $blPass = trim((string) ($post['bl_pass'] ?? ''));

    if ($blPass === '' || !baselinker_shop_validate_bl_pass($blPass, $pdo)) {
        return [
            'error' => true,
            'error_code' => 'invalid_password',
            'error_text' => 'Parola de comunicare (bl_pass) invalidă.',
        ];
    }

    $siteBaseUrl = baselinker_shop_site_base_url();
    $totalProducts = baselinker_shop_count_active_products($pdo);
    $perPage = BASELINKER_SHOP_PRODUCTS_PER_PAGE;
    $totalPages = $totalProducts > 0 ? (int) ceil($totalProducts / $perPage) : 1;
    $page = max(1, (int) ($post['page'] ?? 1));

    return match ($action) {
        'SupportedMethods' => ['methods' => baselinker_shop_supported_methods()],
        'FileVersion' => [
            'platform' => 'Besoiu Piese Auto (custom PHP)',
            'version' => BASELINKER_SHOP_INTEGRATION_VERSION,
        ],
        'ProductsCategories' => [
            'categories' => [
                ['category_id' => '0', 'name' => 'Piese auto', 'parent_id' => ''],
            ],
        ],
        'ProductsList', 'ProductsData' => baselinker_shop_products_response($pdo, $action, $page, $totalPages, $perPage, $siteBaseUrl),
        'ProductsPrices' => baselinker_shop_prices_response($pdo, $page, $totalPages, $perPage),
        'ProductsQuantity' => baselinker_shop_quantity_response($pdo, $page, $totalPages, $perPage),
        default => [
            'error' => true,
            'error_code' => 'unsupported_action',
            'error_text' => 'Acțiune necunoscută: ' . $action,
        ],
    };
}

/** @return array<string, mixed> */
function baselinker_shop_products_response(
    PDO $pdo,
    string $action,
    int $page,
    int $totalPages,
    int $perPage,
    string $siteBaseUrl
): array {
    $rows = baselinker_shop_fetch_products($pdo, $page, $perPage);
    $products = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $products[] = $action === 'ProductsData'
            ? baselinker_shop_map_detail_item($row, $siteBaseUrl)
            : baselinker_shop_map_list_item($row, $siteBaseUrl);
    }

    $response = ['products' => $products];
    if ($totalPages > 1) {
        $response['pages'] = $totalPages;
    }

    return $response;
}

/** @return array<string, mixed> */
function baselinker_shop_prices_response(PDO $pdo, int $page, int $totalPages, int $perPage): array
{
    $rows = baselinker_shop_fetch_products($pdo, $page, $perPage);
    $products = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $productId = (string) ($row['randomn_id'] ?? '');
        $products[] = [
            'product_id' => $productId,
            'price' => round((float) ($row['pPrice'] ?? 0), 2),
        ];
    }

    $response = ['products' => $products];
    if ($totalPages > 1) {
        $response['pages'] = $totalPages;
    }

    return $response;
}

/** @return array<string, mixed> */
function baselinker_shop_quantity_response(PDO $pdo, int $page, int $totalPages, int $perPage): array
{
    $rows = baselinker_shop_fetch_products($pdo, $page, $perPage);
    $products = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $productId = (string) ($row['randomn_id'] ?? '');
        $products[] = [
            'product_id' => $productId,
            'quantity' => max(0, (int) ($row['pStock'] ?? 0)),
        ];
    }

    $response = ['products' => $products];
    if ($totalPages > 1) {
        $response['pages'] = $totalPages;
    }

    return $response;
}

/** @return array<string, mixed> */
function baselinker_shop_info(PDO $pdo): array
{
    $blPass = baselinker_shop_resolve_bl_pass($pdo);
    $meta = baselinker_shop_load_meta();
    $activeProducts = baselinker_shop_count_active_products($pdo);
    $meta['active_products'] = $activeProducts;
    baselinker_shop_save_meta($meta);

    return [
        'status' => 'ready',
        'message' => 'Integrare magazin BaseLinker (Shops API) disponibilă.',
        'bl_pass' => $blPass,
        'urls' => baselinker_shop_public_urls($blPass),
        'meta' => $meta,
        'supported_methods' => baselinker_shop_supported_methods(),
        'products_per_page' => BASELINKER_SHOP_PRODUCTS_PER_PAGE,
        'active_products' => $activeProducts,
    ];
}
