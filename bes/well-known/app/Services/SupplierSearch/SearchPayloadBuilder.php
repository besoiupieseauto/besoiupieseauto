<?php

namespace App\Services\SupplierSearch;

class SearchPayloadBuilder
{
    /**
     * Build the same payload as the old supplier search for the frontend.
     *
     * @param array $productsMap Associative array code => product
     * @param array $timings Optional timings (e.g. when debug_timings=1)
     * @param array $partial Optional partial info (reason, max_seconds, suppliers)
     */
    public function build(array $productsMap, array $timings = [], array $partial = []): array
    {
        $payload = [
            'success' => true,
            'products' => array_values($productsMap),
        ];

        if (!empty($partial)) {
            $payload['partial'] = $partial;
        }

        if (!empty($timings)) {
            $payload['timings'] = $timings;
        }

        return $payload;
    }
}
