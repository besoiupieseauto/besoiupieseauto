<?php

declare(strict_types=1);

/**
 * Bridge Plan autopartner_local → AutopartnerLocalImageLibrary.
 */

function import_resolve_autopartner_local_image(array $product): ?array
{
    $libPath = dirname(__DIR__, 2) . '/Services/AutopartnerLocalImageLibrary.php';
    if (!is_file($libPath)) {
        return null;
    }
    require_once $libPath;

    return \Besoiu\Services\AutopartnerLocalImageLibrary::instance()->lookupForProduct($product);
}
