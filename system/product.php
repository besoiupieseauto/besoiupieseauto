<?php
declare(strict_types=1);

require_once __DIR__ . '/note-html.php';

use Config\Database;
use Besoiu\Controllers\Produse\ProduseService;

if (!function_exists('besoiu_catalog_h')) {
    function besoiu_catalog_h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('besoiu_catalog_price')) {
    function besoiu_catalog_price($value): float
    {
        $normalized = preg_replace('/[^0-9.,]/', '', (string) $value);
        $normalized = str_replace(',', '.', $normalized ?? '');
        return is_numeric($normalized) ? (float) $normalized : 0.0;
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
            require_once dirname(__DIR__) . '/admin/vendor/autoload.php';

            $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__) . '/admin');
            $dotenv->safeLoad();

            $config = require dirname(__DIR__) . '/admin/config/config.php';
            Database::getInstance(
                $config['db_host'],
                $config['db_name'],
                $config['db_user'],
                $config['db_pass']
            );

            $model = new \Besoiu\Core\Furnizori\PriceFormationLogicModel();
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
        $images = besoiu_catalog_images($product['pImages'] ?? '');
        return $images[0] ?? 'assets/images/products/1.jpg';
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
            $path = dirname(__DIR__) . '/config/product-badges.php';
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

if (!function_exists('besoiu_product_card_actions_html')) {
    function besoiu_product_card_actions_html(string $productId = '', bool $allowCart = true): string
    {
        $idAttr = $productId !== '' ? ' data-product-id="' . besoiu_catalog_h($productId) . '"' : '';
        $cartAllowed = $allowCart && $productId !== '' && !str_starts_with($productId, 'epiesa_');

        $html = '<div class="_product-card-actions">' .
            '<button class="_product-card-btn product_detal" type="button"' . $idAttr . ' title="Detalii produs">' .
                '<img src="img/icons/22_cutie_produse.svg" alt="" class="_pca-btn-icon" width="18" height="18">' .
                '<span>Detalii</span>' .
            '</button>';

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
        $idAttr = $productId !== '' ? ' data-product-id="' . besoiu_catalog_h($productId) . '"' : '';

        return '<div class="_product-card-actions _product-card-actions--home">' .
            '<button class="_product-card-btn product_detal" type="button"' . $idAttr . ' title="Detalii produs">' .
                '<img src="img/icons/22_cutie_produse.svg" alt="" class="_pca-btn-icon _pca-btn-icon--on-primary" width="14" height="14">' .
                '<span>Detalii</span>' .
            '</button>' .
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
        $name = trim((string) ($product['pName'] ?? $product['name'] ?? 'Produs fără nume'));
        $price = besoiu_catalog_price($product['pPrice'] ?? 0);
        $priceLabel = besoiu_store_price_label($price);
        $category = besoiu_catalog_category($product);
        $image = besoiu_catalog_first_image($product);
        $productId = trim((string) ($product['randomn_id'] ?? $product['id'] ?? ''));
        $code = trim((string) ($product['pCode'] ?? ''));
        $brand = trim((string) ($product['pBrand'] ?? ''));
        $marca = trim((string) ($product['pMarca'] ?? ''));
        $subcategory = trim((string) ($product['pSubcategory'] ?? ''));
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
                 data-image="<?= besoiu_catalog_h($image) ?>"
                 data-badge="<?= besoiu_catalog_h($badge) ?>"
                 data-desc="<?= besoiu_catalog_h($specsSearch) ?>"
                 data-specs="<?= besoiu_catalog_h($specsJson ?: '[]') ?>">
            <?= besoiu_product_badge_html($badge) ?>
            <div class="_product-card-head">
                <h3 class="_product-card-name"><?= besoiu_catalog_h($name) ?></h3>
            </div>
            <div class="_product-card-image _product-card-image--clickable">
                <img src="<?= besoiu_catalog_h(besoiu_catalog_css_url($image)) ?>" alt="<?= besoiu_catalog_h($name) ?>" loading="lazy" decoding="async">
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
                <div class="_product-time"><?= besoiu_catalog_h((string) $deliveryTime) ?> H</div>
            </div>
            <div class="_product-price"><?= besoiu_catalog_h($priceLabel) ?></div>
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
            require_once dirname(__DIR__) . '/admin/vendor/autoload.php';

            $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__) . '/admin');
            $dotenv->safeLoad();

            $config = require dirname(__DIR__) . '/admin/config/config.php';
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
                $price = besoiu_catalog_price($product['pPrice'] ?? 0);
                $category = besoiu_catalog_category($product);
                $productId = trim((string) ($product['randomn_id'] ?? $product['id'] ?? ''));
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