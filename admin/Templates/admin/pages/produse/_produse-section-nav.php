<?php
declare(strict_types=1);

/** @var string $produseSectionActive */
/** @var int $produseNavVitrinaCount */
/** @var int $produseNavScraperCount */

$produseSectionActive = $produseSectionActive ?? 'lista';
$produseNavVitrinaCount = (int) ($produseNavVitrinaCount ?? 0);
$produseNavScraperCount = (int) ($produseNavScraperCount ?? 0);

$produseSections = [
    'lista' => ['label' => 'Lista produse', 'href' => '/admin/product'],
    'vitrina' => ['label' => 'Vetrina homepage', 'href' => '/admin/vitrina', 'count' => $produseNavVitrinaCount],
    'scanate' => ['label' => 'Produse scanate ePiesa', 'href' => '/admin/scanned', 'count' => $produseNavScraperCount],
    'caiet' => ['label' => 'Caiet comenzi', 'href' => '/admin/caiet-produse'],
];
?>
<div class="besoiu-tabs besoiu-tabs--sections" role="tablist" aria-label="Secțiuni produse">
    <?php foreach ($produseSections as $key => $section): ?>
        <?php $isActive = $produseSectionActive === $key; ?>
        <a href="<?= produse_list_h($section['href']) ?>"
           class="admin-tab besoiu-tabs__btn<?= $isActive ? ' admin-tab--active besoiu-tabs__btn--active' : '' ?>"
           role="tab"
           aria-selected="<?= $isActive ? 'true' : 'false' ?>">
            <?= produse_list_h($section['label']) ?>
            <?php if (isset($section['count'])): ?>
                <span class="admin-tab__count"><?= (int) $section['count'] ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>
