<?php
declare(strict_types=1);

require_once __DIR__ . '/note-html.php';

use Config\Database;
use Besoiu\Services\Products\ProduseService;

if (!function_exists('besoiu_catalog_h')) {
    function besoiu_catalog_h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('besoiu_catalog_price')) {
    function besoiu_catalog_price($value): float
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }

        // Keep digits / separators only (strip "RON", "lei", spaces…).
        $normalized = preg_replace('/[^0-9.,]/', '', $raw) ?? '';
        if ($normalized === '') {
            return 0.0;
        }

        $hasComma = str_contains($normalized, ',');
        $hasDot = str_contains($normalized, '.');
        if ($hasComma && $hasDot) {
            // EU "1.234,56" vs US "1,234.56" — last separator is the decimal.
            if (strrpos($normalized, ',') > strrpos($normalized, '.')) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif ($hasComma) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }
}

if (!function_exists('besoiu_product_card_offer_price')) {
    /**
     * Storefront offer price: prefer final pPrice, fall back to purchase/base when final is empty.
     *
     * @param array<string, mixed> $product
     */
    function besoiu_product_card_offer_price(array $product): float
    {
        foreach (['pPrice', 'price', 'price_numeric', 'pBasePrice'] as $key) {
            if (!isset($product[$key]) || trim((string) $product[$key]) === '') {
                continue;
            }
            $price = besoiu_catalog_price($product[$key]);
            if ($price > 0) {
                return $price;
            }
        }

        return 0.0;
    }
}

if (!function_exists('besoiu_product_card_compare_price')) {
    /**
     * Optional "was" price for sale display (only when higher than offer).
     *
     * @param array<string, mixed> $product
     */
    function besoiu_product_card_compare_price(array $product, float $offerPrice): float
    {
        if ($offerPrice <= 0) {
            return 0.0;
        }

        foreach (['price_old', 'old_price', 'pPriceOld', 'pRetailPrice', 'compare_at_price'] as $key) {
            if (!isset($product[$key]) || trim((string) $product[$key]) === '') {
                continue;
            }
            $old = besoiu_catalog_price($product[$key]);
            if ($old > $offerPrice) {
                return $old;
            }
        }

        return 0.0;
    }
}

if (!function_exists('besoiu_product_card_price_html')) {
    /**
     * @param array<string, mixed> $product
     */
    function besoiu_product_card_price_html(array $product): string
    {
        $offer = besoiu_product_card_offer_price($product);
        $compare = besoiu_product_card_compare_price($product, $offer);
        $offerLabel = besoiu_store_price_label($offer);

        if ($compare > $offer) {
            return '<div class="_product-price _product-price--sale">'
                . '<span class="_product-price-old">' . besoiu_catalog_h(besoiu_store_price_label($compare)) . '</span>'
                . '<span class="_product-price-new">' . besoiu_catalog_h($offerLabel) . '</span>'
                . '</div>';
        }

        return '<div class="_product-price">' . besoiu_catalog_h($offerLabel) . '</div>';
    }
}

if (!function_exists('besoiu_store_price_round_config')) {
    /** @return array{mode:string,value:float} */
    function besoiu_store_price_round_config(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $cached = ['mode' => 'none', 'value' => 1.0];

        try {
            require_once BESOIU_BACKEND . '/vendor/autoload.php';

            $dotenv = Dotenv\Dotenv::createImmutable(BESOIU_CONFIG);
            $dotenv->safeLoad();

            $config = require BESOIU_CONFIG . '/config.php';
            Database::getInstance(
                $config['db_host'],
                $config['db_name'],
                $config['db_user'],
                $config['db_pass']
            );

            $model = new \Besoiu\Core\Supplier\SupplierPriceLogicStore();
            $stored = $model->loadConfig() ?? [];
            $mode = mb_strtolower(trim((string) ($stored['global_price_round_mode'] ?? 'none')));
            if (!in_array($mode, ['next_integer', 'round_to'], true)) {
                $mode = 'none';
            }

            $value = (float) ($stored['global_price_round_value'] ?? 1);
            if ($value <= 0) {
                $value = 1.0;
            }

            $cached = ['mode' => $mode, 'value' => $value];
        } catch (Throwable) {
            // Păstrează valorile implicite dacă configurația nu poate fi citită.
        }

        return $cached;
    }
}

if (!function_exists('besoiu_store_price_uses_integer_display')) {
    function besoiu_store_price_uses_integer_display(): bool
    {
        return besoiu_store_price_round_config()['mode'] !== 'none';
    }
}

if (!function_exists('besoiu_store_price_label')) {
    function besoiu_store_price_label(float $price): string
    {
        if ($price <= 0) {
            return 'La cerere';
        }

        if (besoiu_store_price_uses_integer_display()) {
            return number_format($price, 0, '.', '') . ' RON';
        }

        $formatted = number_format($price, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted . ' RON';
    }
}

if (!function_exists('besoiu_catalog_images')) {
    function besoiu_catalog_images($value): array
    {
        $decoded = json_decode((string) $value, true);
        if (is_array($decoded)) {
            return array_values(array_filter($decoded));
        }

        return $value ? [(string) $value] : [];
    }
}

if (!function_exists('besoiu_catalog_first_image')) {
    function besoiu_catalog_first_image(array $product): string
    {
        require_once __DIR__ . '/besoiu-image.php';
        $images = besoiu_catalog_images($product['pImages'] ?? '');

        return besoiu_resolve_product_image($images[0] ?? besoiu_default_product_image());
    }
}

if (!function_exists('besoiu_catalog_category')) {
    function besoiu_catalog_category(array $product): string
    {
        foreach (['pCategory', 'pCar', 'pBrand', 'pState'] as $key) {
            $value = trim((string) ($product[$key] ?? ''));
            if ($value !== '' && besoiu_catalog_is_valid_category($value)) {
                return $value;
            }
        }

        $name = trim((string) ($product['pName'] ?? $product['name'] ?? ''));
        if ($name !== '') {
            $parts = preg_split('/\s+/', $name) ?: [];
            return implode(' ', array_slice($parts, 0, 2)) ?: 'Piese auto';
        }

        return 'Piese auto';
    }
}

if (!function_exists('besoiu_catalog_is_valid_category')) {
    function besoiu_catalog_is_valid_category(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 60) {
            return false;
        }

        return !preg_match('~(https?://|www\.|/|\\\\|\.(jpg|jpeg|png|webp|gif)(\?|$))~i', $value);
    }
}

if (!function_exists('besoiu_catalog_css_url')) {
    function besoiu_catalog_css_url(string $url): string
    {
        return str_replace(["\\", "'", ')'], ['/', '%27', '%29'], $url);
    }
}

if (!function_exists('besoiu_product_badge_config')) {
    function besoiu_product_badge_config(): array
    {
        static $config = null;
        if ($config === null) {
            $path = BESOIU_CONFIG . '/product-badges.php';
            $config = is_file($path) ? (require $path) : [];
        }

        return is_array($config) ? $config : [];
    }
}

if (!function_exists('besoiu_product_badge_html')) {
    function besoiu_product_badge_html(string $badgeKey): string
    {
        $badgeKey = trim($badgeKey);
        if ($badgeKey === '') {
            return '';
        }

        $badges = besoiu_product_badge_config();
        if (!isset($badges[$badgeKey])) {
            return '';
        }

        $label = (string) ($badges[$badgeKey]['label'] ?? strtoupper($badgeKey));
        return '<div class="_product-card-badge">' .
            '<span class="_product-badge _product-badge--' . besoiu_catalog_h($badgeKey) . '">' . besoiu_catalog_h($label) . '</span>' .
            '</div>';
    }
}

if (!function_exists('besoiu_product_specs_parse_note')) {
    function besoiu_product_specs_parse_note(string $note): array
    {
        $note = trim($note);
        if ($note === '') {
            return [];
        }

        $specs = [];
        $chunks = preg_split('/\s*\|\s*|\s*;\s*/', $note) ?: [$note];

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            if (strpos($chunk, '::') === false && preg_match('/^([^:]+):\s*(.+)$/', $chunk, $matches)) {
                $specs[] = [
                    'label' => trim($matches[1]),
                    'value' => trim($matches[2]),
                ];
                continue;
            }

            $parts = preg_split('/\s*::\s*/', $chunk) ?: [];
            $parts = array_values(array_filter(array_map('trim', $parts), static fn(string $part): bool => $part !== ''));

            if (count($parts) === 2) {
                $specs[] = ['label' => $parts[0], 'value' => $parts[1]];
                continue;
            }

            if (count($parts) > 2) {
                $start = count($parts) % 2 === 1 ? 1 : 0;
                for ($i = $start, $len = count($parts); $i < $len - 1; $i += 2) {
                    $specs[] = ['label' => $parts[$i], 'value' => $parts[$i + 1]];
                }
                continue;
            }

            if (count($parts) === 1) {
                $specs[] = ['label' => 'Detaliu', 'value' => $parts[0]];
            }
        }

        return $specs;
    }
}

if (!function_exists('besoiu_product_specs_from_product')) {
    function besoiu_product_specs_from_product(array $product): array
    {
        $specs = [];
        $seen = [];

        $append = static function (string $label, string $value) use (&$specs, &$seen): void {
            $label = trim($label);
            $value = trim($value);
            if ($label === '' || $value === '') {
                return;
            }

            $key = mb_strtolower($label);
            if (isset($seen[$key])) {
                return;
            }

            $seen[$key] = true;
            $specs[] = ['label' => $label, 'value' => $value];
        };

        $fieldMap = [
            'Brand' => trim((string) ($product['pBrand'] ?? '')),
            'Marcă' => trim((string) ($product['pMarca'] ?? '')),
            'Model' => trim((string) ($product['pModel'] ?? '')),
            'Motorizare' => trim((string) ($product['pMotorizare'] ?? '')),
            'Categorie' => trim((string) ($product['pCategory'] ?? '')),
            'Subcategorie' => trim((string) ($product['pSubcategory'] ?? '')),
            'Compatibilitate' => trim((string) ($product['pCompatibilitati'] ?? $product['pCar'] ?? '')),
        ];

        foreach ($fieldMap as $label => $value) {
            $append($label, $value);
        }

        $rawNote = trim((string) ($product['pNote'] ?? ''));
        if ($rawNote !== '' && !besoiu_note_is_html($rawNote)) {
            foreach (besoiu_product_specs_parse_note($rawNote) as $spec) {
                $append($spec['label'], $spec['value']);
            }
        }

        return $specs;
    }
}

if (!function_exists('besoiu_product_card_specs_preview')) {
    function besoiu_product_card_specs_preview(array $specs, int $limit = 4): array
    {
        $exclude = ['compatibilitate', 'model', 'motorizare', 'descriere'];
        $priority = ['brand', 'categorie', 'subcategorie', 'marca', 'marcă'];
        $priorityItems = [];
        $otherItems = [];

        foreach ($specs as $spec) {
            $label = trim((string) ($spec['label'] ?? ''));
            $value = trim((string) ($spec['value'] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }

            $key = function_exists('mb_strtolower') ? mb_strtolower($label) : strtolower($label);
            if (in_array($key, $exclude, true)) {
                continue;
            }

            $valueLength = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
            if ($valueLength > 42) {
                continue;
            }

            if (in_array($key, $priority, true)) {
                $priorityItems[$key] = ['label' => $label, 'value' => $value];
                continue;
            }

            $otherItems[] = ['label' => $label, 'value' => $value];
        }

        $result = [];
        foreach ($priority as $priorityKey) {
            if (isset($priorityItems[$priorityKey])) {
                $result[] = $priorityItems[$priorityKey];
            }
        }

        foreach ($otherItems as $spec) {
            if (count($result) >= $limit) {
                break;
            }
            $result[] = $spec;
        }

        return array_slice($result, 0, $limit);
    }
}

if (!function_exists('besoiu_product_specs_html')) {
    function besoiu_product_specs_html(array $specs, int $columns = 2, string $extraClass = ''): string
    {
        if ($specs === []) {
            return '';
        }

        $columns = max(1, min(3, $columns));
        $class = '_product-specs-grid _product-specs-grid--' . $columns . 'col';
        if ($extraClass !== '') {
            $class .= ' ' . trim($extraClass);
        }

        $html = '<div class="' . besoiu_catalog_h($class) . '">';
        foreach ($specs as $spec) {
            $html .= '<div class="_product-spec-item">'
                . '<span class="_product-spec-label">' . besoiu_catalog_h($spec['label']) . '</span>'
                . '<span class="_product-spec-value">' . besoiu_catalog_h($spec['value']) . '</span>'
                . '</div>';
        }

        return $html . '</div>';
    }
}

if (!function_exists('besoiu_product_detail_href')) {
    function besoiu_product_detail_href(string $productId): string
    {
        $productId = trim($productId);
        if ($productId === '') {
            return '/catalog';
        }

        return '/produs?id=' . rawurlencode($productId);
    }
}

if (!function_exists('besoiu_product_card_actions_html')) {
    function besoiu_product_card_actions_html(string $productId = '', bool $allowCart = true): string
    {
        $productId = trim($productId);
        $idAttr = $productId !== '' ? ' data-product-id="' . besoiu_catalog_h($productId) . '"' : '';
        $href = besoiu_product_detail_href($productId);
        $cartAllowed = $allowCart && $productId !== '' && !str_starts_with($productId, 'epiesa_');

        // Real <a href> so Detalii works even if JS click handlers fail to attach.
        $html = '<div class="_product-card-actions">' .
            '<a class="_product-card-btn product_detal" href="' . besoiu_catalog_h($href) . '"' . $idAttr . ' title="Detalii produs">' .
                '<img src="img/icons/22_cutie_produse.svg" alt="" class="_pca-btn-icon" width="18" height="18">' .
                '<span>Detalii</span>' .
            '</a>';

        if ($cartAllowed) {
            $html .=
            '<button class="btn_addtoccard _pca-icon" type="button" title="Adaugă în coș">' .
                '<img src="img/icons/14_cos_cumparaturi.svg" alt="" class="_pca-btn-icon" width="20" height="20">' .
            '</button>' .
            '<button class="btn_quickbuy _pca-icon" type="button" title="Cumpără cu 1 click">' .
                '<img src="img/icons/26_plata_card.svg" alt="" class="_pca-btn-icon" width="20" height="20">' .
            '</button>';
        } else {
            $html .= '<span class="_product-card-external-hint muted" style="font-size:12px;margin-left:8px;">Disponibil doar prin WhatsApp</span>';
        }

        return $html . '</div>';
    }
}

if (!function_exists('besoiu_product_card_actions_home_html')) {
    /** Acțiuni compacte — homepage (Produse speciale / recomandate). */
    function besoiu_product_card_actions_home_html(string $productId = ''): string
    {
        $productId = trim($productId);
        $idAttr = $productId !== '' ? ' data-product-id="' . besoiu_catalog_h($productId) . '"' : '';
        $href = besoiu_product_detail_href($productId);

        return '<div class="_product-card-actions _product-card-actions--home">' .
            '<a class="_product-card-btn product_detal" href="' . besoiu_catalog_h($href) . '"' . $idAttr . ' title="Detalii produs">' .
                '<img src="img/icons/22_cutie_produse.svg" alt="" class="_pca-btn-icon _pca-btn-icon--on-primary" width="14" height="14">' .
                '<span>Detalii</span>' .
            '</a>' .
            '<button class="btn_addtoccard _pca-icon" type="button" title="Adaugă în coș" aria-label="Adaugă în coș">' .
                '<img src="img/icons/14_cos_cumparaturi.svg" alt="" class="_pca-btn-icon" width="15" height="15">' .
            '</button>' .
            '<button class="btn_quickbuy _pca-icon" type="button" title="Cumpără cu 1 click" aria-label="Cumpără cu 1 click">' .
                '<img src="img/icons/26_plata_card.svg" alt="" class="_pca-btn-icon" width="15" height="15">' .
            '</button>' .
            '</div>';
    }
}

if (!function_exists('besoiu_render_magazin_card')) {
    /** Card voluminos — pagina Magazin (/catalog): detalii complete pentru cumpărător. */
    function besoiu_render_magazin_card(array $product): void
    {
        if (function_exists('besoiu_resolve_website_product_title')) {
            $name = trim(besoiu_resolve_website_product_title($product));
        } else {
            $name = '';
        }
        if ($name === '') {
            $name = trim((string) ($product['pName'] ?? $product['name'] ?? 'Produs fără nume'));
        }
        $price = besoiu_product_card_offer_price($product);
        $category = besoiu_catalog_category($product);
        $image = besoiu_catalog_first_image($product);
        // Storefront detail route expects public randomn_id (hex), not numeric DB id.
        $productId = trim((string) ($product['randomn_id'] ?? ''));
        $code = trim((string) ($product['pCode'] ?? ''));
        $brand = trim((string) ($product['pBrand'] ?? ''));
        $marca = trim((string) ($product['pMarca'] ?? ''));
        $subcategory = trim((string) ($product['pSubcategory'] ?? ''));
        $stock = trim((string) ($product['pStock'] ?? ''));
        $description = trim((string) ($product['pNote'] ?? ''));
        if ($description === '') {
            $description = trim(($brand ? $brand . ' - ' : '') . 'Piesă auto disponibilă în stoc.');
        }
        $isHtmlNote = besoiu_note_is_html($description);
        $specs = besoiu_product_specs_from_product($product);
        if ($specs === [] && $description !== '' && !$isHtmlNote) {
            $specs = [['label' => 'Descriere', 'value' => $description]];
        }
        $plainDescription = $isHtmlNote ? besoiu_note_plain_text($description) : $description;
        $specsSearch = implode(' ', array_map(static fn(array $spec): string => $spec['label'] . ' ' . $spec['value'], $specs));
        if ($plainDescription !== '') {
            $specsSearch = trim($specsSearch . ' ' . $plainDescription);
        }
        $cardSpecs = besoiu_product_card_specs_preview($specs, 4);
        $specsJson = json_encode($specs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $shipping = trim((string) ($product['pShipping'] ?? ''));
        $deliveryTime = stripos($shipping, 'ridicare') !== false ? 8 : 24;
        $badge = trim((string) ($product['pBadge'] ?? ''));
        $stockLabel = $stock !== '' ? $stock : 'La cerere';
        ?>
        <article class="_product-card magazin-card"
                 data-card-type="magazin"
                 data-product-id="<?= besoiu_catalog_h($productId) ?>"
                 data-name="<?= besoiu_catalog_h($name) ?>"
                 data-oem="<?= besoiu_catalog_h($code) ?>"
                 data-vin=""
                 data-category="<?= besoiu_catalog_h($category) ?>"
                 data-subcategory="<?= besoiu_catalog_h($subcategory) ?>"
                 data-marca="<?= besoiu_catalog_h($marca) ?>"
                 data-brand="<?= besoiu_catalog_h($brand) ?>"
                 data-price="<?= besoiu_catalog_h((string) $price) ?>"
                 data-stock="<?= besoiu_catalog_h($stock) ?>"
                 data-image="<?= besoiu_catalog_h($image) ?>"
                 data-badge="<?= besoiu_catalog_h($badge) ?>"
                 data-desc="<?= besoiu_catalog_h($specsSearch) ?>"
                 data-specs="<?= besoiu_catalog_h($specsJson ?: '[]') ?>">
            <div class="_product-card-head">
                <h3 class="_product-card-name"><?= besoiu_catalog_h($name) ?></h3>
            </div>
            <div class="_product-card-image _product-card-image--clickable">
                <?= besoiu_product_badge_html($badge) ?>
                <img src="<?= besoiu_catalog_h(besoiu_catalog_css_url($image)) ?>" alt="<?= besoiu_catalog_h($name) ?>" width="200" height="200" loading="lazy" decoding="async">
            </div>
            <?= besoiu_product_specs_html($cardSpecs, 2, '_product-card-specs') ?>
            <?php if ($plainDescription !== '' && $cardSpecs === []): ?>
            <p class="_product-card-desc"><?= besoiu_catalog_h($plainDescription) ?></p>
            <?php elseif ($plainDescription !== '' && $isHtmlNote): ?>
            <p class="_product-card-desc _product-card-desc--fallback" hidden><?= besoiu_catalog_h($plainDescription) ?></p>
            <?php elseif (!$isHtmlNote && $description !== ''): ?>
            <p class="_product-card-desc _product-card-desc--fallback"<?= $cardSpecs !== [] ? ' hidden' : '' ?>><?= besoiu_catalog_h($description) ?></p>
            <?php endif; ?>
            <div class="_product-card-info">
                <div class="_product-oem">OEM: <?= besoiu_catalog_h($code !== '' ? $code : 'N/A') ?></div>
                <div class="_product-stock">Stoc: <?= besoiu_catalog_h($stockLabel) ?></div>
                <div class="_product-time"><?= besoiu_catalog_h((string) $deliveryTime) ?> H</div>
            </div>
            <?= besoiu_product_card_price_html($product) ?>
            <?= besoiu_product_card_actions_html($productId) ?>
        </article>
        <?php
    }
}

if (!function_exists('besoiu_catalog_preload_limit')) {
    function besoiu_catalog_preload_limit(): int
    {
        $env = getenv('BESOIU_CATALOG_PRELOAD_LIMIT');
        if ($env !== false && $env !== '') {
            return max(50, min(2000, (int) $env));
        }

        return 500;
    }
}

if (!function_exists('besoiu_catalog_grid_meta')) {
    /** @return array{total:int,loaded:int,truncated:bool} */
    function besoiu_catalog_grid_meta(): array
    {
        $meta = $GLOBALS['besoiu_catalog_grid_meta'] ?? null;
        if (!is_array($meta)) {
            return ['total' => 0, 'loaded' => 0, 'truncated' => false];
        }

        return [
            'total' => (int) ($meta['total'] ?? 0),
            'loaded' => (int) ($meta['loaded'] ?? 0),
            'truncated' => !empty($meta['truncated']),
        ];
    }
}

if (!function_exists('besoiu_catalog_load_products')) {
    function besoiu_catalog_load_products(): array
    {
        $previousErrorReporting = error_reporting();
        error_reporting($previousErrorReporting & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        try {
            require_once BESOIU_BACKEND . '/vendor/autoload.php';

            $dotenv = Dotenv\Dotenv::createImmutable(BESOIU_CONFIG);
            $dotenv->safeLoad();

            $config = require BESOIU_CONFIG . '/config.php';
            Database::getInstance(
                $config['db_host'],
                $config['db_name'],
                $config['db_user'],
                $config['db_pass']
            );

            $service = new ProduseService();
            $bundle = $service->getCatalogProducts(besoiu_catalog_preload_limit());
            $GLOBALS['besoiu_catalog_grid_meta'] = [
                'total' => (int) ($bundle['total'] ?? 0),
                'loaded' => (int) ($bundle['loaded'] ?? 0),
                'truncated' => !empty($bundle['truncated']),
            ];

            $items = is_array($bundle['items'] ?? null) ? $bundle['items'] : [];
            require_once __DIR__ . '/tecdoc_stock.php';

            return tecdoc_deduplicate_catalog_rows_by_supplier_price($items);
        } catch (Throwable $exception) {
            error_log('[catalog-products] ' . $exception->getMessage());
            $GLOBALS['besoiu_catalog_grid_meta'] = ['total' => 0, 'loaded' => 0, 'truncated' => false];

            return [];
        } finally {
            error_reporting($previousErrorReporting);
        }
    }
}

if (defined('BESOIU_SKIP_PRODUCT_GRID') && BESOIU_SKIP_PRODUCT_GRID) {
    return;
}

$catalogProducts = besoiu_catalog_load_products();
$catalogGridMeta = besoiu_catalog_grid_meta();
$besoiuProductGridMode = $besoiuProductGridMode ?? 'catalog';
$besoiuUsesHomeGrid = in_array($besoiuProductGridMode, ['home', 'magazin'], true);
$besoiuUsesMagazinCard = in_array($besoiuProductGridMode, ['catalog', 'magazin'], true);
$besoiuProductGridClass = $besoiuUsesHomeGrid ? '_product-grid' : 'row';
if ($besoiuProductGridMode === 'magazin') {
    $besoiuProductGridClass .= ' magazin-grid';
}
$gridId = $besoiuUsesHomeGrid ? '_product-grid' : 'product-grid';
$gridDataAttrs = '';
if (!empty($catalogGridMeta['truncated'])) {
    $gridDataAttrs = sprintf(
        ' data-catalog-truncated="1" data-catalog-total="%d" data-catalog-loaded="%d"',
        (int) ($catalogGridMeta['total'] ?? 0),
        (int) ($catalogGridMeta['loaded'] ?? 0)
    );
}
?>

<div class="<?= besoiu_catalog_h($besoiuProductGridClass) ?>" id="<?= besoiu_catalog_h($gridId) ?>"<?= $gridDataAttrs ?>>
    <?php foreach ($catalogProducts as $product): ?>
        <?php if ($besoiuUsesMagazinCard && $besoiuUsesHomeGrid): ?>
            <?php besoiu_render_magazin_card($product); ?>
        <?php elseif ($besoiuUsesMagazinCard): ?>
            <?php
                $name = trim((string) ($product['pName'] ?? $product['name'] ?? 'Produs fără nume'));
                $price = besoiu_product_card_offer_price($product);
                $category = besoiu_catalog_category($product);
                $productId = trim((string) ($product['randomn_id'] ?? ''));
                $code = trim((string) ($product['pCode'] ?? ''));
                $car = trim((string) ($product['pCar'] ?? ''));
                $brand = trim((string) ($product['pBrand'] ?? ''));
                $shipping = trim((string) ($product['pShipping'] ?? ''));
                $deliveryTime = stripos($shipping, 'ridicare') !== false ? 8 : 24;
            ?>
            <div class="col-12 col-sm-4 product-col"
                 data-name="<?= besoiu_catalog_h($name . ' ' . $code . ' ' . $car . ' ' . $brand) ?>"
                 data-price="<?= besoiu_catalog_h(number_format($price, 2, '.', '')) ?>"
                 data-time="<?= besoiu_catalog_h((string) $deliveryTime) ?>"
                 data-category="<?= besoiu_catalog_h($category) ?>"
                 data-product-id="<?= besoiu_catalog_h($productId) ?>">
                <?php besoiu_render_magazin_card($product); ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>