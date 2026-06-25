<?php

namespace App\Services\SupplierSearchNew\Contracts;

interface SupplierParserInterface
{
    /**
     * Parse raw API response into product entries for aggregation.
     * Returns array of entries, each: [ 'code' => string, 'mfrpn' => string, 'manufacturer' => ?string, 'db_name' => ?string, 'name' => ?string, 'ean' => ?string, 'material' => ?string, 'supplier_name' => string, 'variants' => array ]
     *
     * @param string $query Normalized search query (no spaces/dashes).
     * @param mixed $rawResponse Decoded response (array or object) or raw body.
     * @param string $rawBody Raw response body string.
     */
    public function parse(string $query, $rawResponse, string $rawBody): array;
}
