<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $product
 * @return array<string, mixed>|null
 */
function import_resolve_autotal_local_image(array $product): ?array
{
    $libPath = dirname(__DIR__, 2) . '/Services/AutototalLocalImageLibrary.php';
    if (!is_file($libPath)) {
        $libPath = dirname(__DIR__, 4) . '/app/Backend/src/Services/AutototalLocalImageLibrary.php';
    }
    if (!is_file($libPath)) {
        return null;
    }
    require_once $libPath;

    return \Besoiu\Services\AutototalLocalImageLibrary::instance()->lookupForProduct($product);
}
