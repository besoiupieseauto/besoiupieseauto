<?php
declare(strict_types=1);

/**
 * Decodare VIN TecDoc via RapidAPI auto-parts-catalog + căutare stoc pe vehicul.
 */

function tecdoc_normalize_vin(string $vin): string
{
    return strtoupper(preg_replace('/[^A-Z0-9]/', '', $vin) ?? '');
}

function tecdoc_is_vin_query(string $value): bool
{
    $vin = tecdoc_normalize_vin($value);
    if (strlen($vin) !== 17) {
        return false;
    }
    if (preg_match('/[IOQ]/', $vin)) {
        return false;
    }

    return (bool) preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin);
}

/** @return array<int, string> */
function tecdoc_vin_manufacturer_map(array $data): array
{
    $map = [];
    $rows = $data['matchingManufacturers']['array'] ?? $data['matchingManufacturers'] ?? [];
    if (!is_array($rows)) {
        return $map;
    }
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = (int) ($row['manuId'] ?? 0);
        $name = trim((string) ($row['manuName'] ?? ''));
        if ($id > 0 && $name !== '') {
            $map[$id] = $name;
        }
    }

    return $map;
}

/** @return array<int, string> */
function tecdoc_vin_model_map(array $data): array
{
    $map = [];
    $rows = $data['matchingModels']['array'] ?? $data['matchingModels'] ?? [];
    if (!is_array($rows)) {
        return $map;
    }
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = (int) ($row['modelId'] ?? 0);
        $name = trim((string) ($row['modelName'] ?? ''));
        if ($id > 0 && $name !== '') {
            $map[$id] = $name;
        }
    }

    return $map;
}

/** @return array<int, array<string, mixed>> */
function tecdoc_vin_extract_vehicle_list(array $data): array
{
    foreach (['matchingVehicles', 'matchingVehicle'] as $key) {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            continue;
        }
        $node = $data[$key];
        if (isset($node['array']) && is_array($node['array'])) {
            return array_values(array_filter($node['array'], 'is_array'));
        }
        if ($node !== [] && array_keys($node) === range(0, count($node) - 1)) {
            return array_values(array_filter($node, 'is_array'));
        }
    }

    return [];
}

/** @param array<string, mixed> $vehicleRow */
function tecdoc_vin_normalize_vehicle_row(array $vehicleRow): array
{
    $carId = (int) ($vehicleRow['carId'] ?? $vehicleRow['vehicleId'] ?? 0);
    $manuName = trim((string) ($vehicleRow['manuName'] ?? ''));
    $modelName = trim((string) ($vehicleRow['modelName'] ?? ''));
    $motor = trim((string) ($vehicleRow['vehicleTypeDescription'] ?? ''));
    $carName = trim((string) ($vehicleRow['carName'] ?? ''));

    if ($manuName === '' && $carName !== '') {
        $parts = preg_split('/\s+/u', $carName, 2) ?: [];
        if (!empty($parts[0])) {
            $manuName = trim((string) $parts[0]);
        }
    }

    $label = $carName;
    if ($label === '') {
        $label = trim($manuName . ' ' . $modelName . ' ' . $motor);
    }

    return [
        'car_id' => $carId,
        'label' => $label,
        'manu_name' => $manuName,
        'model_name' => $modelName,
        'motor' => $motor,
        'car_name' => $carName !== '' ? $carName : $label,
        'manu_id' => (int) ($vehicleRow['manuId'] ?? 0),
        'model_id' => (int) ($vehicleRow['modelId'] ?? 0),
    ];
}

/** @return array<string, mixed> */
function tecdoc_parse_vin_decode_body(string $body, string $vin): array
{
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return [
            'success' => false,
            'message' => 'Răspuns invalid de la serviciul VIN.',
            'vin_invalid' => true,
        ];
    }

    if (isset($decoded['message']) && is_string($decoded['message']) && trim($decoded['message']) !== '') {
        $message = trim($decoded['message']);
        if (tecdoc_is_quota_exceeded($message)) {
            return [
                'success' => false,
                'message' => tecdoc_quota_user_message(),
                'quota_exceeded' => true,
            ];
        }
    }

    $data = $decoded['data'] ?? $decoded;
    if (!is_array($data)) {
        return [
            'success' => false,
            'message' => 'Nu am găsit un vehicul pentru acest VIN.',
            'vin_invalid' => true,
        ];
    }

    $manuMap = tecdoc_vin_manufacturer_map($data);
    $modelMap = tecdoc_vin_model_map($data);
    $vehiclesRaw = tecdoc_vin_extract_vehicle_list($data);
    if ($vehiclesRaw === []) {
        return [
            'success' => false,
            'message' => 'Nu am găsit un vehicul compatibil pentru VIN-ul introdus. Verifică numărul sau contactează-ne.',
            'vin_invalid' => true,
        ];
    }

    $vehicles = [];
    foreach ($vehiclesRaw as $row) {
        $manuId = (int) ($row['manuId'] ?? 0);
        $modelId = (int) ($row['modelId'] ?? 0);
        if ($manuId > 0 && empty($row['manuName']) && isset($manuMap[$manuId])) {
            $row['manuName'] = $manuMap[$manuId];
        }
        if ($modelId > 0 && empty($row['modelName']) && isset($modelMap[$modelId])) {
            $row['modelName'] = $modelMap[$modelId];
        }

        $normalized = tecdoc_vin_normalize_vehicle_row($row);
        if ($normalized['car_id'] > 0) {
            $vehicles[] = $normalized;
        }
    }

    if ($vehicles === []) {
        return [
            'success' => false,
            'message' => 'VIN recunoscut parțial, dar vehiculul nu a putut fi mapat în catalog.',
            'vin_invalid' => true,
        ];
    }

    $primary = $vehicles[0];

    return [
        'success' => true,
        'vin' => $vin,
        'car_id' => (int) $primary['car_id'],
        'vehicle' => $primary,
        'vehicles' => $vehicles,
        'vehicles_count' => count($vehicles),
    ];
}

/** @return array<string, mixed> */
function tecdoc_decode_vin(string $vin): array
{
    $vin = tecdoc_normalize_vin($vin);
    if (!tecdoc_is_vin_query($vin)) {
        return [
            'success' => false,
            'message' => 'VIN invalid. Introdu exact 17 caractere alfanumerice (fără I, O, Q).',
            'vin_invalid' => true,
        ];
    }

    if (tecdoc_api_is_unavailable()) {
        return [
            'success' => false,
            'message' => tecdoc_quota_user_message(),
            'quota_exceeded' => true,
        ];
    }

    $host = BESOiu_TECDOC_HOST;
    $url = "https://$host/vin/tecdoc-vin-check/" . rawurlencode($vin);
    $body = tecdoc_cached_response($url, 86400 * 7);
    $parsed = tecdoc_parse_vin_decode_body($body, $vin);

    if (!empty($parsed['success'])) {
        return $parsed;
    }

    if (!empty($parsed['quota_exceeded'])) {
        return $parsed;
    }

    $probe = tecdoc_http_get($url);
    $httpCode = (int) ($probe['http_code'] ?? 0);
    if ($httpCode === 404) {
        return [
            'success' => false,
            'message' => 'Serviciul VIN nu este disponibil pe planul RapidAPI curent. Verifică abonamentul auto-parts-catalog.',
            'http_code' => 404,
        ];
    }

    if ($httpCode >= 400) {
        return [
            'success' => false,
            'message' => 'Nu am putut decoda VIN-ul acum. Încearcă din nou sau contactează-ne telefonic.',
            'http_code' => $httpCode,
        ];
    }

    return $parsed;
}

/** @param array<string, mixed> $vehicle @return array<int, string> */
function tecdoc_vin_vehicle_keywords(array $vehicle): array
{
    $keywords = [];
    foreach (['manu_name', 'model_name', 'car_name', 'motor', 'label'] as $key) {
        $text = trim((string) ($vehicle[$key] ?? ''));
        if ($text === '') {
            continue;
        }
        foreach (preg_split('/\s+/u', $text) ?: [] as $token) {
            $token = trim($token);
            if ($token === '' || mb_strlen($token, 'UTF-8') < 2) {
                continue;
            }
            if (preg_match('/^(I{1,3}|IV|VI|VII|VIII|IX|X)$/u', $token)) {
                continue;
            }
            $keywords[] = $token;
        }
    }

    return array_values(array_unique($keywords));
}

/** @param array<string, mixed> $vehicle @param array<string, mixed> $filters @return array<int, array<string, mixed>> */
function tecdoc_query_products_for_vehicle(PDO $pdo, array $vehicle, array $filters = [], int $limit = 80): array
{
    $searchFilters = $filters;
    unset($searchFilters['vin']);

    $manu = trim((string) ($vehicle['manu_name'] ?? ''));
    if ($manu !== '' && trim((string) ($searchFilters['marca'] ?? '')) === '') {
        $searchFilters['marca'] = $manu;
    }

    $products = tecdoc_query_bd_products($pdo, $searchFilters, $limit);
    if ($products === []) {
        return [];
    }

    $manuLower = mb_strtolower($manu, 'UTF-8');
    $modelLower = mb_strtolower(trim((string) ($vehicle['model_name'] ?? '')), 'UTF-8');
    $modelToken = $modelLower !== '' ? mb_substr($modelLower, 0, 4, 'UTF-8') : '';

    $filtered = array_values(array_filter($products, static function (array $product) use ($manuLower, $modelToken): bool {
        $haystack = mb_strtolower(implode(' ', [
            (string) ($product['name'] ?? ''),
            (string) ($product['marca'] ?? ''),
            (string) ($product['category'] ?? ''),
            (string) ($product['note'] ?? ''),
            (string) ($product['desc'] ?? ''),
        ]), 'UTF-8');

        if ($manuLower !== '' && str_contains($haystack, $manuLower)) {
            return true;
        }
        if ($modelToken !== '' && str_contains($haystack, $modelToken)) {
            return true;
        }

        return $manuLower === '' && $modelToken === '';
    }));

    return $filtered !== [] ? $filtered : $products;
}

/** @param array<string, mixed> $decode @param array<string, mixed> $filters @return array<string, mixed> */
function tecdoc_vin_vehicle_only_search(PDO $pdo, array $decode, array $filters): array
{
    $vehicle = is_array($decode['vehicle'] ?? null) ? $decode['vehicle'] : [];
    $carId = (int) ($decode['car_id'] ?? 0);
    $vin = (string) ($decode['vin'] ?? '');
    $label = trim((string) ($vehicle['label'] ?? ''));
    $vehiclesCount = (int) ($decode['vehicles_count'] ?? count($decode['vehicles'] ?? []));

    $products = tecdoc_query_products_for_vehicle($pdo, $vehicle, $filters, 80);
    $notice = $label !== ''
        ? 'Vehicul identificat: ' . $label . '. '
        : '';

    if ($vehiclesCount > 1) {
        $notice .= 'TecDoc a găsit ' . $vehiclesCount . ' motorizări posibile — afișăm prima potrivire. ';
    }

    if ($products === []) {
        $notice .= 'Nu avem piese în stoc mapate pentru acest vehicul. Introdu codul OEM sau contactează-ne.';
    } else {
        $notice .= 'Afișăm piese din stoc compatibile cu vehiculul decodat. Pentru potrivire exactă, folosește și codul OEM.';
    }

    return [
        'success' => true,
        'source' => 'bd_vin',
        'count' => count($products),
        'scanned' => 0,
        'products' => $products,
        'stock_brands' => tecdoc_collect_stock_brand_labels($products),
        'vehicle' => $vehicle,
        'vin' => $vin,
        'car_id' => $carId,
        'notice' => trim($notice),
    ];
}

/** @param array<string, mixed> $filters @return array<string, mixed> */
function tecdoc_public_search_by_vin(array $filters): array
{
    $pdo = tecdoc_db();
    $vin = tecdoc_normalize_vin((string) ($filters['vin'] ?? ''));
    $decode = tecdoc_decode_vin($vin);

    if (empty($decode['success'])) {
        search_log_write(
            $pdo,
            'vin',
            $vin,
            false,
            null,
            null,
            0,
            (string) ($decode['message'] ?? 'VIN invalid'),
            [
                'source' => 'vin',
                'filters' => array_filter(['vin' => $vin]),
                'decode_error' => (string) ($decode['message'] ?? ''),
            ]
        );

        return array_merge($decode, [
            'source' => 'vin',
            'count' => 0,
            'scanned' => 0,
            'products' => [],
        ]);
    }

    $inner = $filters;
    unset($inner['vin']);
    $inner['car_id'] = (int) ($decode['car_id'] ?? 0);

    $vehicle = is_array($decode['vehicle'] ?? null) ? $decode['vehicle'] : [];
    if (trim((string) ($inner['marca'] ?? '')) === '' && !empty($vehicle['manu_name'])) {
        $inner['marca'] = (string) $vehicle['manu_name'];
    }

    $hasTextQuery = trim((string) ($inner['oem'] ?? '')) !== ''
        || trim((string) ($inner['name'] ?? '')) !== ''
        || trim((string) ($inner['subcategory'] ?? '')) !== '';

    $result = $hasTextQuery
        ? tecdoc_public_search_core($inner)
        : tecdoc_vin_vehicle_only_search($pdo, $decode, $inner);

    $result['vehicle'] = $vehicle;
    $result['vin'] = $vin;
    $result['car_id'] = (int) ($decode['car_id'] ?? 0);
    if (!empty($decode['vehicles']) && is_array($decode['vehicles'])) {
        $result['vehicles'] = $decode['vehicles'];
    }

    $count = (int) ($result['count'] ?? 0);
    $products = is_array($result['products'] ?? null) ? $result['products'] : [];
    $meta = search_log_scan_meta_from_context($inner, $result, $products);
    $meta['vin'] = $vin;
    if (!empty($decode['vehicles']) && is_array($decode['vehicles'])) {
        $meta['vehicles_count'] = count($decode['vehicles']);
    }

    if ($count === 0) {
        search_log_write(
            $pdo,
            'vin',
            $vin,
            false,
            (int) ($decode['car_id'] ?? 0),
            (string) ($vehicle['label'] ?? ''),
            0,
            (string) ($result['notice'] ?? 'Fără rezultate'),
            $meta
        );
    } else {
        search_log_write(
            $pdo,
            'vin',
            $vin,
            true,
            (int) ($decode['car_id'] ?? 0),
            (string) ($vehicle['label'] ?? ''),
            $count,
            null,
            $meta
        );
    }

    return $result;
}
