<?php
declare(strict_types=1);

/** @var array<string, mixed> $product */

$title = trim((string) ($product['title'] ?? 'Produs'));
$price = trim((string) ($product['price'] ?? ''));
$image = trim((string) ($product['image'] ?? ''));
$url = trim((string) ($product['url'] ?? '#'));
$catLabel = trim((string) ($product['category_label'] ?? ''));
$desc = trim((string) ($product['description'] ?? ''));
$id = trim((string) ($product['url_path'] ?? md5($url)));
?>
<article class="besoiu-produse-card" data-id="<?= produse_list_h($id) ?>">
    <div class="besoiu-produse-card__media">
        <?php if ($image !== ''): ?>
            <img src="<?= produse_list_h($image) ?>" alt="<?= produse_list_h($title) ?>" loading="lazy">
        <?php else: ?>
            <span class="besoiu-produse-card__placeholder" aria-hidden="true">fără imagine</span>
        <?php endif; ?>
        <?php if ($catLabel !== ''): ?>
            <span class="besoiu-produse-card__badge"><?= produse_list_h($catLabel) ?></span>
        <?php endif; ?>
    </div>
    <div class="besoiu-produse-card__body">
        <h3 class="besoiu-produse-card__title"><?= produse_list_h($title) ?></h3>
        <?php if ($desc !== ''): ?>
            <p class="besoiu-produse-card__desc"><?= produse_list_h($desc) ?></p>
        <?php endif; ?>
        <p class="besoiu-produse-card__price"><?= produse_list_h($price !== '' ? $price : 'La cerere') ?></p>
    </div>
    <div class="besoiu-produse-card__foot">
        <a href="<?= produse_list_h($url) ?>" target="_blank" rel="noopener" class="besoiu-produse-card__link">
            <i data-lucide="external-link"></i>
            ePiesa
        </a>
    </div>
</article>
