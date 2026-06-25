<?php
declare(strict_types=1);

/** @var array<string, mixed> $product */
/** @var int $index */
/** @var bool $showVitrinaBadge */

$showVitrinaBadge = $showVitrinaBadge ?? false;
$id = (string) ($product['randomn_id'] ?? $product['id'] ?? '');
$name = $product['pName'] ?: ($product['name'] ?? 'Produs fara nume');
$price = (string) ($product['pPrice'] ?? '');
$brand = trim((string) ($product['pBrand'] ?? ''));
$productCategory = trim((string) ($product['pCategory'] ?? ''));
$state = (string) ($product['pState'] ?? '');
$city = (string) ($product['pCity'] ?? '');
$car = (string) ($product['pCar'] ?? '');
$code = (string) ($product['pCode'] ?? '');
$category = $productCategory !== '' ? $productCategory : ($car ?: ($city ?: ($state ?: 'Piese auto')));
$active = ((string) ($product['status'] ?? '1') !== '0');
$onVitrina = (int) ($product['pVitrina'] ?? 0) === 1;
$imageUrl = produse_list_first_image($product);
?>
<article class="besoiu-produse-card product-card"
     data-id="<?= produse_list_h($id) ?>"
     data-vitrina="<?= $onVitrina ? '1' : '0' ?>">
    <a class="besoiu-produse-card__media besoiu-produse-card__media--db" href="/admin/editproduse?id=<?= urlencode($id) ?>">
        <img src="<?= produse_list_h($imageUrl) ?>" alt="<?= produse_list_h($name) ?>" loading="lazy">
        <?php if ($showVitrinaBadge || $onVitrina): ?>
            <span class="besoiu-produse-card__badge besoiu-produse-card__badge--vitrina">Vitrină</span>
        <?php endif; ?>
    </a>
    <div class="besoiu-produse-card__body">
        <h3 class="besoiu-produse-card__title">
            <a href="/admin/editproduse?id=<?= urlencode($id) ?>"><?= produse_list_h($name) ?></a>
        </h3>
        <p class="besoiu-produse-card__meta"><?= produse_list_h($category) ?></p>
        <ul class="besoiu-produse-card__facts">
            <li><i data-lucide="wallet"></i><?= $price !== '' ? produse_list_h($price) . ' lei' : 'Preț nesetat' ?></li>
            <li><i data-lucide="tag"></i><?= $brand !== '' ? produse_list_h($brand) : '—' ?></li>
            <li><i data-lucide="barcode"></i><?= $code !== '' ? produse_list_h($code) : '—' ?></li>
            <li><i data-lucide="circle-check"></i><?= produse_list_h($active ? 'Activ' : 'Inactiv') ?></li>
        </ul>
    </div>
    <div class="besoiu-produse-card__foot besoiu-produse-card__foot--actions">
        <a href="/admin/editproduse?id=<?= urlencode($id) ?>" class="besoiu-produse-card__link">
            <i data-lucide="pencil"></i>
            Edit
        </a>
        <?php if ($showVitrinaBadge): ?>
        <button type="button" class="besoiu-produse-card__btn vitrina-toggle-btn" data-id="<?= produse_list_h($id) ?>" data-enabled="<?= $onVitrina ? '1' : '0' ?>">
            <i data-lucide="layout-grid"></i>
            <?= $onVitrina ? 'Pe vitrină' : 'Adaugă vitrină' ?>
        </button>
        <?php endif; ?>
    </div>
</article>
