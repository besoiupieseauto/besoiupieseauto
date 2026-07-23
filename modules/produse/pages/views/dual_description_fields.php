<?php
declare(strict_types=1);

if (!function_exists('product_desc_h')) {
    function product_desc_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

function product_desc_render_field(string $noteValue = ''): void
{
    ?>
    <textarea name="pNote" rows="10" placeholder="Descriere generată automat la import (TecDoc / CSV) — editabilă manual..."
              class="w-full rounded-md border bg-background px-3 py-2"><?= product_desc_h($noteValue) ?></textarea>
    <p class="mt-2 text-sm opacity-70">
        O singură descriere pentru site, export și marketplace. Titlul produsului rămâne curat, fără multi-marcă.
    </p>
    <?php
}

/** @deprecated Folosește product_desc_render_field */
function dual_desc_render_tabs(string $websiteValue = '', string $marketplaceValue = ''): void
{
    $note = trim($websiteValue) !== '' ? $websiteValue : $marketplaceValue;
    product_desc_render_field($note);
}

/** @deprecated Nu mai e necesar — fără tab-uri duale. */
function dual_desc_render_script(): void
{
}
