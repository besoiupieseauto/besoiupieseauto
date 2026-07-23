<?php

declare(strict_types=1);

/**
 * Bridge Plan local_ttc_poze → LocalTtcImageLibrary.
 *
 * @param array<string, mixed> $product
 * @return array<string, mixed>|null
 */
function import_resolve_local_ttc_image(array $product): ?array
{
    $libPath = dirname(__DIR__, 2) . '/Services/LocalTtcImageLibrary.php';
    if (!is_file($libPath)) {
        return null;
    }

    require_once $libPath;

    return \Besoiu\Services\LocalTtcImageLibrary::instance()->lookupForProduct($product);
}
