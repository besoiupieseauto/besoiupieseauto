<?php
declare(strict_types=1);

if (!function_exists('produse_list_h')) {
    function produse_list_h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('produse_list_images')) {
    function produse_list_images($value): array
    {
        $decoded = json_decode((string) $value, true);
        if (is_array($decoded)) {
            return array_values(array_filter($decoded));
        }

        return $value ? [(string) $value] : [];
    }
}

if (!function_exists('produse_list_has_real_image')) {
    function produse_list_has_real_image(array $product): bool
    {
        return produse_list_images($product['pImages'] ?? '') !== [];
    }
}

if (!function_exists('produse_list_first_image')) {
    function produse_list_first_image(array $product): string
    {
        $images = produse_list_images($product['pImages'] ?? '');

        return $images[0] ?? '/admin/dist/images/fakers/preview-12.jpg';
    }
}

if (!function_exists('produse_list_price_number')) {
    function produse_list_price_number($value): string
    {
        return preg_replace('/[^0-9.]/', '', str_replace(',', '.', (string) $value)) ?: '0';
    }
}

if (!function_exists('produse_list_base_price')) {
    function produse_list_base_price(array $product): string
    {
        $basePrice = trim((string) ($product['pBasePrice'] ?? ''));
        if ($basePrice !== '') {
            return $basePrice;
        }

        return trim((string) ($product['pPrice'] ?? ''));
    }
}

if (!function_exists('produse_list_stock')) {
    function produse_list_stock(array $product): string
    {
        $stock = trim((string) ($product['pStock'] ?? ''));
        if ($stock === '') {
            $stock = trim((string) ($product['pShipping'] ?? ''));
        }

        return $stock !== '' ? $stock : '0';
    }
}

if (!function_exists('produse_vitrina_product_id')) {
    function produse_vitrina_product_id(array $product): string
    {
        $path = trim((string) ($product['url_path'] ?? ''));
        $url = trim((string) ($product['url'] ?? ''));
        if (($path !== '' || $url !== '') && function_exists('besoiu_scraper_product_id')) {
            return besoiu_scraper_product_id($product);
        }

        $id = trim((string) ($product['randomn_id'] ?? $product['id'] ?? ''));

        return $id !== '' ? $id : '';
    }
}

if (!function_exists('produse_vitrina_db_row_for_preview')) {
    /** @param array<string, mixed> $product */
    function produse_vitrina_db_row_for_preview(array $product): array
    {
        return [
            'randomn_id' => (string) ($product['randomn_id'] ?? ''),
            'id' => (string) ($product['id'] ?? ''),
            'name' => (string) ($product['pName'] ?? ''),
            'pName' => (string) ($product['pName'] ?? ''),
            'price' => (string) ($product['pPrice'] ?? ''),
            'pPrice' => (string) ($product['pPrice'] ?? ''),
            'pImages' => (string) ($product['pImages'] ?? '[]'),
            'image' => produse_list_first_image($product),
        ];
    }
}
