<?php
declare(strict_types=1);

/**
 * Titluri duale: site (pName) + marketplace (pNameMarketplace).
 * Același produs, două versiuni de titlu — nu se dublează rândul în DB.
 */

if (!function_exists('besoiu_ensure_name_marketplace_columns')) {
    function besoiu_ensure_name_marketplace_columns(PDO $pdo): void
    {
        if (function_exists('import_ensure_name_marketplace_columns')) {
            import_ensure_name_marketplace_columns($pdo);

            return;
        }

        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        foreach (['import_produse', 'produse'] as $table) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
                     LIMIT 1'
                );
                $stmt->execute([$table, 'pNameMarketplace']);
                if ($stmt->fetchColumn()) {
                    continue;
                }
                $pdo->exec(
                    "ALTER TABLE `{$table}`
                     ADD COLUMN `pNameMarketplace` VARCHAR(500) NULL DEFAULT NULL
                     AFTER `pName`"
                );
            } catch (Throwable $e) {
                error_log('[besoiu_ensure_name_marketplace_columns] ' . $table . ': ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('besoiu_apply_dual_product_titles')) {
    /**
     * @param array<string, mixed> $row
     */
    function besoiu_apply_dual_product_titles(array &$row, string $website, string $marketplace = ''): void
    {
        $website = trim($website);
        $marketplace = trim($marketplace);
        if ($marketplace === '') {
            $marketplace = $website;
        }

        $row['pName'] = $website;
        $row['pNameMarketplace'] = $marketplace;

        $raw = $row['raw_json'] ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($raw)) {
            $raw = [];
        }

        $formation = is_array($raw['product_formation'] ?? null) ? $raw['product_formation'] : [];
        $formation['title_website'] = $website;
        $formation['title_pieseauto'] = $marketplace;
        if (!isset($formation['title_oem']) || trim((string) $formation['title_oem']) === '') {
            $formation['title_oem'] = $website;
        }
        $formation['applied_at'] = date('c');
        $formation['applied'] = true;
        $raw['product_formation'] = $formation;
        $row['raw_json'] = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('besoiu_resolve_website_product_title')) {
    /**
     * @param array<string, mixed> $product
     */
    function besoiu_resolve_website_product_title(array $product): string
    {
        return trim((string) ($product['pName'] ?? ''));
    }
}

if (!function_exists('besoiu_resolve_marketplace_product_title')) {
    /**
     * @param array<string, mixed> $product
     */
    function besoiu_resolve_marketplace_product_title(array $product): string
    {
        $direct = trim((string) ($product['pNameMarketplace'] ?? ''));
        if ($direct !== '') {
            return $direct;
        }

        $raw = $product['raw_json'] ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($raw)) {
            $raw = [];
        }

        $formation = is_array($raw['product_formation'] ?? null) ? $raw['product_formation'] : [];
        $stored = trim((string) ($formation['title_pieseauto'] ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        if (class_exists(\Besoiu\Services\ProductCardFormationService::class)) {
            try {
                return \Besoiu\Services\ProductCardFormationService::resolveMarketplaceTitleFromRow($product);
            } catch (\Throwable $e) {
                // fallback below
            }
        }

        return trim((string) ($product['pName'] ?? ''));
    }
}
