<?php
declare(strict_types=1);

require_once __DIR__ . '/system/page-init.php';
require_once __DIR__ . '/system/site-content.php';
require_once __DIR__ . '/system/besoiu-assets.php';
require_once __DIR__ . '/system/note-html.php';
require_once __DIR__ . '/system/tecdoc_description.php';

use Config\Database;
use Evasystem\Controllers\Produse\ProduseService;

function product_page_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function product_page_price($value): float
{
    $normalized = preg_replace('/[^0-9.,]/', '', (string) $value);
    $normalized = str_replace(',', '.', $normalized ?? '');
    return is_numeric($normalized) ? (float) $normalized : 0.0;
}

function product_page_images($value): array
{
    $decoded = json_decode((string) $value, true);
    if (is_array($decoded)) {
        return array_values(array_filter($decoded));
    }

    return $value ? [(string) $value] : [];
}

function product_page_category(array $product): string
{
    foreach (['pCategory', 'pCar', 'pBrand', 'pState'] as $key) {
        $value = trim((string) ($product[$key] ?? ''));
        if ($value !== '' && !preg_match('~(https?://|www\.|/|\\\\|\.(jpg|jpeg|png|webp|gif)(\?|$))~i', $value)) {
            return $value;
        }
    }

    return 'Piese auto';
}

function product_page_load_product(): ?array
{
    $id = trim((string) ($_GET['id'] ?? ''));
    if (str_starts_with($id, 'epiesa_')) {
        require_once __DIR__ . '/system/scraper-home.php';
        $scraperProduct = besoiu_scraper_find_by_id($id);

        return $scraperProduct ? besoiu_scraper_as_page_product($scraperProduct, $id) : null;
    }

    $previousErrorReporting = error_reporting();
    error_reporting($previousErrorReporting & ~E_DEPRECATED & ~E_USER_DEPRECATED);

    try {
        require_once __DIR__ . '/admin/vendor/autoload.php';

        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/admin');
        $dotenv->safeLoad();

        $config = require __DIR__ . '/admin/config/config.php';
        Database::getInstance(
            $config['db_host'],
            $config['db_name'],
            $config['db_user'],
            $config['db_pass']
        );

        $service = new ProduseService();
        $product = $id !== '' ? $service->getIdProduses($id) : null;

        if (!$product) {
            $products = array_values(array_filter($service->getAllProduses(), static function (array $item): bool {
                return (string) ($item['status'] ?? '1') !== '0';
            }));
            $product = $products[0] ?? null;
        }

        require_once __DIR__ . '/system/tecdoc_stock.php';

        return tecdoc_resolve_product_page_row($product);
    } catch (Throwable $exception) {
        error_log('[product-page] ' . $exception->getMessage());
        return null;
    } finally {
        error_reporting($previousErrorReporting);
    }
}

function product_page_related_products(?array $current, int $limit = 4): array
{
    if (!$current) {
        return [];
    }

    $currentId = trim((string) ($current['randomn_id'] ?? ''));
    $category = product_page_category($current);
    $brand = trim((string) ($current['pBrand'] ?? ''));

    $previousErrorReporting = error_reporting();
    error_reporting($previousErrorReporting & ~E_DEPRECATED & ~E_USER_DEPRECATED);

    try {
        require_once __DIR__ . '/admin/vendor/autoload.php';
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/admin');
        $dotenv->safeLoad();
        $config = require __DIR__ . '/admin/config/config.php';
        Database::getInstance(
            $config['db_host'],
            $config['db_name'],
            $config['db_user'],
            $config['db_pass']
        );

        $service = new ProduseService();
        $all = array_values(array_filter($service->getAllProduses(), static function (array $item): bool {
            return (string) ($item['status'] ?? '1') !== '0';
        }));

        $scored = [];
        foreach ($all as $item) {
            $itemId = trim((string) ($item['randomn_id'] ?? ''));
            if ($itemId === '' || $itemId === $currentId) {
                continue;
            }

            $score = 0;
            if ($category !== '' && product_page_category($item) === $category) {
                $score += 2;
            }
            if ($brand !== '' && trim((string) ($item['pBrand'] ?? '')) === $brand) {
                $score += 1;
            }
            if ($score > 0) {
                $scored[] = ['item' => $item, 'score' => $score];
            }
        }

        usort($scored, static function (array $a, array $b): int {
            return $b['score'] <=> $a['score'];
        });

        $related = array_map(static fn (array $row): array => $row['item'], array_slice($scored, 0, $limit));
        $relatedIds = array_flip(array_map(static fn (array $item): string => trim((string) ($item['randomn_id'] ?? '')), $related));

        if (count($related) < $limit) {
            foreach ($all as $item) {
                if (count($related) >= $limit) {
                    break;
                }
                $itemId = trim((string) ($item['randomn_id'] ?? ''));
                if ($itemId === '' || $itemId === $currentId || isset($relatedIds[$itemId])) {
                    continue;
                }
                $related[] = $item;
                $relatedIds[$itemId] = true;
            }
        }

        require_once __DIR__ . '/system/tecdoc_stock.php';

        return tecdoc_deduplicate_catalog_rows_by_supplier_price($related);
    } catch (Throwable $exception) {
        error_log('[product-page-related] ' . $exception->getMessage());
        return [];
    } finally {
        error_reporting($previousErrorReporting);
    }
}

$productPageProduct = product_page_load_product();
$productPageId = trim((string) ($_GET['id'] ?? ($productPageProduct['randomn_id'] ?? '')));
$productPageName = trim((string) ($productPageProduct['pName'] ?? 'Produs indisponibil'));
$productPageCode = trim((string) ($productPageProduct['pCode'] ?? ''));
$productPageBrand = trim((string) ($productPageProduct['pBrand'] ?? ''));
$productPageCar = trim((string) ($productPageProduct['pCar'] ?? ''));
$productPageCategory = $productPageProduct ? product_page_category($productPageProduct) : 'Piese auto';
$productPageDescription = $productPageProduct
    ? (!empty($productPageProduct['_scraper'])
        ? trim((string) ($productPageProduct['pNote'] ?? ''))
        : tecdoc_resolve_product_description($productPageProduct))
    : 'Produs disponibil în catalogul Besoiu Piese Auto. Pentru compatibilitate exactă, recomandăm verificarea după VIN.';
$productPageDescriptionPlain = besoiu_note_plain_text($productPageDescription);
$productPagePrice = product_page_price($productPageProduct['pPrice'] ?? 0);
$productPagePriceLabel = $productPagePrice > 0 ? number_format($productPagePrice, 2, '.', '') . ' RON' : 'La cerere';
$productPageImages = product_page_images($productPageProduct['pImages'] ?? '');
if (!$productPageImages) {
    $productPageImages = ['assets/images/products/1.jpg'];
}
$productPageMainImage = $productPageImages[0];
$productPageRelated = product_page_related_products($productPageProduct, 4);
$productPageAbsoluteUrl = besoiu_absolute_url('/produs?id=' . rawurlencode($productPageId));
$productPageImageAbsolute = (str_starts_with($productPageMainImage, 'http') ? $productPageMainImage : besoiu_absolute_url('/' . ltrim($productPageMainImage, '/')));

$productPageWhatsappFlag = mb_strtolower(trim((string) ($productPageProduct['pWhatsapp'] ?? '')));
$productPageWhatsappEnabled = $productPageWhatsappFlag !== 'nu';
$productPageContactPhone = trim((string) (site_content_blocks('home')['why']['phone'] ?? '0726 498 573'));
if ($productPageWhatsappFlag === 'foloseste telefon cont') {
    $productPageSellerPhone = trim((string) ($productPageProduct['phone'] ?? ''));
    if ($productPageSellerPhone !== '') {
        $productPageContactPhone = $productPageSellerPhone;
    }
}
$productPageWhatsappPrefill = 'Bună! Sunt interesat(ă) de produsul: ' . $productPageName
    . ($productPageCode !== '' ? ' (cod: ' . $productPageCode . ')' : '')
    . '. Link: ' . $productPageAbsoluteUrl;
$productPageWhatsappHref = $productPageWhatsappEnabled
    ? site_phone_to_wa_href($productPageContactPhone, $productPageWhatsappPrefill)
    : '';
$productPageSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $productPageName,
    'sku' => $productPageCode !== '' ? $productPageCode : $productPageId,
    'mpn' => $productPageCode !== '' ? $productPageCode : null,
    'brand' => [
        '@type' => 'Brand',
        'name' => $productPageBrand !== '' ? $productPageBrand : 'Besoiu Piese Auto',
    ],
    'description' => function_exists('mb_substr') ? mb_substr($productPageDescriptionPlain, 0, 500) : substr($productPageDescriptionPlain, 0, 500),
    'image' => array_values(array_map(static function (string $image): string {
        return str_starts_with($image, 'http') ? $image : besoiu_absolute_url('/' . ltrim($image, '/'));
    }, $productPageImages)),
    'url' => $productPageAbsoluteUrl,
    'offers' => [
        '@type' => 'Offer',
        'url' => $productPageAbsoluteUrl,
        'priceCurrency' => 'RON',
        'price' => $productPagePrice > 0 ? number_format($productPagePrice, 2, '.', '') : '0.00',
        'availability' => 'https://schema.org/InStock',
        'itemCondition' => 'https://schema.org/UsedCondition',
        'seller' => [
            '@type' => 'Organization',
            'name' => 'Besoiu Piese Auto',
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= product_page_h($productPageName) ?> | BesoiuPieseAuto</title>
    <meta name="description" content="<?= product_page_h(function_exists('mb_substr') ? mb_substr($productPageDescriptionPlain, 0, 155) : substr($productPageDescriptionPlain, 0, 155)) ?>">
    <meta name="author" content="Galac-Web">
    <script type="application/ld+json"><?= json_encode($productPageSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css">

    <?php besoiu_render_styles('product', ['assets/css/product-page.css'], true); ?>
</head>
<body>
<div class="page">

    <?php include_once 'system/header.php'; ?>

    <main id="main-content">
        <div class="container">

            <nav class="prod-breadcrumb" aria-label="breadcrumb">
                <a href="/">Acasă</a>
                <span class="sep">›</span>
                <a href="/catalog">Catalog</a>
                <span class="sep">›</span>
                <?= product_page_h($productPageName) ?>
            </nav>

            <div class="prod-layout">

                <div class="prod-gallery">
                    <div class="prod-gallery-main">
                        <span class="badge-stock">ÎN STOC</span>
                        <div class="gallery-actions">
                            <button type="button" id="prod-share-btn" class="prod-share-btn" aria-label="Distribuie produsul" data-share-url="<?= product_page_h($productPageAbsoluteUrl) ?>" data-share-title="<?= product_page_h($productPageName) ?>"><i class="fa-solid fa-share-nodes"></i></button>
                            <button type="button" id="prod-fav-btn" class="prod-fav-btn" aria-label="Adaugă la favorite" aria-pressed="false" data-product-id="<?= product_page_h($productPageId) ?>"><i class="fa-regular fa-heart"></i></button>
                        </div>
                        <div class="product-single-carousel owl-carousel owl-theme">
                            <?php foreach ($productPageImages as $imgIndex => $image): ?>
                                <div class="product-item">
                                    <img class="product-single-image"
                                         src="<?= product_page_h($image) ?>"
                                         data-zoom-image="<?= product_page_h($image) ?>"
                                         width="540"
                                         alt="<?= product_page_h($productPageName) ?>"
                                         <?= $imgIndex === 0 ? 'loading="eager" fetchpriority="high"' : 'loading="lazy"' ?>>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="prod-thumbnail owl-dots">
                        <?php foreach ($productPageImages as $thumbIndex => $image): ?>
                            <div class="owl-dot">
                                <img src="<?= product_page_h($image) ?>"
                                     width="72" height="72"
                                     alt="miniatură <?= product_page_h($productPageName) ?>"
                                     <?= $thumbIndex === 0 ? 'loading="eager"' : 'loading="lazy"' ?>>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="prod-details product-single-details" data-product-id="<?= product_page_h($productPageId) ?>">
                    <div class="prod-category-label"><?= product_page_h($productPageCategory) ?></div>
                    <h1 class="product-title"><?= product_page_h($productPageName) ?></h1>

                    <div class="prod-rating">
                        <span class="stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></span>
                        <a href="#product-reviews-content" class="review-link">(1 recenzie)</a>
                    </div>

                    <div class="prod-divider"></div>

                    <div class="price-box">
                        <span class="new-price"><?= product_page_h($productPagePriceLabel) ?></span>
                    </div>
                    <div class="price-note">TVA inclus · Livrare de la 20 RON</div>

                    <div class="stock-badge">
                        <i class="fa-solid fa-circle-check"></i>
                        În stoc · Livrare în 24-48h
                    </div>

                    <ul class="prod-specs-list">
                        <li>
                            <span class="spec-icon"><i class="fa-solid fa-barcode"></i></span>
                            <span class="spec-label">Cod produs</span>
                            <span class="spec-value"><?= product_page_h($productPageCode !== '' ? $productPageCode : 'N/A') ?></span>
                        </li>
                        <li>
                            <span class="spec-icon"><i class="fa-solid fa-industry"></i></span>
                            <span class="spec-label">Producător</span>
                            <span class="spec-value"><?= product_page_h($productPageBrand !== '' ? $productPageBrand : 'Nespecificat') ?></span>
                        </li>
                        <li>
                            <span class="spec-icon"><i class="fa-solid fa-folder-open"></i></span>
                            <span class="spec-label">Categorie</span>
                            <span class="spec-value"><?= product_page_h($productPageCategory) ?></span>
                        </li>
                        <li>
                            <span class="spec-icon"><i class="fa-solid fa-car"></i></span>
                            <span class="spec-label">Compatibil</span>
                            <span class="spec-value"><?= product_page_h($productPageCar !== '' ? $productPageCar : 'Universal') ?></span>
                        </li>
                    </ul>

                    <div class="prod-action">
                        <div class="qty-stepper">
                            <button type="button" class="qty-minus" aria-label="Minus"><i class="fa-solid fa-minus"></i></button>
                            <input type="number" class="horizontal-quantity" placeholder="1" min="1" max="99">
                            <button type="button" class="qty-plus" aria-label="Plus"><i class="fa-solid fa-plus"></i></button>
                        </div>
                        <button class="prod-add-cart add-cart" type="button">
                            <i class="fa-solid fa-cart-shopping"></i>
                            ADAUGĂ ÎN COȘ
                        </button>
                    </div>

                    <?php if ($productPageWhatsappHref !== ''): ?>
                    <a class="prod-whatsapp-btn whatsapp-btn" id="prod-whatsapp-btn" href="<?= product_page_h($productPageWhatsappHref) ?>" target="_blank" rel="noopener noreferrer" aria-label="Contactează vânzătorul pe WhatsApp">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        Contactează pe WhatsApp
                    </a>
                    <?php endif; ?>

                    <div class="prod-phone">sau sună: <a href="<?= product_page_h(site_phone_to_tel_href($productPageContactPhone)) ?>"><?= product_page_h($productPageContactPhone) ?></a></div>

                    <div class="trust-badges">
                        <div class="badge-card">
                            <div class="badge-icon"><i class="fa-solid fa-truck-fast"></i></div>
                            Livrare 24h
                        </div>
                        <div class="badge-card">
                            <div class="badge-icon"><i class="fa-solid fa-shield-halved"></i></div>
                            Garanție 90 zile
                        </div>
                        <div class="badge-card">
                            <div class="badge-icon"><i class="fa-solid fa-rotate-left"></i></div>
                            Retur 14 zile
                        </div>
                    </div>
                </div>

            </div>

            <div class="prod-tabs">
                <ul class="nav nav-tabs" id="productTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="product-tab-desc" data-bs-toggle="tab"
                                data-bs-target="#product-desc-content" type="button" role="tab"
                                aria-controls="product-desc-content" aria-selected="true">
                            Descriere
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="product-tab-fitment" data-bs-toggle="tab"
                                data-bs-target="#product-fitment-content" type="button" role="tab"
                                aria-controls="product-fitment-content" aria-selected="false">
                            Compatibilitate
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="product-tab-specs" data-bs-toggle="tab"
                                data-bs-target="#product-specs-content" type="button" role="tab"
                                aria-controls="product-specs-content" aria-selected="false">
                            Specificații tehnice
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="product-tab-reviews" data-bs-toggle="tab"
                                data-bs-target="#product-reviews-content" type="button" role="tab"
                                aria-controls="product-reviews-content" aria-selected="false">
                            Recenzii (1)
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="productTabsContent">
                    <div class="tab-pane fade show active" id="product-desc-content" role="tabpanel" aria-labelledby="product-tab-desc"
                         data-besoiu-desc-channel="website">
                        <div class="product-desc-content" data-besoiu-desc-target="website">
                            <?= besoiu_note_render($productPageDescription) ?>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="product-fitment-content" role="tabpanel" aria-labelledby="product-tab-fitment">
                        <table>
                            <thead>
                                <tr>
                                    <th>Marcă</th>
                                    <th>Model</th>
                                    <th>Motorizare</th>
                                    <th>Combustibil</th>
                                    <th>An</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?= product_page_h($productPageBrand !== '' ? $productPageBrand : '—') ?></td>
                                    <td><?= product_page_h($productPageCar !== '' ? $productPageCar : '—') ?></td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>—</td>
                                </tr>
                                <tr>
                                    <td colspan="5" style="font-size:13px;color:var(--muted)">Compatibilitatea finală se confirmă după seria VIN. Contactați-ne pentru verificare.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="tab-pane fade" id="product-specs-content" role="tabpanel" aria-labelledby="product-tab-specs">
                        <table>
                            <tbody>
                                <tr><th>Producător</th><td><?= product_page_h($productPageBrand !== '' ? $productPageBrand : 'N/A') ?></td></tr>
                                <tr><th>Cod produs</th><td><?= product_page_h($productPageCode !== '' ? $productPageCode : 'N/A') ?></td></tr>
                                <tr><th>Categorie</th><td><?= product_page_h($productPageCategory) ?></td></tr>
                                <tr><th>Compatibil</th><td><?= product_page_h($productPageCar !== '' ? $productPageCar : 'Verificați după VIN') ?></td></tr>
                                <tr><th>Stare</th><td>Nou</td></tr>
                                <tr><th>Garanție</th><td>Conform politicii magazinului</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="tab-pane fade" id="product-reviews-content" role="tabpanel" aria-labelledby="product-tab-reviews">
                        <div class="review-card">
                            <img src="assets/images/blog/author.jpg" alt="autor" width="56" height="56">
                            <div>
                                <div class="review-stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
                                <div class="review-meta"><strong>Client verificat</strong> – 12 aprilie 2026</div>
                                <div class="review-text">Produs bun, livrare rapidă și compatibilitate confirmată după VIN.</div>
                            </div>
                        </div>

                        <div class="review-form">
                            <h3>Adaugă o recenzie</h3>
                            <form action="#" onsubmit="return false;">
                                <div class="form-field">
                                    <label for="rating">Evaluarea ta <span class="required">*</span></label>
                                    <span class="rating-stars">
                                        <a class="star-1" href="#">1</a>
                                        <a class="star-2" href="#">2</a>
                                        <a class="star-3" href="#">3</a>
                                        <a class="star-4" href="#">4</a>
                                        <a class="star-5" href="#">5</a>
                                    </span>
                                    <select name="rating" id="rating" required style="display:none">
                                        <option value="">Evaluează…</option>
                                        <option value="5">Perfect</option>
                                        <option value="4">Bun</option>
                                        <option value="3">Mediu</option>
                                        <option value="2">Slab</option>
                                        <option value="1">Foarte slab</option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label>Recenzia ta <span class="required">*</span></label>
                                    <textarea rows="5" placeholder="Scrie recenzia ta aici..."></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-field">
                                        <label>Nume <span class="required">*</span></label>
                                        <input type="text" required placeholder="Numele tău">
                                    </div>
                                    <div class="form-field">
                                        <label>Email <span class="required">*</span></label>
                                        <input type="email" required placeholder="adresa@exemplu.ro">
                                    </div>
                                </div>
                                <div class="check-row">
                                    <input type="checkbox" id="save-name">
                                    <label for="save-name">Salvează numele și emailul meu pentru data viitoare.</label>
                                </div>
                                <button type="submit" class="btn-submit">Trimite</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <section class="related-section" id="related-products">
                <h2>Produse similare</h2>
                <?php if ($productPageRelated): ?>
                <div class="related-grid">
                    <?php foreach ($productPageRelated as $relatedItem):
                        $relatedId = trim((string) ($relatedItem['randomn_id'] ?? ''));
                        $relatedName = trim((string) ($relatedItem['pName'] ?? 'Produs'));
                        $relatedPrice = product_page_price($relatedItem['pPrice'] ?? 0);
                        $relatedPriceLabel = $relatedPrice > 0 ? number_format($relatedPrice, 2, '.', '') . ' RON' : 'La cerere';
                        $relatedImages = product_page_images($relatedItem['pImages'] ?? '');
                        $relatedImage = $relatedImages[0] ?? 'assets/images/products/1.jpg';
                    ?>
                    <a href="/produs?id=<?= product_page_h($relatedId) ?>" class="related-card">
                        <div class="card-img">
                            <img src="<?= product_page_h($relatedImage) ?>" alt="<?= product_page_h($relatedName) ?>" loading="lazy" width="200" height="200">
                        </div>
                        <div class="card-name"><?= product_page_h($relatedName) ?></div>
                        <div class="card-price"><?= product_page_h($relatedPriceLabel) ?></div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="related-empty">Momentan nu avem alte produse similare. <a href="/catalog">Vezi catalogul complet</a>.</p>
                <?php endif; ?>
            </section>

        </div>
    </main>

    <?php include_once 'system/footer.php'; ?>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<?php besoiu_render_scripts('product', ['assets/js/product-carousel.js']); ?>

</body>
</html>
