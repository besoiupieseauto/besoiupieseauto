<?php
declare(strict_types=1);

/** @var array<string, mixed> $product */

$id = produse_vitrina_product_id($product);
?>
<div class="besoiu-vitrina-home-card-wrap" role="listitem" data-product-id="<?= produse_list_h($id) ?>">
    <?php besoiu_render_home_vitrina_card($product); ?>
    <div class="besoiu-vitrina-home-card__foot">
        <button type="button" class="besoiu-btn-secondary besoiu-vitrina-preview-btn" data-action="badge-off" data-product-id="<?= produse_list_h($id) ?>">Scoate din recomandări</button>
        <button type="button" class="besoiu-btn-secondary besoiu-vitrina-preview-btn" data-action="vitrina-off" data-product-id="<?= produse_list_h($id) ?>">Scoate de pe vitrină</button>
    </div>
</div>
