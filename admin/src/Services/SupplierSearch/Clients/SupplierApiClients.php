<?php

declare(strict_types=1);

namespace Evasystem\Services\SupplierSearch\Clients;

use Evasystem\Controllers\Furnizori\AutoPartnerApiClient;
use Evasystem\Services\SupplierSearch\SupplierSearchConfig;

final class MateromClient
{
    /** @return array{success:bool,data:array<int,mixed>,error:string,status:int} */
    public function search(string $query): array
    {
        $token = SupplierSearchConfig::materomToken();
        if ($token === '') {
            return ['success' => false, 'data' => [], 'error' => 'Token Materom lipsă (MATEROM_TOKEN_TIMISOARA).', 'status' => 0];
        }

        $url = SupplierSearchConfig::materomBaseUrl() . '/v4/part_search/global?' . http_build_query(['term' => $query]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = trim((string) curl_error($ch));
        curl_close($ch);

        if ($body === false || $error !== '') {
            return ['success' => false, 'data' => [], 'error' => $error !== '' ? $error : 'Răspuns gol Materom', 'status' => $status];
        }

        $decoded = json_decode((string) $body, true);
        if ($status < 200 || $status >= 300) {
            return ['success' => false, 'data' => is_array($decoded) ? $decoded : [], 'error' => 'HTTP ' . $status, 'status' => $status];
        }

        return [
            'success' => true,
            'data' => is_array($decoded) ? $decoded : [],
            'error' => '',
            'status' => $status,
        ];
    }
}

final class AutopartnerSearchClient
{
    /** @param array<int, array{productCode:string,quantity:int}> $products @return array{success:bool,data:array<string,mixed>,error:string,status:int} */
    public function productsAvailability(array $products): array
    {
        $furnizor = SupplierSearchConfig::autopartnerFurnizor();
        if ($furnizor === null) {
            return ['success' => false, 'data' => [], 'error' => 'Furnizor AUTOPARTNER neconfigurat în admin.', 'status' => 0];
        }

        $client = (new AutoPartnerApiClient())->configure($furnizor);

        return $client->request('/ProductsAvailabilityV2', [
            'products' => $products,
            'onlySite' => false,
        ]);
    }
}
