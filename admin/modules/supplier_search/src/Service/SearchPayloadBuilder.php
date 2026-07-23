<?php
declare(strict_types=1);

namespace Besoiu\Modules\SupplierSearch\Service;

final class SearchPayloadBuilder
{
    /** @param array<string, array<string, mixed>> $productsMap @return array<string, mixed> */
    public function build(array $productsMap, array $timings = []): array
    {
        $payload = [
            'success' => true,
            'products' => array_values($productsMap),
        ];

        if ($timings !== []) {
            $payload['timings'] = $timings;
        }

        return $payload;
    }
}
