<?php

declare(strict_types=1);

/**
 * Semantica titlurilor din listele furnizor — exclude rânduri care nu sunt produse reale.
 */

function besoiu_import_semantic_is_non_product_title(string $title): bool
{
    $hay = mb_strtolower(trim($title), 'UTF-8');
    if ($hay === '') {
        return false;
    }

    $needles = [
        'consignatie',
        'consignație',
        'consignat',
        'taxa consignatie',
        'taxă consignație',
        'core charge',
        'deposit included',
        'deposit price',
        'garantie piesa',
        'garanție piesă',
        'piesa veche',
        'piesă veche',
        'return old part',
        'old part return',
        'taxa ambalaj',
        'taxă ambalaj',
        'returnable packaging',
    ];

    foreach ($needles as $needle) {
        if (str_contains($hay, $needle)) {
            return true;
        }
    }

    return false;
}
