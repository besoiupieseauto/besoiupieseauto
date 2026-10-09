<?php

declare(strict_types=1);

use Config\Database;

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function caiet_pieseauto_fetch(array $payload): array
{
    $account = (int) ($payload['account'] ?? 1);
    $token = caiet_pieseauto_token($account === 2 ? 2 : 1);
    if ($token === '') {
        return [
            'orders' => [],
            'message' => 'Lipsește tokenul pieseauto. Salvează-l la API Credentials, serviciul pieseauto, cheia auth_1 sau auth_2.',
        ];
    }

    $url = 'https://www.pieseauto.ro/ctl.php?a=exportOrders&auth=' . rawurlencode($token);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/141.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json, text/javascript, */*; q=0.01',
            'Referer: https://www.pieseauto.ro/',
            'Origin: https://www.pieseauto.ro',
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    if (!is_string($response) || $httpCode !== 200) {
        throw new RuntimeException('Pieseauto nu a răspuns (HTTP ' . $httpCode . ($curlErr !== '' ? ', ' . $curlErr : '') . ').');
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Răspunsul pieseauto nu este JSON.');
    }
    $orders = $decoded['orders'] ?? $decoded;
    if (!is_array($orders)) {
        $orders = [];
    }

    return ['orders' => array_values($orders)];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function caiet_pieseauto_import(array $payload, int $userId): array
{
    $orders = $payload['orders'] ?? null;
    if (!is_array($orders) || $orders === []) {
        throw new InvalidArgumentException('Nu ai selectat comenzi.');
    }
    if (count($orders) > 100) {
        throw new InvalidArgumentException('Maxim 100 de comenzi odată.');
    }
    $destination = strtolower(trim((string) ($payload['destination'] ?? 'external')));
    $internal = in_array($destination, ['utvin', 'tm'], true);
    $table = $internal ? 'comenzi' : 'comenzi_ext';
    $detailTable = $internal ? 'detaliu' : 'detaliu_ext';
    $location = $destination === 'utvin' ? '2' : '1';
    $pdo = Database::getDB('legacy');
    $createdAt = time() + (2 * 3600);
    $orderDate = date('Y-m-d');
    $imported = [];
    $transportCache = [];

    $pdo->beginTransaction();
    try {
        foreach ($orders as $orderData) {
            if (!is_array($orderData)) {
                continue;
            }
            $clientId = caiet_pieseauto_save_client($pdo, $orderData, $createdAt);
            $items = is_array($orderData['items'] ?? null) ? $orderData['items'] : [];
            $total = 0.0;
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $total += (float) ($item['unit_price_ron'] ?? 0) * (float) ($item['quantity'] ?? 1);
            }
            if ($items === []) {
                $total = 1.0;
            }
            $orderId = (int) $pdo->query('SELECT COALESCE(MAX(idcomanda), 0) + 1 FROM ' . $table)->fetchColumn();
            if ($internal) {
                $stmt = $pdo->prepare(
                    'INSERT INTO comenzi (idcomanda, userid, cont_awb, idclient, idmasina, stare, retur, total, data, locatie_mgz, observations, created_at)
                     VALUES (:id, :uid, :awb, :client, 1, 1, 0, :total, :data, :loc, :obs, :created_at)'
                );
                $stmt->execute([
                    ':id' => $orderId,
                    ':uid' => $userId,
                    ':awb' => (string) ($orderData['courier_awb'] ?? ''),
                    ':client' => $clientId,
                    ':total' => round($total, 2),
                    ':data' => $orderDate,
                    ':loc' => $location,
                    ':obs' => (string) ($orderData['buyer_comments'] ?? ''),
                    ':created_at' => $createdAt,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO comenzi_ext (idcomanda, userid, cont_awb, idclient, idmasina, stare, retur, total, data, observations, created_at)
                     VALUES (:id, :uid, :awb, :client, 1, 1, 0, :total, :data, :obs, :created_at)'
                );
                $stmt->execute([
                    ':id' => $orderId,
                    ':uid' => $userId,
                    ':awb' => (string) ($orderData['courier_awb'] ?? ''),
                    ':client' => $clientId,
                    ':total' => round($total, 2),
                    ':data' => $orderDate,
                    ':obs' => (string) ($orderData['buyer_comments'] ?? ''),
                    ':created_at' => $createdAt,
                ]);
            }

            $lineStmt = $pdo->prepare(
                'INSERT INTO ' . $detailTable . ' (idcomanda, idprodus, cantitate, pret, furnizor, culoare, created_at)
                 VALUES (:order_id, :product_id, :qty, :price, :supplier, :color, :created_at)'
            );
            if ($items !== []) {
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $productId = caiet_pieseauto_product($pdo, $item, $transportCache, $createdAt);
                    $lineStmt->execute([
                        ':order_id' => $orderId,
                        ':product_id' => $productId,
                        ':qty' => max(1, (float) ($item['quantity'] ?? 1)),
                        ':price' => round((float) ($item['unit_price_ron'] ?? 0), 2),
                        ':supplier' => trim((string) ($item['furnizor'] ?? '__')) ?: '__',
                        ':color' => trim((string) ($item['disponibilitate'] ?? 'FFFFFF')) ?: 'FFFFFF',
                        ':created_at' => $createdAt,
                    ]);
                }
            } else {
                $lineStmt->execute([
                    ':order_id' => $orderId,
                    ':product_id' => 66790,
                    ':qty' => 1,
                    ':price' => 0,
                    ':supplier' => '__',
                    ':color' => 'FFFFFF',
                    ':created_at' => $createdAt,
                ]);
            }
            $imported[] = $orderId;
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return [
        'imported' => count($imported),
        'order_ids' => $imported,
        'destination' => $internal ? ($destination === 'utvin' ? 'UTVIN' : 'TM') : 'External',
    ];
}

function caiet_pieseauto_token(int $account): string
{
    $key = $account === 2 ? 'auth_2' : 'auth_1';
    try {
        $stmt = Database::getDB('legacy')->prepare(
            'SELECT data_value FROM api_credentials WHERE service_name = :service AND data_key = :key LIMIT 1'
        );
        $stmt->execute([':service' => 'pieseauto', ':key' => $key]);
        $stored = $stmt->fetchColumn();
        if (is_string($stored) && $stored !== '' && function_exists('caiet_laravel_decrypt')) {
            $plain = trim(caiet_laravel_decrypt($stored));
            if ($plain !== '') {
                return $plain;
            }
        }
    } catch (Throwable $exception) {
        unset($exception);
    }
    $env = getenv('PIESEAUTO_' . strtoupper($key));
    if (is_string($env) && trim($env) !== '') {
        return trim($env);
    }
    $sibling = caiet_pieseauto_sibling_tokens();

    return $sibling[$account === 2 ? 1 : 0] ?? '';
}

/**
 * @return list<string>
 */
function caiet_pieseauto_sibling_tokens(): array
{
    if (!defined('BESOIU_ROOT')) {
        return [];
    }
    $path = dirname((string) BESOIU_ROOT) . '/caietdecomenzi/app/Http/Controllers/PieseautoController.php';
    if (!is_file($path)) {
        return [];
    }
    $text = file_get_contents($path);
    if (!is_string($text)) {
        return [];
    }
    preg_match_all('/exportOrders&auth=([A-Za-z0-9_-]+)/', $text, $matches);

    return array_values($matches[1] ?? []);
}

/**
 * @param array<string, mixed> $orderData
 */
function caiet_pieseauto_save_client(PDO $pdo, array $orderData, int $createdAt): int
{
    $phone = trim((string) ($orderData['buyer_phone'] ?? ''));
    $existing = null;
    if ($phone !== '') {
        $stmt = $pdo->prepare('SELECT * FROM clienti WHERE telefon = :phone LIMIT 1');
        $stmt->execute([':phone' => $phone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $existing = is_array($row) ? $row : null;
    }
    $billing = is_array($orderData['company_billing_data'] ?? null) ? $orderData['company_billing_data'] : [];
    $cuiRaw = trim((string) ($billing['company_cui'] ?? ''));
    $data = [
        'nume' => caiet_pieseauto_keep($orderData['buyer_name'] ?? null, $existing['nume'] ?? ''),
        'telefon' => $phone !== '' ? $phone : (string) ($existing['telefon'] ?? ''),
        'companie' => caiet_pieseauto_keep($billing['company_name'] ?? null, $existing['companie'] ?? ''),
        'regcom' => caiet_pieseauto_keep($billing['company_reg'] ?? null, $existing['regcom'] ?? ''),
        'cif' => caiet_pieseauto_keep($cuiRaw !== '' ? $cuiRaw : null, $existing['cif'] ?? ''),
        'adresa' => (string) ($existing['adresa'] ?? ''),
        'adresa_facturare' => (string) ($existing['adresa_facturare'] ?? ''),
        'idlocalitate' => (int) ($existing['idlocalitate'] ?? 0),
        'localitate_facturare' => (int) ($existing['localitate_facturare'] ?? 0),
    ];
    $shippingCityId = caiet_pieseauto_city(
        $pdo,
        isset($orderData['shipping_city']) ? str_replace('-', ' ', (string) $orderData['shipping_city']) : '',
        isset($orderData['shipping_county']) ? str_replace('-', ' ', (string) $orderData['shipping_county']) : ''
    );
    if ($cuiRaw !== '') {
        $data['adresa'] = caiet_pieseauto_keep($billing['company_address'] ?? null, $data['adresa']);
        $anaf = caiet_pieseauto_anaf(preg_replace('/\D/', '', $cuiRaw) ?: '');
        $found = is_array($anaf['found'][0] ?? null) ? $anaf['found'][0] : [];
        $postal = caiet_pieseauto_ascii((string) ($found['adresa_sediu_social']['scod_Postal'] ?? ''));
        if ($postal !== '') {
            $postalCity = caiet_pieseauto_city_by_postal($pdo, $postal);
            if ($postalCity > 0) {
                $data['idlocalitate'] = $postalCity;
            }
        }
        $general = is_array($found['date_generale'] ?? null) ? $found['date_generale'] : [];
        $data['regcom'] = caiet_pieseauto_keep($general['nrRegCom'] ?? null, $data['regcom']);
        $data['companie'] = caiet_pieseauto_keep($general['denumire'] ?? null, $data['companie']);
        $data['adresa'] = caiet_pieseauto_keep($general['adresa'] ?? null, $data['adresa']);
        $data['adresa_facturare'] = caiet_pieseauto_keep($orderData['shipping_address'] ?? null, $data['adresa_facturare']);
        if ($shippingCityId > 0) {
            $data['localitate_facturare'] = $shippingCityId;
        }
    } else {
        $data['adresa'] = caiet_pieseauto_keep($orderData['shipping_address'] ?? null, $data['adresa']);
        if ($shippingCityId > 0) {
            $data['idlocalitate'] = $shippingCityId;
        }
    }
    if ($data['nume'] === '') {
        $data['nume'] = 'Client pieseauto';
    }

    if ($existing) {
        $update = $pdo->prepare(
            'UPDATE clienti SET nume = :nume, telefon = :telefon, adresa = :adresa, companie = :companie, cif = :cif,
             regcom = :regcom, idlocalitate = :idlocalitate, adresa_facturare = :adresa_facturare,
             localitate_facturare = :localitate_facturare WHERE idclienti = :id'
        );
        $update->execute([
            ':id' => (int) $existing['idclienti'],
            ':nume' => $data['nume'],
            ':telefon' => $data['telefon'] !== '' ? $data['telefon'] : null,
            ':adresa' => $data['adresa'] !== '' ? $data['adresa'] : null,
            ':companie' => $data['companie'] !== '' ? $data['companie'] : null,
            ':cif' => $data['cif'] !== '' ? $data['cif'] : null,
            ':regcom' => $data['regcom'] !== '' ? $data['regcom'] : null,
            ':idlocalitate' => $data['idlocalitate'] > 0 ? $data['idlocalitate'] : null,
            ':adresa_facturare' => $data['adresa_facturare'] !== '' ? $data['adresa_facturare'] : null,
            ':localitate_facturare' => $data['localitate_facturare'] > 0 ? $data['localitate_facturare'] : null,
        ]);

        return (int) $existing['idclienti'];
    }

    $id = (int) $pdo->query('SELECT COALESCE(MAX(idclienti), 0) + 1 FROM clienti')->fetchColumn();
    $insert = $pdo->prepare(
        'INSERT INTO clienti (idclienti, nume, telefon, adresa, companie, cif, regcom, idlocalitate, adresa_facturare, localitate_facturare, created_at)
         VALUES (:id, :nume, :telefon, :adresa, :companie, :cif, :regcom, :idlocalitate, :adresa_facturare, :localitate_facturare, :created_at)'
    );
    $insert->execute([
        ':id' => $id,
        ':nume' => $data['nume'],
        ':telefon' => $data['telefon'] !== '' ? $data['telefon'] : null,
        ':adresa' => $data['adresa'] !== '' ? $data['adresa'] : null,
        ':companie' => $data['companie'] !== '' ? $data['companie'] : null,
        ':cif' => $data['cif'] !== '' ? $data['cif'] : null,
        ':regcom' => $data['regcom'] !== '' ? $data['regcom'] : null,
        ':idlocalitate' => $data['idlocalitate'] > 0 ? $data['idlocalitate'] : null,
        ':adresa_facturare' => $data['adresa_facturare'] !== '' ? $data['adresa_facturare'] : null,
        ':localitate_facturare' => $data['localitate_facturare'] > 0 ? $data['localitate_facturare'] : null,
        ':created_at' => $createdAt,
    ]);

    return $id;
}

function caiet_pieseauto_city(PDO $pdo, string $city, string $county): int
{
    $city = trim($city);
    $county = trim($county);
    if ($city === '') {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT idlocatie FROM localitati WHERE localitate = :city AND judet = :county LIMIT 1');
    $stmt->execute([':city' => $city, ':county' => $county]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    $id = (int) $pdo->query('SELECT COALESCE(MAX(idlocatie), 0) + 1 FROM localitati')->fetchColumn();
    $insert = $pdo->prepare('INSERT INTO localitati (idlocatie, localitate, judet, codrutare) VALUES (:id, :city, :county, 4000)');
    $insert->execute([
        ':id' => $id,
        ':city' => $city,
        ':county' => $county !== '' ? ucfirst(strtolower($county)) : '',
    ]);

    return $id;
}

function caiet_pieseauto_city_by_postal(PDO $pdo, string $postal): int
{
    $withZero = str_starts_with($postal, '0') ? $postal : '0' . $postal;
    $stmt = $pdo->prepare('SELECT localitate, judet FROM coduri_postale WHERE cod IN (:a, :b) LIMIT 1');
    $stmt->execute([':a' => $postal, ':b' => $withZero]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return 0;
    }
    $city = str_replace('-', ' ', caiet_pieseauto_ascii((string) ($row['localitate'] ?? '')));
    $county = str_replace('-', ' ', caiet_pieseauto_ascii((string) ($row['judet'] ?? '')));

    return caiet_pieseauto_city($pdo, $city, $county);
}

/**
 * @return array<string, mixed>
 */
function caiet_pieseauto_anaf(string $cui): array
{
    if ($cui === '') {
        return [];
    }
    $ch = curl_init('https://webservicesp.anaf.ro/api/PlatitorTvaRest/v9/tva');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([['cui' => (int) $cui, 'data' => date('Y-m-d')]], JSON_UNESCAPED_UNICODE),
    ]);
    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($response) || $code !== 200) {
        return [];
    }
    $decoded = json_decode($response, true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * @param array<string, mixed> $item
 * @param array<string, int> $transportCache
 */
function caiet_pieseauto_product(PDO $pdo, array $item, array &$transportCache, int $createdAt): int
{
    $title = trim((string) ($item['title'] ?? ''));
    $price = round((float) ($item['unit_price_ron'] ?? 0), 2);
    $isTransport = preg_match('/transport|courier|delivery/i', $title) === 1;
    if ($isTransport) {
        $cacheKey = strtolower($title) . '|' . $price;
        if (isset($transportCache[$cacheKey])) {
            return $transportCache[$cacheKey];
        }
        $stmt = $pdo->prepare('SELECT idprodus FROM produse WHERE denumire = :name AND pret = :price LIMIT 1');
        $stmt->execute([':name' => $title !== '' ? $title : 'Transport', ':price' => $price]);
        $found = $stmt->fetchColumn();
        if ($found !== false) {
            $transportCache[$cacheKey] = (int) $found;

            return (int) $found;
        }
    } else {
        $code = trim((string) ($item['product_id'] ?? ''));
        if ($code !== '') {
            $stmt = $pdo->prepare('SELECT idprodus FROM produse WHERE cod_produs = :code LIMIT 1');
            $stmt->execute([':code' => $code]);
            $found = $stmt->fetchColumn();
            if ($found !== false) {
                return (int) $found;
            }
        }
    }
    $id = (int) $pdo->query('SELECT COALESCE(MAX(idprodus), 0) + 1 FROM produse')->fetchColumn();
    $insert = $pdo->prepare(
        'INSERT INTO produse (idprodus, denumire, cod_produs, pret, created_at) VALUES (:id, :name, :code, :price, :created_at)'
    );
    $insert->execute([
        ':id' => $id,
        ':name' => $title !== '' ? $title : ($isTransport ? 'Transport' : 'Produs pieseauto'),
        ':code' => $isTransport ? null : (trim((string) ($item['product_id'] ?? '')) ?: null),
        ':price' => $price,
        ':created_at' => $createdAt,
    ]);
    if ($isTransport) {
        $transportCache[strtolower($title) . '|' . $price] = $id;
    }

    return $id;
}

function caiet_pieseauto_keep(mixed $incoming, string $current): string
{
    $value = trim((string) ($incoming ?? ''));

    return $value !== '' ? $value : $current;
}

function caiet_pieseauto_ascii(string $value): string
{
    $value = strtr($value, [
        'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
        'Ă' => 'A', 'Â' => 'A', 'Î' => 'I', 'Ș' => 'S', 'Ş' => 'S', 'Ț' => 'T', 'Ţ' => 'T',
    ]);
    $out = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

    return is_string($out) && $out !== '' ? $out : $value;
}
