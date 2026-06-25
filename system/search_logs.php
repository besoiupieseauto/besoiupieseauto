<?php
declare(strict_types=1);

/**
 * Jurnal căutări VIN/OEM — pentru extindere stoc și dashboard admin.
 */

function search_logs_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute(['search_logs']);
    $exists = ((int) $stmt->fetchColumn()) > 0;

    return $exists;
}

function search_logs_has_meta_column(PDO $pdo): bool
{
    static $has = null;
    if ($has !== null) {
        return $has;
    }
    if (!search_logs_table_exists($pdo)) {
        $has = false;
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute(['search_logs', 'meta_json']);
    $has = ((int) $stmt->fetchColumn()) > 0;

    return $has;
}

function search_log_client_hash(): ?string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($ip === '') {
        return null;
    }

    return hash('sha256', $ip . '|' . (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

/** @param array<string, mixed> $filters */
function search_log_vehicle_from_filters(array $filters): ?string
{
    $parts = array_values(array_filter([
        trim((string) ($filters['marca'] ?? '')),
        trim((string) ($filters['model'] ?? '')),
        trim((string) ($filters['motorizare'] ?? '')),
    ], static fn (string $part): bool => $part !== ''));

    if ($parts === []) {
        return null;
    }

    return mb_substr(implode(' · ', $parts), 0, 255, 'UTF-8');
}

/**
 * @param array<string, mixed> $filters
 * @param array<string, mixed> $result
 * @param array<int, array<string, mixed>> $products
 * @return array<string, mixed>
 */
function search_log_scan_meta_from_context(array $filters, array $result, array $products = []): array
{
    $filterKeys = ['category', 'subcategory', 'marca', 'model', 'motorizare', 'oem', 'name', 'vin'];
    $activeFilters = [];
    foreach ($filterKeys as $key) {
        $value = trim((string) ($filters[$key] ?? ''));
        if ($value !== '') {
            $activeFilters[$key] = $value;
        }
    }

    $carId = (int) ($filters['car_id'] ?? 0);
    if ($carId > 0) {
        $activeFilters['car_id'] = $carId;
    }
    $nodeId = (int) ($filters['node_id'] ?? 0);
    if ($nodeId > 0) {
        $activeFilters['node_id'] = $nodeId;
    }

    $preview = [];
    foreach (array_slice($products, 0, 5) as $product) {
        if (!is_array($product)) {
            continue;
        }
        $preview[] = [
            'id' => (string) ($product['randomn_id'] ?? $product['id'] ?? ''),
            'name' => (string) ($product['name'] ?? $product['pName'] ?? ''),
            'code' => (string) ($product['code'] ?? $product['pCode'] ?? ''),
            'brand' => (string) ($product['brand'] ?? $product['pBrand'] ?? ''),
            'category' => (string) ($product['category'] ?? $product['pCategory'] ?? ''),
        ];
    }

    $meta = [
        'source' => (string) ($result['source'] ?? 'bd'),
        'filters' => $activeFilters,
        'products_preview' => $preview,
    ];

    $fallback = trim((string) ($result['fallback'] ?? ''));
    if ($fallback !== '') {
        $meta['fallback'] = $fallback;
    }

    if (!empty($result['vehicle']) && is_array($result['vehicle'])) {
        $meta['vehicle'] = $result['vehicle'];
    }

    return $meta;
}

/** @param mixed $raw @return array<string, mixed>|null */
function search_log_parse_meta($raw): ?array
{
    if ($raw === null || $raw === '') {
        return null;
    }
    if (is_array($raw)) {
        return $raw;
    }
    if (!is_string($raw)) {
        return null;
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : null;
}

/** @param array<string, mixed> $row @return array<string, mixed> */
function search_logs_enrich_row(array $row): array
{
    $row['meta'] = search_log_parse_meta($row['meta_json'] ?? null);
    unset($row['meta_json']);

    return $row;
}

function search_log_write(
    PDO $pdo,
    string $queryType,
    string $queryValue,
    bool $found,
    ?int $carId = null,
    ?string $vehicleLabel = null,
    int $resultCount = 0,
    ?string $notice = null,
    ?array $meta = null
): void {
    if (!search_logs_table_exists($pdo)) {
        return;
    }

    $queryType = in_array($queryType, ['vin', 'oem', 'name'], true) ? $queryType : 'name';
    $queryValue = mb_substr(trim($queryValue), 0, 128, 'UTF-8');
    if ($queryValue === '') {
        return;
    }

    // Evită spam: aceeași căutare negăsită în ultimele 15 minute
    if (!$found) {
        $dup = $pdo->prepare(
            'SELECT id FROM search_logs
             WHERE query_type = ? AND query_value = ? AND found = 0
               AND created_at >= (NOW() - INTERVAL 15 MINUTE)
             LIMIT 1'
        );
        $dup->execute([$queryType, $queryValue]);
        if ($dup->fetchColumn()) {
            return;
        }
    }

    $hasMeta = search_logs_has_meta_column($pdo);
    $metaJson = null;
    if ($hasMeta && $meta !== null && $meta !== []) {
        $encoded = json_encode($meta, JSON_UNESCAPED_UNICODE);
        if (is_string($encoded)) {
            $metaJson = $encoded;
        }
    }

    if ($hasMeta) {
        $stmt = $pdo->prepare(
            'INSERT INTO search_logs
                (query_type, query_value, found, car_id, vehicle_label, result_count, notice, meta_json, ip_hash, created_at)
             VALUES
                (:query_type, :query_value, :found, :car_id, :vehicle_label, :result_count, :notice, :meta_json, :ip_hash, NOW())'
        );
        $stmt->execute([
            ':query_type' => $queryType,
            ':query_value' => $queryValue,
            ':found' => $found ? 1 : 0,
            ':car_id' => $carId !== null && $carId > 0 ? $carId : null,
            ':vehicle_label' => $vehicleLabel !== null && $vehicleLabel !== ''
                ? mb_substr($vehicleLabel, 0, 255, 'UTF-8')
                : null,
            ':result_count' => max(0, $resultCount),
            ':notice' => $notice !== null && $notice !== ''
                ? mb_substr($notice, 0, 500, 'UTF-8')
                : null,
            ':meta_json' => $metaJson,
            ':ip_hash' => search_log_client_hash(),
        ]);

        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO search_logs
            (query_type, query_value, found, car_id, vehicle_label, result_count, notice, ip_hash, created_at)
         VALUES
            (:query_type, :query_value, :found, :car_id, :vehicle_label, :result_count, :notice, :ip_hash, NOW())'
    );
    $stmt->execute([
        ':query_type' => $queryType,
        ':query_value' => $queryValue,
        ':found' => $found ? 1 : 0,
        ':car_id' => $carId !== null && $carId > 0 ? $carId : null,
        ':vehicle_label' => $vehicleLabel !== null && $vehicleLabel !== ''
            ? mb_substr($vehicleLabel, 0, 255, 'UTF-8')
            : null,
        ':result_count' => max(0, $resultCount),
        ':notice' => $notice !== null && $notice !== ''
            ? mb_substr($notice, 0, 500, 'UTF-8')
            : null,
        ':ip_hash' => search_log_client_hash(),
    ]);
}

/** @return array<int, array<string, mixed>> */
function search_logs_list(PDO $pdo, int $limit = 100, bool $notFoundOnly = false): array
{
    return search_logs_query($pdo, [
        'limit' => $limit,
        'not_found_only' => $notFoundOnly,
    ]);
}

/**
 * @param array{
 *   limit?: int,
 *   offset?: int,
 *   not_found_only?: bool,
 *   found?: string|int,
 *   query_type?: string,
 *   q?: string,
 *   date_from?: string,
 *   date_to?: string
 * } $filters
 * @return array<int, array<string, mixed>>
 */
function search_logs_query(PDO $pdo, array $filters = []): array
{
    if (!search_logs_table_exists($pdo)) {
        return [];
    }

    $limit = max(1, min(500, (int) ($filters['limit'] ?? 100)));
    $offset = max(0, (int) ($filters['offset'] ?? 0));
    [$where, $params] = search_logs_build_where($filters);

    $columns = 'id, query_type, query_value, found, car_id, vehicle_label, result_count, notice, created_at';
    if (search_logs_has_meta_column($pdo)) {
        $columns .= ', meta_json';
    }

    $sql = 'SELECT ' . $columns . ' FROM search_logs';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_map(static fn (array $row): array => search_logs_enrich_row($row), $rows);
}

/**
 * @param array<string, mixed> $filters
 * @return array{0: array<int, string>, 1: array<int, mixed>}
 */
function search_logs_build_where(array $filters): array
{
    $where = [];
    $params = [];

    if (!empty($filters['not_found_only'])) {
        $where[] = 'found = 0';
    } elseif (isset($filters['found']) && $filters['found'] !== '') {
        $where[] = 'found = ?';
        $params[] = (int) $filters['found'] === 1 ? 1 : 0;
    }

    $queryType = trim((string) ($filters['query_type'] ?? ''));
    if ($queryType !== '' && in_array($queryType, ['vin', 'oem', 'name'], true)) {
        $where[] = 'query_type = ?';
        $params[] = $queryType;
    }

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(query_value LIKE ? OR vehicle_label LIKE ? OR notice LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like);
    }

    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $where[] = 'DATE(created_at) >= ?';
        $params[] = $dateFrom;
    }

    $dateTo = trim((string) ($filters['date_to'] ?? ''));
    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $where[] = 'DATE(created_at) <= ?';
        $params[] = $dateTo;
    }

    return [$where, $params];
}

/** @param array<string, mixed> $filters */
function search_logs_count(PDO $pdo, array $filters = []): int
{
    if (!search_logs_table_exists($pdo)) {
        return 0;
    }

    $filtersCopy = $filters;
    unset($filtersCopy['limit'], $filtersCopy['offset']);
    [$where, $params] = search_logs_build_where($filtersCopy);

    $sql = 'SELECT COUNT(*) FROM search_logs';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/** @return array<string, int> */
function search_logs_stats(PDO $pdo): array
{
    if (!search_logs_table_exists($pdo)) {
        return [
            'total' => 0,
            'not_found' => 0,
            'found' => 0,
            'today' => 0,
            'today_not_found' => 0,
            'today_found' => 0,
            'vin_not_found' => 0,
            'oem_not_found' => 0,
            'vin_found' => 0,
            'oem_found' => 0,
            'name_found' => 0,
            'name_not_found' => 0,
        ];
    }

    $row = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            SUM(found = 0) AS not_found,
            SUM(found = 1) AS found,
            SUM(DATE(created_at) = CURDATE()) AS today,
            SUM(DATE(created_at) = CURDATE() AND found = 0) AS today_not_found,
            SUM(DATE(created_at) = CURDATE() AND found = 1) AS today_found,
            SUM(found = 0 AND query_type = 'vin') AS vin_not_found,
            SUM(found = 0 AND query_type = 'oem') AS oem_not_found,
            SUM(found = 0 AND query_type = 'name') AS name_not_found,
            SUM(found = 1 AND query_type = 'vin') AS vin_found,
            SUM(found = 1 AND query_type = 'oem') AS oem_found,
            SUM(found = 1 AND query_type = 'name') AS name_found
         FROM search_logs"
    )->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total' => (int) ($row['total'] ?? 0),
        'not_found' => (int) ($row['not_found'] ?? 0),
        'found' => (int) ($row['found'] ?? 0),
        'today' => (int) ($row['today'] ?? 0),
        'today_not_found' => (int) ($row['today_not_found'] ?? 0),
        'today_found' => (int) ($row['today_found'] ?? 0),
        'vin_not_found' => (int) ($row['vin_not_found'] ?? 0),
        'oem_not_found' => (int) ($row['oem_not_found'] ?? 0),
        'name_not_found' => (int) ($row['name_not_found'] ?? 0),
        'vin_found' => (int) ($row['vin_found'] ?? 0),
        'oem_found' => (int) ($row['oem_found'] ?? 0),
        'name_found' => (int) ($row['name_found'] ?? 0),
    ];
}

/** @return array<int, array{day:string,total:int,found:int,not_found:int}> */
function search_logs_daily_trend(PDO $pdo, int $days = 14): array
{
    if (!search_logs_table_exists($pdo)) {
        return [];
    }

    $days = max(1, min(90, $days));
    $stmt = $pdo->prepare(
        "SELECT DATE(created_at) AS day,
                COUNT(*) AS total,
                SUM(found = 1) AS found,
                SUM(found = 0) AS not_found
         FROM search_logs
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY DATE(created_at)
         ORDER BY day ASC"
    );
    $stmt->execute([$days - 1]);

    $byDay = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $byDay[(string) ($row['day'] ?? '')] = [
            'total' => (int) ($row['total'] ?? 0),
            'found' => (int) ($row['found'] ?? 0),
            'not_found' => (int) ($row['not_found'] ?? 0),
        ];
    }

    $today = new DateTimeImmutable('today');
    $result = [];
    for ($offset = $days - 1; $offset >= 0; $offset--) {
        $day = $today->sub(new DateInterval('P' . $offset . 'D'))->format('Y-m-d');
        $stats = $byDay[$day] ?? ['total' => 0, 'found' => 0, 'not_found' => 0];
        $result[] = [
            'day' => $day,
            'total' => $stats['total'],
            'found' => $stats['found'],
            'not_found' => $stats['not_found'],
        ];
    }

    return $result;
}

/** @return array<int, array<string, mixed>> */
function search_logs_top_oem(PDO $pdo, int $limit = 10): array
{
    if (!search_logs_table_exists($pdo)) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $sql = "SELECT query_value,
                   COUNT(*) AS attempts,
                   SUM(found = 1) AS found_count,
                   SUM(found = 0) AS not_found_count,
                   MAX(created_at) AS last_seen
            FROM search_logs
            WHERE query_type = 'oem'
            GROUP BY query_value
            ORDER BY attempts DESC, last_seen DESC
            LIMIT {$limit}";

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array<int, array<string, mixed>> */
function search_logs_top_missing(PDO $pdo, int $limit = 10): array
{
    return search_logs_top_grouped($pdo, false, $limit);
}

function search_logs_missing_codes_count(PDO $pdo): int
{
    if (!search_logs_table_exists($pdo)) {
        return 0;
    }

    return (int) $pdo->query(
        'SELECT COUNT(*) FROM (
            SELECT 1 FROM search_logs WHERE found = 0 GROUP BY query_type, query_value
        ) grouped_missing'
    )->fetchColumn();
}

/** @return array<int, array<string, mixed>> */
function search_logs_top_found(PDO $pdo, int $limit = 10): array
{
    return search_logs_top_grouped($pdo, true, $limit);
}

/** @return array<int, array<string, mixed>> */
function search_logs_top_grouped(PDO $pdo, bool $found, int $limit = 10): array
{
    if (!search_logs_table_exists($pdo)) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $foundInt = $found ? 1 : 0;
    $sql = "SELECT query_type, query_value,
                   COUNT(*) AS attempts,
                   SUM(result_count) AS total_results,
                   MAX(result_count) AS max_results,
                   MAX(created_at) AS last_seen,
                   MAX(vehicle_label) AS vehicle_label
            FROM search_logs
            WHERE found = {$foundInt}
            GROUP BY query_type, query_value
            ORDER BY attempts DESC, last_seen DESC
            LIMIT {$limit}";

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @param array<string, mixed> $filters
 * @return array<int, array<string, mixed>>
 */
function search_logs_export_rows(PDO $pdo, array $filters = []): array
{
    $filters['limit'] = max(1, min(5000, (int) ($filters['limit'] ?? 2000)));
    $filters['offset'] = 0;

    return search_logs_query($pdo, $filters);
}

/** @param array<int, array<string, mixed>> $rows */
function search_logs_csv_content(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    if ($handle === false) {
        return '';
    }

    fputcsv($handle, [
        'id',
        'query_type',
        'query_value',
        'found',
        'car_id',
        'vehicle_label',
        'result_count',
        'notice',
        'meta_json',
        'created_at',
    ], ';');

    foreach ($rows as $row) {
        $meta = $row['meta'] ?? search_log_parse_meta($row['meta_json'] ?? null);
        fputcsv($handle, [
            $row['id'] ?? '',
            $row['query_type'] ?? '',
            $row['query_value'] ?? '',
            !empty($row['found']) ? '1' : '0',
            $row['car_id'] ?? '',
            $row['vehicle_label'] ?? '',
            $row['result_count'] ?? '',
            $row['notice'] ?? '',
            $meta !== null ? json_encode($meta, JSON_UNESCAPED_UNICODE) : '',
            $row['created_at'] ?? '',
        ], ';');
    }

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    return is_string($csv) ? $csv : '';
}

/**
 * Agregă căutări frecvente din jurnal — alimentează filtre și sugestii storefront (tm_115).
 *
 * @return array{
 *   available: bool,
 *   vehicles: array<int, array<string, mixed>>,
 *   categories: array<int, array<string, mixed>>,
 *   marci: array<int, array<string, mixed>>,
 *   subcategories: array<int, array<string, mixed>>,
 *   modele: array<int, array<string, mixed>>,
 *   queries: array<int, array<string, mixed>>,
 *   generated_at: string
 * }
 */
function search_logs_storefront_insights(PDO $pdo, int $limit = 8, int $days = 90): array
{
    $empty = [
        'available' => false,
        'vehicles' => [],
        'categories' => [],
        'marci' => [],
        'subcategories' => [],
        'modele' => [],
        'queries' => [],
        'generated_at' => date('c'),
    ];

    if (!search_logs_table_exists($pdo)) {
        return $empty;
    }

    $limit = max(1, min(20, $limit));
    $days = max(7, min(365, $days));
    $hasMeta = search_logs_has_meta_column($pdo);

    $vehicles = search_logs_insights_top_vehicles($pdo, $limit, $days);
    $queries = search_logs_insights_top_queries($pdo, $limit, $days);

    if ($hasMeta) {
        $categories = search_logs_insights_meta_group($pdo, 'category', $limit, $days);
        $marci = search_logs_insights_meta_group($pdo, 'marca', $limit, $days);
        $subcategories = search_logs_insights_meta_group($pdo, 'subcategory', $limit, $days);
        $modele = search_logs_insights_meta_group($pdo, 'model', $limit, $days);
    } else {
        $categories = search_logs_insights_vehicle_label_marca($pdo, $limit, $days);
        $marci = $categories;
        $subcategories = [];
        $modele = [];
    }

    return [
        'available' => $vehicles !== [] || $categories !== [] || $marci !== [] || $queries !== [],
        'vehicles' => $vehicles,
        'categories' => $categories,
        'marci' => $marci,
        'subcategories' => $subcategories,
        'modele' => $modele,
        'queries' => $queries,
        'generated_at' => date('c'),
    ];
}

/** @return array<int, array<string, mixed>> */
function search_logs_insights_top_vehicles(PDO $pdo, int $limit, int $days): array
{
    $stmt = $pdo->prepare(
        "SELECT car_id,
                MAX(vehicle_label) AS vehicle_label,
                COUNT(*) AS search_count,
                MAX(created_at) AS last_seen
         FROM search_logs
         WHERE found = 1
           AND car_id IS NOT NULL
           AND car_id > 0
           AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
         GROUP BY car_id
         ORDER BY search_count DESC, last_seen DESC
         LIMIT {$limit}"
    );
    $stmt->bindValue(':days', $days, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $label = trim((string) ($row['vehicle_label'] ?? ''));
        if ($label === '') {
            continue;
        }
        $rows[] = [
            'car_id' => (int) ($row['car_id'] ?? 0),
            'label' => mb_substr($label, 0, 255, 'UTF-8'),
            'search_count' => (int) ($row['search_count'] ?? 0),
            'last_seen' => (string) ($row['last_seen'] ?? ''),
        ];
    }

    return $rows;
}

/** @return array<int, array<string, mixed>> */
function search_logs_insights_top_queries(PDO $pdo, int $limit, int $days): array
{
    $stmt = $pdo->prepare(
        "SELECT query_type,
                query_value,
                COUNT(*) AS search_count,
                MAX(vehicle_label) AS vehicle_label,
                MAX(car_id) AS car_id,
                MAX(created_at) AS last_seen
         FROM search_logs
         WHERE found = 1
           AND query_value <> ''
           AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
         GROUP BY query_type, query_value
         ORDER BY search_count DESC, last_seen DESC
         LIMIT {$limit}"
    );
    $stmt->bindValue(':days', $days, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $value = trim((string) ($row['query_value'] ?? ''));
        if ($value === '') {
            continue;
        }
        $type = in_array((string) ($row['query_type'] ?? ''), ['vin', 'oem', 'name'], true)
            ? (string) $row['query_type']
            : 'name';
        $rows[] = [
            'query_type' => $type,
            'query_value' => mb_substr($value, 0, 128, 'UTF-8'),
            'search_count' => (int) ($row['search_count'] ?? 0),
            'vehicle_label' => trim((string) ($row['vehicle_label'] ?? '')) ?: null,
            'car_id' => (int) ($row['car_id'] ?? 0) ?: null,
            'last_seen' => (string) ($row['last_seen'] ?? ''),
        ];
    }

    return $rows;
}

/** @return array<int, array<string, mixed>> */
function search_logs_insights_meta_group(PDO $pdo, string $filterKey, int $limit, int $days): array
{
    if (!in_array($filterKey, ['category', 'subcategory', 'marca', 'model', 'motorizare'], true)) {
        return [];
    }

    $jsonPath = '$.filters.' . $filterKey;
    $stmt = $pdo->prepare(
        "SELECT JSON_UNQUOTE(JSON_EXTRACT(meta_json, '{$jsonPath}')) AS label,
                COUNT(*) AS search_count,
                MAX(created_at) AS last_seen
         FROM search_logs
         WHERE found = 1
           AND meta_json IS NOT NULL
           AND JSON_EXTRACT(meta_json, '{$jsonPath}') IS NOT NULL
           AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
         GROUP BY label
         HAVING label IS NOT NULL AND TRIM(label) <> ''
         ORDER BY search_count DESC, last_seen DESC
         LIMIT {$limit}"
    );
    $stmt->bindValue(':days', $days, PDO::PARAM_INT);
    $stmt->execute();

    return search_logs_insights_normalize_label_rows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $filterKey);
}

/** @return array<int, array<string, mixed>> */
function search_logs_insights_vehicle_label_marca(PDO $pdo, int $limit, int $days): array
{
    $stmt = $pdo->prepare(
        "SELECT vehicle_label AS label,
                COUNT(*) AS search_count,
                MAX(created_at) AS last_seen
         FROM search_logs
         WHERE found = 1
           AND vehicle_label IS NOT NULL
           AND TRIM(vehicle_label) <> ''
           AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
         GROUP BY vehicle_label
         ORDER BY search_count DESC, last_seen DESC
         LIMIT {$limit}"
    );
    $stmt->bindValue(':days', $days, PDO::PARAM_INT);
    $stmt->execute();

    return search_logs_insights_normalize_label_rows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'vehicle');
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function search_logs_insights_normalize_label_rows(array $rows, string $kind): array
{
    $result = [];
    foreach ($rows as $row) {
        $label = trim((string) ($row['label'] ?? ''));
        if ($label === '' || mb_strlen($label) > 120) {
            continue;
        }
        $result[] = [
            'label' => $label,
            'kind' => $kind,
            'search_count' => (int) ($row['search_count'] ?? 0),
            'last_seen' => (string) ($row['last_seen'] ?? ''),
        ];
    }

    return $result;
}

/**
 * Boost sortare facete după popularitate în search_logs.
 *
 * @param array<int, array<string, mixed>> $items
 * @param array<int, array<string, mixed>> $insights
 * @return array<int, array<string, mixed>>
 */
function search_logs_boost_facet_items(array $items, array $insights): array
{
    if ($items === [] || $insights === []) {
        return $items;
    }

    $boostMap = [];
    foreach ($insights as $row) {
        if (!is_array($row)) {
            continue;
        }
        $label = trim((string) ($row['label'] ?? ''));
        if ($label === '') {
            continue;
        }
        $key = search_logs_insights_normalize_label($label);
        $boostMap[$key] = max($boostMap[$key] ?? 0, (int) ($row['search_count'] ?? 0));
    }

    if ($boostMap === []) {
        return $items;
    }

    $existing = [];
    foreach ($items as $item) {
        $existing[search_logs_insights_normalize_label((string) ($item['label'] ?? ''))] = true;
    }

    foreach ($insights as $row) {
        if (!is_array($row)) {
            continue;
        }
        $label = trim((string) ($row['label'] ?? ''));
        if ($label === '') {
            continue;
        }
        $key = search_logs_insights_normalize_label($label);
        if (isset($existing[$key])) {
            continue;
        }
        $items[] = [
            'label' => $label,
            'slug' => preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($label, 'UTF-8')) ?: 'item',
            'count' => 0,
            'search_count' => (int) ($row['search_count'] ?? 0),
            'from_search_history' => true,
        ];
        $existing[$key] = true;
    }

    usort($items, static function (array $a, array $b) use ($boostMap): int {
        $aKey = search_logs_insights_normalize_label((string) ($a['label'] ?? ''));
        $bKey = search_logs_insights_normalize_label((string) ($b['label'] ?? ''));
        $aBoost = $boostMap[$aKey] ?? (int) ($a['search_count'] ?? 0);
        $bBoost = $boostMap[$bKey] ?? (int) ($b['search_count'] ?? 0);
        if ($aBoost !== $bBoost) {
            return $bBoost <=> $aBoost;
        }

        return ((int) ($b['count'] ?? 0)) <=> ((int) ($a['count'] ?? 0));
    });

    return $items;
}

function search_logs_insights_normalize_label(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = str_replace(
        ['ă', 'â', 'î', 'ș', 'ş', 'ț', 'ţ'],
        ['a', 'a', 'i', 's', 's', 't', 't'],
        $value
    );

    return preg_replace('/[^a-z0-9]+/', '', $value) ?? $value;
}
