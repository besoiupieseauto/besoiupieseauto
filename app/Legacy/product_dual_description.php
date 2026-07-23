<?php
declare(strict_types=1);

require_once __DIR__ . '/note-html.php';

if (!function_exists('besoiu_apply_product_description')) {
    /**
     * Setează o singură descriere generată — folosită peste tot (site, export, marketplace).
     *
     * @param array<string, mixed> $row
     */
    function besoiu_apply_product_description(array &$row, string $description): void
    {
        $description = trim($description);
        $row['pNote'] = $description;
        $row['pNoteWebsite'] = $description;
        $row['pNoteMarketplace'] = $description;
    }
}

if (!function_exists('besoiu_resolve_product_description')) {
    /**
     * @param array<string, mixed> $product
     */
    function besoiu_resolve_product_description(array $product): string
    {
        $note = trim((string) ($product['pNote'] ?? ''));
        if ($note !== '') {
            return $note;
        }

        foreach (['pNoteWebsite', 'pNoteMarketplace'] as $legacyKey) {
            $legacy = trim((string) ($product[$legacyKey] ?? ''));
            if ($legacy !== '') {
                return $legacy;
            }
        }

        return '';
    }
}

if (!function_exists('besoiu_apply_dual_product_descriptions')) {
    /** @deprecated Folosește besoiu_apply_product_description — păstrat pentru compatibilitate import. */
    function besoiu_apply_dual_product_descriptions(array &$row, string $description, string $website = ''): void
    {
        unset($website);
        besoiu_apply_product_description($row, $description);
    }
}

if (!function_exists('besoiu_resolve_website_product_description')) {
    /** @deprecated Alias — aceeași descriere unică. */
    function besoiu_resolve_website_product_description(array $product): string
    {
        return besoiu_resolve_product_description($product);
    }
}

if (!function_exists('besoiu_resolve_marketplace_product_description')) {
    /** @deprecated Alias — aceeași descriere unică. */
    function besoiu_resolve_marketplace_product_description(array $product): string
    {
        return besoiu_resolve_product_description($product);
    }
}
