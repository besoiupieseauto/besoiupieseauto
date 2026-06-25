<?php

declare(strict_types=1);

namespace Evasystem\Services\SupplierSearch;

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
