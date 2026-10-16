<?php

declare(strict_types=1);

use Besoiu\Core\Supplier\SupplierHooks;
use Besoiu\Modules\SupplierSearch\Service\SupplierSearchConfig;
use Config\Database;

/**
 * Paritate cu caietdecomenzi (Laravel): SMS, WhatsApp, AWB, facturi, linii, utilizatori, wishlist, promoții, LKQ, plasare furnizor.
 */

function caiet_parity_pdo(): PDO
{
    return Database::getDB('legacy');
}

function caiet_cred(string $service, string $key): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            if (function_exists('caiet_laravel_decrypt')) {
                $stmt = caiet_parity_pdo()->query('SELECT service_name, data_key, data_value FROM api_credentials');
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $cache[(string) $row['service_name'] . '.' . (string) $row['data_key']] = caiet_laravel_decrypt((string) ($row['data_value'] ?? ''));
                }
            }
        } catch (Throwable) {
            $cache = [];
        }
    }
    $found = trim((string) ($cache[$service . '.' . $key] ?? ''));
    if ($found !== '') {
        return $found;
    }
    $env = strtoupper($service . '_' . $key);

    return trim((string) ($_ENV[$env] ?? getenv($env) ?: ''));
}

function caiet_env(string $key, string $fallback = ''): string
{
    $value = trim((string) ($_ENV[$key] ?? getenv($key) ?: ''));

    return $value !== '' ? $value : $fallback;
}

/**
 * @return array{status:int,body:string,json:?array<string,mixed>}
 */
function caiet_http(string $method, string $url, ?string $body, array $headers): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        throw new InvalidArgumentException('cURL nu este disponibil.');
    }
    $options = [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $options);
    if (class_exists(SupplierSearchConfig::class)) {
        SupplierSearchConfig::configureCurl($ch);
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = trim((string) curl_error($ch));
    curl_close($ch);
    if ($raw === false) {
        throw new InvalidArgumentException($error !== '' ? $error : 'Cererea HTTP a eșuat.');
    }
    $decoded = json_decode((string) $raw, true);

    return ['status' => $status, 'body' => (string) $raw, 'json' => is_array($decoded) ? $decoded : null];
}

function caiet_template(string $channel, string $code): string
{
    $defaults = [
        'whatsapp.order_pickup' => "Bună ziua {{client_name}},\n\nComanda dvs. a ajuns la magazinul din {{store_name}} și poate fi ridicată.\n\nTotal de plată: {{total}} RON\nLocația noastră: {{store_url}}\nContact: 0726 498 573 / 0767 164 728\nProgram: Luni–Vineri 9:00–17:00, Sâmbătă 9:00–14:00\n\nEchipa Besoiu Piese Auto",
        'whatsapp.external_order_shipped' => "Bună ziua {{client_name}},\n\nComanda dvs. a fost expediată prin {{courier_name}}.\nTotal de plată (inclusiv transport): {{total}} RON\nNumăr AWB: {{awb}}\nUrmăriți coletul: {{awb_url}}\n\nEchipa Besoiu Piese Auto",
        'whatsapp.wishlist_offer' => "Bună ziua,\n\nOferta pentru piesele solicitate.\nVIN: {{vin}}\n\n{{product_lines}}\n\nTOTAL = {{total}} RON\n\nBesoiu Piese Auto",
        'sms.order_pickup_with_total' => 'Buna ziua {{client_name}}, comanda a sosit la magazinul din {{store_name}}. Total: {{total}} RON. Locatie: {{store_url}}. Tel: 0726498573',
        'sms.order_pickup_no_total' => 'Buna ziua {{client_name}}, comanda a sosit la magazinul din {{store_name}}. Locatie: {{store_url}}. Tel: 0726498573',
        'sms.external_order_sameday' => 'Buna ziua, comanda a fost trimisa prin Sameday. Total: {{total}} RON. Track: https://sameday.ro/#awb={{awb}}',
        'sms.external_order_fancourier' => 'Buna ziua, comanda a fost trimisa prin FanCourier. Total: {{total}} RON. Track: http://www.fancourier.ro/awb-tracking/?tracking={{awb}}',
    ];
    try {
        $stmt = caiet_parity_pdo()->prepare('SELECT template FROM message_templates WHERE channel = :channel AND code = :code LIMIT 1');
        $stmt->execute([':channel' => $channel, ':code' => $code]);
        $text = trim((string) $stmt->fetchColumn());
        if ($text !== '') {
            return $text;
        }
    } catch (Throwable) {
    }

    return $defaults[$channel . '.' . $code] ?? '';
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function caiet_sms_prepare(array $payload): array
{
    $source = caiet_comenzi_source($payload);
    $id = caiet_comenzi_id($payload, 'order_id');
    $pdo = caiet_parity_pdo();
    if ($source === 'externa') {
        $stmt = $pdo->prepare('SELECT c.total, c.awb, c.cont_awb, c.stare, cl.nume, cl.telefon FROM comenzi_ext c JOIN clienti cl ON cl.idclienti = c.idclient WHERE c.idcomanda = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new InvalidArgumentException('Comanda externă nu există.');
        }
        $code = strtolower((string) ($row['cont_awb'] ?? '')) === 'same' ? 'external_order_sameday' : 'external_order_fancourier';
        $message = strtr(caiet_template('sms', $code), [
            '{{total}}' => number_format((float) $row['total'], 2, '.', ''),
            '{{awb}}' => (string) ($row['awb'] ?? ''),
        ]);
    } else {
        $stmt = $pdo->prepare('SELECT c.total, c.stare, c.locatie_mgz, cl.nume, cl.telefon FROM comenzi c JOIN clienti cl ON cl.idclienti = c.idclient WHERE c.idcomanda = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new InvalidArgumentException('Comanda internă nu există.');
        }
        $store = (int) ($row['locatie_mgz'] ?? 1) === 1 ? 'Timisoara' : 'Utvin';
        $url = (int) ($row['locatie_mgz'] ?? 1) === 1 ? 'https://tinyurl.com/BesoiuPieseAutoTimisoara' : 'https://tinyurl.com/BesoiuPieseAutoUtvin';
        $code = (int) ($row['stare'] ?? 0) === 4 ? 'order_pickup_no_total' : 'order_pickup_with_total';
        $message = strtr(caiet_template('sms', $code), [
            '{{client_name}}' => (string) $row['nume'],
            '{{store_name}}' => $store,
            '{{total}}' => number_format((float) $row['total'], 2, '.', ''),
            '{{store_url}}' => $url,
        ]);
    }

    return [
        'client_name' => (string) ($row['nume'] ?? ''),
        'client_phone' => (string) ($row['telefon'] ?? ''),
        'default_message' => $message,
    ];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function caiet_sms_send(array $payload): array
{
    $source = caiet_comenzi_source($payload);
    $orderId = caiet_comenzi_id($payload, 'order_id');
    $phone = preg_replace('/\s+/', '', (string) ($payload['phone'] ?? '')) ?? '';
    $phone = str_replace(['+', '-', '(', ')', '.'], '', $phone);
    $message = trim((string) ($payload['message'] ?? ''));
    if ($phone === '' || strlen($phone) < 10 || $message === '') {
        throw new InvalidArgumentException('Telefonul sau mesajul SMS este invalid.');
    }
    if (!str_starts_with($phone, '4')) {
        $phone = '+4' . ltrim($phone, '0');
    } else {
        $phone = '+' . $phone;
    }
    $url = caiet_cred('sms', 'api_url');
    $key = caiet_cred('sms', 'api_key');
    if ($url === '' || $key === '') {
        $url = caiet_env('SMS_API_URL', 'https://api.smsapi.ro/sms.do');
        $key = caiet_env('SMS_API_KEY');
    }
    if ($url === '' || $key === '') {
        throw new InvalidArgumentException('Credențialele SMS nu sunt configurate în API Credentials.');
    }
    $body = json_encode(['from' => '3737', 'to' => $phone, 'message' => $message], JSON_UNESCAPED_UNICODE);
    $response = caiet_http('POST', $url, $body === false ? '{}' : $body, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key,
    ]);
    $text = trim($response['body']);
    if (!str_starts_with($text, 'OK:')) {
        $error = str_starts_with($text, 'ERROR:') ? substr($text, 6) : ($text !== '' ? $text : 'HTTP ' . $response['status']);
        throw new InvalidArgumentException('Eroare la trimiterea SMS-ului (' . $error . ').');
    }
    $parts = explode(':', $text);
    $pdo = caiet_parity_pdo();
    $stmt = $pdo->prepare('INSERT INTO sms (status, idcomanda, idcomanda_ext, idprimit, cost, data_exp) VALUES (:status, :intern, :extern, :idprimit, :cost, :data_exp)');
    $stmt->execute([
        ':status' => 'Trimis',
        ':intern' => $source === 'interna' ? $orderId : 0,
        ':extern' => $source === 'externa' ? $orderId : 0,
        ':idprimit' => $parts[1] ?? '',
        ':cost' => $parts[2] ?? '0',
        ':data_exp' => date('Y-m-d H:i:s'),
    ]);

    return ['message' => 'SMS trimis cu succes', 'id' => $parts[1] ?? ''];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function caiet_whatsapp(array $payload): array
{
    $source = caiet_comenzi_source($payload);
    $id = caiet_comenzi_id($payload, 'order_id');
    $pdo = caiet_parity_pdo();
    if ($source === 'externa') {
        $stmt = $pdo->prepare('SELECT c.total, c.awb, c.cont_awb, cl.nume, cl.telefon FROM comenzi_ext c JOIN clienti cl ON cl.idclienti = c.idclient WHERE c.idcomanda = :id LIMIT 1');
    } else {
        $stmt = $pdo->prepare('SELECT c.total, c.stare, c.locatie_mgz, cl.nume, cl.telefon FROM comenzi c JOIN clienti cl ON cl.idclienti = c.idclient WHERE c.idcomanda = :id LIMIT 1');
    }
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new InvalidArgumentException('Comanda nu există.');
    }
    $phone = preg_replace('/\D+/', '', (string) ($row['telefon'] ?? '')) ?? '';
    if ($phone === '') {
        throw new InvalidArgumentException('Clientul nu are telefon.');
    }
    if (str_starts_with($phone, '0')) {
        $phone = '40' . substr($phone, 1);
    }
    if (!str_starts_with($phone, '40')) {
        $phone = '40' . $phone;
    }
    if ($source === 'externa') {
        $courier = strtolower((string) ($row['cont_awb'] ?? '')) === 'same' ? 'Sameday' : 'FanCourier';
        $awb = (string) ($row['awb'] ?? '');
        $track = $courier === 'Sameday'
            ? 'https://sameday.ro/#awb=' . rawurlencode($awb)
            : 'https://www.fancourier.ro/awb-tracking/?tracking=' . rawurlencode($awb);
        $text = strtr(caiet_template('whatsapp', 'external_order_shipped'), [
            '{{client_name}}' => (string) $row['nume'],
            '{{courier_name}}' => $courier,
            '{{total}}' => number_format((float) $row['total'], 2, '.', ''),
            '{{awb}}' => $awb,
            '{{awb_url}}' => $track,
        ]);
        try {
            $pdo->prepare('UPDATE comenzi_ext SET whatsapp_sent = 1, whatsapp_sent_at = :at WHERE idcomanda = :id')->execute([':at' => date('Y-m-d H:i:s'), ':id' => $id]);
        } catch (Throwable) {
        }
    } else {
        $loc = (int) ($row['locatie_mgz'] ?? 1);
        $total = (int) ($row['stare'] ?? 0) === 4 ? 0 : (float) $row['total'];
        $text = strtr(caiet_template('whatsapp', 'order_pickup'), [
            '{{client_name}}' => (string) $row['nume'],
            '{{store_name}}' => $loc === 1 ? 'Timisoara' : 'Utvin',
            '{{total}}' => number_format($total, 2, '.', ''),
            '{{store_url}}' => $loc === 1 ? 'https://tinyurl.com/BesoiuPieseAutoTimisoara' : 'https://tinyurl.com/BesoiuPieseAutoUtvin',
        ]);
        try {
            $pdo->prepare('UPDATE comenzi SET whatsapp_sent = 1, whatsapp_sent_at = :at WHERE idcomanda = :id')->execute([':at' => date('Y-m-d H:i:s'), ':id' => $id]);
        } catch (Throwable) {
        }
    }

    return ['url' => 'https://web.whatsapp.com/send/?phone=' . $phone . '&text=' . rawurlencode($text)];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function caiet_awb_form(array $payload): array
{
    $id = caiet_comenzi_id($payload, 'order_id');
    $pdo = caiet_parity_pdo();
    $stmt = $pdo->prepare(
        'SELECT c.idcomanda, c.total, c.awb, cl.nume, cl.telefon, cl.adresa, loc.judet, loc.localitate
         FROM comenzi_ext c
         JOIN clienti cl ON cl.idclienti = c.idclient
         LEFT JOIN localitati loc ON loc.idlocatie = cl.idlocalitate
         WHERE c.idcomanda = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new InvalidArgumentException('Comanda externă nu există.');
    }
    $counties = $pdo->query('SELECT DISTINCT judet FROM localitati WHERE judet IS NOT NULL AND judet <> \'\' ORDER BY judet')->fetchAll(PDO::FETCH_COLUMN);

    return ['order' => $row, 'counties' => $counties];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function caiet_awb_create(array $payload): array
{
    $id = caiet_comenzi_id($payload, 'order_id');
    $courier = strtolower(trim((string) ($payload['courier'] ?? 'fancourier')));
    $awb = $courier === 'sameday' ? caiet_sameday_awb($payload) : caiet_fancourier_awb($payload);
    $account = $courier === 'sameday' ? 'same' : trim((string) ($payload['contfan'] ?? 'Utvin'));
    $stmt = caiet_parity_pdo()->prepare('UPDATE comenzi_ext SET awb = :awb, cont_awb = :cont, updated_at = :at WHERE idcomanda = :id');
    $stmt->execute([':awb' => $awb, ':cont' => $account, ':at' => date('Y-m-d H:i:s'), ':id' => $id]);

    return ['awb_number' => $awb, 'message' => 'AWB generat cu succes'];
}

/**
 * @param array<string, mixed> $payload
 */
function caiet_fancourier_awb(array $payload): string
{
    $username = caiet_cred('fancourier', 'username');
    $password = caiet_cred('fancourier', 'password');
    $clientId = caiet_cred('fancourier', 'client_id');
    if ($username === '') {
        $username = caiet_env('FANCOURIER_USERNAME');
    }
    if ($password === '') {
        $password = caiet_env('FANCOURIER_PASSWORD');
    }
    if ($clientId === '') {
        $clientId = caiet_env('FANCOURIER_CLIENT_ID');
    }
    if ($username === '' || $password === '' || $clientId === '') {
        throw new InvalidArgumentException('FanCourier nu este configurat (username, password, client_id).');
    }
    $base = rtrim(caiet_env('FANCOURIER_API_URL', 'https://api.fancourier.ro'), '/');
    $login = caiet_http('POST', $base . '/login?' . http_build_query(['username' => $username, 'password' => $password]), null, ['Accept: application/json']);
    $token = (string) ($login['json']['data']['token'] ?? '');
    if ($token === '') {
        throw new InvalidArgumentException('Autentificare FanCourier eșuată.');
    }
    $service = trim((string) ($payload['tipserviciu'] ?? 'Standard'));
    $cod = (float) ($payload['ramburs'] ?? 0);
    if (str_contains(strtolower($service), 'cont colector')) {
        $cod = max(1, $cod);
    }
    $options = [];
    if (!empty($payload['deschidere'])) {
        $options[] = 'A';
    }
    if (!empty($payload['sambata'])) {
        $options[] = 'S';
    }
    $body = [
        'clientId' => (int) $clientId,
        'shipments' => [[
            'info' => [
                'service' => $service,
                'packages' => ['parcel' => max(1, (int) ($payload['colete'] ?? 1)), 'envelope' => max(0, (int) ($payload['plicuri'] ?? 0))],
                'weight' => max(0.1, (float) ($payload['greutate'] ?? 1)),
                'cod' => $cod,
                'payment' => 'sender',
                'content' => 'Piese auto',
                'observation' => trim((string) ($payload['observatii'] ?? 'Piese auto')),
                'options' => $options === [] ? ['X'] : $options,
            ],
            'recipient' => [
                'name' => trim((string) ($payload['nume'] ?? 'Destinatar')),
                'phone' => trim((string) ($payload['telefon'] ?? '')),
                'address' => [
                    'county' => trim((string) ($payload['judet'] ?? '')),
                    'locality' => trim((string) ($payload['localitate'] ?? '')),
                    'street' => trim((string) ($payload['adresa'] ?? '')),
                    'streetNo' => '1',
                    'zipCode' => '300000',
                ],
            ],
        ]],
    ];
    $json = json_encode($body, JSON_UNESCAPED_UNICODE);
    $response = caiet_http('POST', $base . '/intern-awb', $json === false ? '{}' : $json, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
        'Content-Type: application/json',
    ]);
    $awb = (string) ($response['json']['response'][0]['awbNumber'] ?? $response['json']['data']['awbNumber'] ?? '');
    if ($awb === '' && isset($response['json']['response'][0]['errors'])) {
        throw new InvalidArgumentException('FanCourier: ' . json_encode($response['json']['response'][0]['errors'], JSON_UNESCAPED_UNICODE));
    }
    if ($awb === '') {
        throw new InvalidArgumentException('FanCourier nu a returnat AWB.');
    }

    return $awb;
}

/**
 * @param array<string, mixed> $payload
 */
function caiet_sameday_awb(array $payload): string
{
    $username = caiet_cred('sameday', 'username') ?: caiet_env('SAMEDAY_USERNAME');
    $password = caiet_cred('sameday', 'password') ?: caiet_env('SAMEDAY_PASSWORD');
    $pickup = (int) (caiet_cred('sameday', 'pickup_point_id') ?: caiet_env('SAMEDAY_PICKUP_POINT_ID', '0'));
    $serviceId = (int) (caiet_cred('sameday', 'service_id') ?: caiet_env('SAMEDAY_SERVICE_ID', '7'));
    if ($username === '' || $password === '' || $pickup <= 0) {
        throw new InvalidArgumentException('Sameday nu este configurat (username, password, pickup_point_id).');
    }
    $base = rtrim(caiet_env('SAMEDAY_API_URL', 'https://api.sameday.ro'), '/');
    $authBody = json_encode(['username' => $username, 'password' => $password]);
    $auth = caiet_http('POST', $base . '/api/authenticate', $authBody === false ? '{}' : $authBody, ['Content-Type: application/json', 'Accept: application/json', 'X-Auth-Username: ' . $username, 'X-Auth-Password: ' . $password]);
    $token = (string) ($auth['json']['token'] ?? '');
    if ($token === '') {
        throw new InvalidArgumentException('Autentificare Sameday eșuată.');
    }
    $body = [
        'pickupPoint' => $pickup,
        'packageType' => 0,
        'packageNumber' => max(1, (int) ($payload['colete'] ?? 1)),
        'packageWeight' => max(0.1, (float) ($payload['greutate'] ?? 1)),
        'service' => $serviceId,
        'awbPayment' => 1,
        'cashOnDelivery' => max(0, (float) ($payload['ramburs'] ?? 0)),
        'insuredValue' => 0,
        'thirdPartyPickup' => 0,
        'awbRecipient' => [
            'name' => trim((string) ($payload['nume'] ?? 'Destinatar')),
            'phoneNumber' => trim((string) ($payload['telefon'] ?? '')),
            'personType' => 0,
            'postalCode' => '300000',
            'address' => trim((string) ($payload['adresa'] ?? '')),
            'countyString' => trim((string) ($payload['judet'] ?? '')),
            'cityString' => trim((string) ($payload['localitate'] ?? '')),
        ],
        'parcels' => [['weight' => max(0.1, (float) ($payload['greutate'] ?? 1))]],
    ];
    $json = json_encode($body, JSON_UNESCAPED_UNICODE);
    $response = caiet_http('POST', $base . '/api/awb', $json === false ? '{}' : $json, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
        'Content-Type: application/json',
    ]);
    $awb = (string) ($response['json']['awbNumber'] ?? '');
    if ($awb === '') {
        throw new InvalidArgumentException('Sameday nu a returnat AWB. ' . substr($response['body'], 0, 240));
    }

    return $awb;
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function caiet_awb_update(array $payload): array
{
    $id = caiet_comenzi_id($payload, 'order_id');
    $awb = trim((string) ($payload['awb'] ?? ''));
    if ($awb === '') {
        throw new InvalidArgumentException('AWB-ul este gol.');
    }
    $stmt = caiet_parity_pdo()->prepare('UPDATE comenzi_ext SET awb = :awb, updated_at = :at WHERE idcomanda = :id');
    $stmt->execute([':awb' => $awb, ':at' => date('Y-m-d H:i:s'), ':id' => $id]);

    return ['awb' => $awb];
}

/**
 * @param array<string, mixed> $payload
 * @return array{filename:string,pdf_base64:string}
 */
function caiet_awb_label(array $payload): array
{
    $awb = trim((string) ($payload['awb'] ?? ''));
    if ($awb === '' || $awb === '___') {
        throw new InvalidArgumentException('AWB-ul lipsește.');
    }
    if (preg_match('/^1ONB/i', $awb) === 1) {
        $pdf = caiet_sameday_label($awb);
    } else {
        $pdf = caiet_fancourier_label($awb);
    }

    return ['filename' => 'awb-' . $awb . '.pdf', 'pdf_base64' => base64_encode($pdf)];
}

function caiet_fancourier_label(string $awb): string
{
    $username = caiet_cred('fancourier', 'username') ?: caiet_env('FANCOURIER_USERNAME');
    $password = caiet_cred('fancourier', 'password') ?: caiet_env('FANCOURIER_PASSWORD');
    $clientId = caiet_cred('fancourier', 'client_id') ?: caiet_env('FANCOURIER_CLIENT_ID');
    if ($username === '' || $password === '' || $clientId === '') {
        throw new InvalidArgumentException('FanCourier nu este configurat.');
    }
    $base = rtrim(caiet_env('FANCOURIER_API_URL', 'https://api.fancourier.ro'), '/');
    $login = caiet_http('POST', $base . '/login?' . http_build_query(['username' => $username, 'password' => $password]), null, ['Accept: application/json']);
    $token = (string) ($login['json']['data']['token'] ?? '');
    if ($token === '') {
        throw new InvalidArgumentException('Autentificare FanCourier eșuată.');
    }
    $url = $base . '/awb/label?' . http_build_query(['clientId' => $clientId, 'pdf' => 1, 'language' => 'ro']) . '&awbs[]=' . rawurlencode($awb);
    $response = caiet_http('GET', $url, null, ['Authorization: Bearer ' . $token, 'Accept: application/pdf']);
    if ($response['status'] >= 400 || !str_starts_with($response['body'], '%PDF')) {
        throw new InvalidArgumentException('FanCourier nu a returnat PDF-ul AWB.');
    }

    return $response['body'];
}

function caiet_sameday_token(): string
{
    $username = caiet_cred('sameday', 'username') ?: caiet_env('SAMEDAY_USERNAME');
    $password = caiet_cred('sameday', 'password') ?: caiet_env('SAMEDAY_PASSWORD');
    if ($username === '' || $password === '') {
        return '';
    }
    $base = rtrim(caiet_env('SAMEDAY_API_URL', 'https://api.sameday.ro'), '/');
    $authBody = json_encode(['username' => $username, 'password' => $password]);
    $auth = caiet_http('POST', $base . '/api/authenticate', $authBody === false ? '{}' : $authBody, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Auth-Username: ' . $username,
        'X-Auth-Password: ' . $password,
    ]);

    return (string) ($auth['json']['token'] ?? '');
}

function caiet_sameday_label(string $awb): string
{
    $token = caiet_sameday_token();
    if ($token === '') {
        throw new InvalidArgumentException('Sameday nu este configurat.');
    }
    $base = rtrim(caiet_env('SAMEDAY_API_URL', 'https://api.sameday.ro'), '/');
    $response = caiet_http('GET', $base . '/api/awb/download/' . rawurlencode($awb) . '/pdf', null, [
        'Authorization: Bearer ' . $token,
        'Accept: application/pdf',
    ]);
    if ($response['status'] >= 400 || !str_starts_with($response['body'], '%PDF')) {
        throw new InvalidArgumentException('Sameday nu a returnat PDF-ul AWB.');
    }

    return $response['body'];
}

/**
 * @return array<string, int>
 */
function caiet_awb_track(int $limit): array
{
    $pdo = caiet_parity_pdo();
    $stmt = $pdo->query(
        "SELECT idcomanda, awb FROM comenzi_ext
         WHERE awb IS NOT NULL AND awb <> '' AND awb <> '___'
           AND (courier_status IS NULL OR courier_status NOT LIKE 'Livrat%')
         ORDER BY idcomanda DESC LIMIT " . max(1, min(200, $limit))
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $updated = 0;
    $username = caiet_cred('fancourier', 'username') ?: caiet_env('FANCOURIER_USERNAME');
    $password = caiet_cred('fancourier', 'password') ?: caiet_env('FANCOURIER_PASSWORD');
    $token = '';
    if ($username !== '' && $password !== '') {
        $base = rtrim(caiet_env('FANCOURIER_API_URL', 'https://api.fancourier.ro'), '/');
        $login = caiet_http('POST', $base . '/login?' . http_build_query(['username' => $username, 'password' => $password]), null, ['Accept: application/json']);
        $token = (string) ($login['json']['data']['token'] ?? '');
    }
    $samedayToken = '';
    foreach ($rows as $row) {
        $awb = (string) $row['awb'];
        if (preg_match('/^1ONB/i', $awb) === 1) {
            if ($samedayToken === '') {
                $samedayToken = caiet_sameday_token();
            }
            if ($samedayToken === '') {
                continue;
            }
            $sameBase = rtrim(caiet_env('SAMEDAY_API_URL', 'https://api.sameday.ro'), '/');
            $track = caiet_http('GET', $sameBase . '/api/client/awb/' . rawurlencode($awb) . '/status', null, ['Authorization: Bearer ' . $samedayToken, 'Accept: application/json']);
            $name = (string) ($track['json']['expeditionStatus']['statusLabel'] ?? $track['json']['status'] ?? '');
            if ($name === '') {
                continue;
            }
            $stareSql = str_contains(strtolower($name), 'livrat') ? ', stare = 4' : '';
            $pdo->prepare('UPDATE comenzi_ext SET courier_status = :status' . $stareSql . ' WHERE idcomanda = :id')->execute([':status' => $name, ':id' => (int) $row['idcomanda']]);
            $updated++;
            continue;
        }
        if (!ctype_digit($awb) || $token === '') {
            continue;
        }
        $base = rtrim(caiet_env('FANCOURIER_API_URL', 'https://api.fancourier.ro'), '/');
        $track = caiet_http('GET', $base . '/reports/awb/tracking?awb=' . rawurlencode($awb) . '&language=ro', null, ['Authorization: Bearer ' . $token, 'Accept: application/json']);
        $events = $track['json']['data']['events'] ?? $track['json']['events'] ?? [];
        if (!is_array($events) || $events === []) {
            continue;
        }
        $last = end($events);
        if (!is_array($last)) {
            continue;
        }
        $name = (string) ($last['name'] ?? '');
        $status = trim($name . ' ' . (string) ($last['location'] ?? '') . ' ' . (string) ($last['date'] ?? ''));
        $stareSql = '';
        $params = [':status' => $status, ':id' => (int) $row['idcomanda']];
        if ($name === 'Livrat') {
            $stareSql = ', stare = 4';
        } elseif ($name === 'Retur') {
            $stareSql = ', stare = 6';
        }
        $update = $pdo->prepare('UPDATE comenzi_ext SET courier_status = :status' . $stareSql . ' WHERE idcomanda = :id');
        $update->execute($params);
        $updated++;
    }

    return ['checked' => count($rows), 'updated' => $updated];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function caiet_line_save(array $payload): array
{
    $source = caiet_comenzi_source($payload);
    $orderId = caiet_comenzi_id($payload, 'order_id');
    $productId = (int) ($payload['idprodus'] ?? 0);
    $qty = (float) ($payload['cantitate'] ?? 1);
    $price = round((float) ($payload['pret'] ?? 0), 2);
    $supplier = trim((string) ($payload['furnizor'] ?? '__'));
    if ($productId <= 0 || $qty == 0.0) {
        throw new InvalidArgumentException('Produsul sau cantitatea este invalidă.');
    }
    $detail = $source === 'externa' ? 'detaliu_ext' : 'detaliu';
    $pdo = caiet_parity_pdo();
    $lineId = isset($payload['iddetaliu']) && is_numeric($payload['iddetaliu']) ? (int) $payload['iddetaliu'] : 0;
    $total = round($qty * $price, 2);
    if ($lineId > 0) {
        $sql = 'UPDATE ' . $detail . ' SET cantitate = :qty, pret = :price, total = :total';
        $params = [':qty' => $qty, ':price' => $price, ':total' => $total, ':id' => $lineId, ':order_id' => $orderId];
        if (array_key_exists('furnizor', $payload) && $supplier !== '') {
            $sql .= ', furnizor = :furnizor';
            $params[':furnizor'] = $supplier;
        }
        $stmt = $pdo->prepare($sql . ' WHERE iddetaliu = :id AND idcomanda = :order_id');
        $stmt->execute($params);
    } else {
        $stmt = $pdo->prepare('INSERT INTO ' . $detail . ' (idcomanda, idprodus, cantitate, pret, total, culoare, furnizor, created_at) VALUES (:order_id, :product, :qty, :price, :total, :color, :furnizor, :created)');
        $stmt->execute([
            ':order_id' => $orderId,
            ':product' => $productId,
            ':qty' => $qty,
            ':price' => $price,
            ':total' => $total,
            ':color' => 'FFFFFF',
            ':furnizor' => $supplier,
            ':created' => date('Y-m-d H:i:s'),
        ]);
    }
    caiet_recalc_order_total($pdo, $source, $orderId);

    return ['order_id' => $orderId, 'idprodus' => $productId];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, bool>
 */
function caiet_line_delete(array $payload): array
{
    $source = caiet_comenzi_source($payload);
    $orderId = caiet_comenzi_id($payload, 'order_id');
    $lineId = caiet_comenzi_id($payload, 'iddetaliu');
    $detail = $source === 'externa' ? 'detaliu_ext' : 'detaliu';
    $pdo = caiet_parity_pdo();
    $stmt = $pdo->prepare('DELETE FROM ' . $detail . ' WHERE iddetaliu = :id AND idcomanda = :order_id');
    $stmt->execute([':id' => $lineId, ':order_id' => $orderId]);
    caiet_recalc_order_total($pdo, $source, $orderId);

    return ['deleted' => $stmt->rowCount() > 0];
}

function caiet_recalc_order_total(PDO $pdo, string $source, int $orderId): void
{
    $detail = $source === 'externa' ? 'detaliu_ext' : 'detaliu';
    $header = $source === 'externa' ? 'comenzi_ext' : 'comenzi';
    $sum = $pdo->prepare('SELECT COALESCE(SUM(ROUND(cantitate * pret, 2)), 0) FROM ' . $detail . ' WHERE idcomanda = :id');
    $sum->execute([':id' => $orderId]);
    $total = round((float) $sum->fetchColumn(), 2);
    $update = $pdo->prepare('UPDATE ' . $header . ' SET total = :total WHERE idcomanda = :id');
    $update->execute([':total' => $total, ':id' => $orderId]);
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function caiet_invoice_from_order(\Besoiu\Core\CaietComenzi\CaietComenziModel $model, array $payload): array
{
    $source = caiet_comenzi_source($payload);
    $orderId = caiet_comenzi_id($payload, 'order_id');
    $pdo = caiet_parity_pdo();
    $header = $source === 'externa' ? 'comenzi_ext' : 'comenzi';
    $detail = $source === 'externa' ? 'detaliu_ext' : 'detaliu';
    $stmt = $pdo->prepare('SELECT idclient, id_factura FROM ' . $header . ' WHERE idcomanda = :id LIMIT 1');
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        throw new InvalidArgumentException('Comanda nu există.');
    }
    if ((int) ($order['id_factura'] ?? 0) > 0) {
        return ['OrderID' => (int) $order['id_factura'], 'existing' => true];
    }
    $linesStmt = $pdo->prepare('SELECT idprodus, cantitate, pret FROM ' . $detail . ' WHERE idcomanda = :id');
    $linesStmt->execute([':id' => $orderId]);
    $lines = [];
    foreach ($linesStmt->fetchAll(PDO::FETCH_ASSOC) as $line) {
        $lines[] = [
            'idprodus' => (int) $line['idprodus'],
            'cantitate' => (float) $line['cantitate'],
            'pret' => (float) $line['pret'],
            'tva' => 21,
        ];
    }
    if ($lines === []) {
        throw new InvalidArgumentException('Comanda nu are produse de facturat.');
    }
    $agent = (int) ($payload['agent'] ?? 0);
    if ($agent <= 0) {
        $agent = (int) $pdo->query('SELECT EmployeeId FROM employees ORDER BY EmployeeId ASC LIMIT 1')->fetchColumn();
    }
    $created = $model->createInvoice([
        'id_client' => (int) $order['idclient'],
        'agent' => $agent,
        'data_factura' => date('d/m/Y'),
        'data_scadenta' => date('d/m/Y', strtotime('+14 days')),
        'tip_incasare' => (int) ($payload['tip_incasare'] ?? 1),
        'tip_factura' => (string) ($payload['tip_factura'] ?? 'internal'),
        'lines' => $lines,
    ]);
    $link = $pdo->prepare('UPDATE ' . $header . ' SET id_factura = :invoice WHERE idcomanda = :id');
    $link->execute([':invoice' => (int) $created['OrderID'], ':id' => $orderId]);

    return $created;
}

/**
 * @return array{html:string}
 */
function caiet_invoice_print(\Besoiu\Core\CaietComenzi\CaietComenziModel $model, int $orderId): array
{
    $data = caiet_comenzi_factura($model, $orderId);
    $rows = '';
    $i = 1;
    foreach ($data['lines'] ?? [] as $line) {
        if (!is_array($line)) {
            continue;
        }
        $rows .= '<tr><td>' . $i++ . '</td><td>' . htmlspecialchars((string) ($line['ProductId'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars((string) ($line['Quantity'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars((string) ($line['UnitPrice'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars((string) ($line['tva'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars((string) ($line['total'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }
    $html = '<!DOCTYPE html><html lang="ro"><head><meta charset="utf-8"><title>Factura ' . (int) $data['OrderID'] . '</title><style>body{font-family:Arial,sans-serif;margin:24px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ccc;padding:6px;text-align:left}</style></head><body>'
        . '<h1>Factura ' . htmlspecialchars((string) ($data['seria'] ?? ''), ENT_QUOTES, 'UTF-8') . ' ' . (int) $data['OrderID'] . '</h1>'
        . '<p><strong>' . htmlspecialchars((string) ($data['client_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong></p>'
        . '<table><thead><tr><th>#</th><th>Produs</th><th>Cant.</th><th>Preț</th><th>TVA</th><th>Total</th></tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<p>Total: <strong>' . htmlspecialchars((string) ($data['grand_total'] ?? ''), ENT_QUOTES, 'UTF-8') . ' RON</strong></p>'
        . '<script>window.print()</script></body></html>';

    return ['html' => $html];
}

/**
 * @param array<int, mixed> $lines
 * @return array{series:string,number:string}
 */
function caiet_emit_smartbill(PDO $pdo, int $invoiceId, int $customerId, array $lines, string $series, int $paymentType): array
{
    $email = caiet_cred('smartbill', 'user_email') ?: caiet_env('SMARTBILL_USER_EMAIL');
    $apiKey = caiet_cred('smartbill', 'api_key') ?: caiet_env('SMARTBILL_API_KEY');
    $vat = caiet_cred('smartbill', 'company_vat') ?: caiet_env('SMARTBILL_COMPANY_VAT', 'RO31298897');
    if ($email === '' || $apiKey === '') {
        throw new InvalidArgumentException('SmartBill nu este configurat (user_email, api_key).');
    }
    $client = $pdo->prepare('SELECT nume, companie, cif, adresa, telefon FROM clienti WHERE idclienti = :id LIMIT 1');
    $client->execute([':id' => $customerId]);
    $customer = $client->fetch(PDO::FETCH_ASSOC) ?: [];
    $products = [];
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        $productId = (int) ($line['idprodus'] ?? 0);
        $nameStmt = $pdo->prepare('SELECT denumire FROM produse WHERE idprodus = :id LIMIT 1');
        $nameStmt->execute([':id' => $productId]);
        $name = trim((string) $nameStmt->fetchColumn());
        $products[] = [
            'name' => $name !== '' ? $name : ('Produs ' . $productId),
            'code' => (string) $productId,
            'isDiscount' => false,
            'measuringUnitName' => 'buc',
            'currency' => 'RON',
            'quantity' => (float) ($line['cantitate'] ?? 1),
            'price' => round((float) ($line['pret'] ?? 0), 2),
            'isTaxIncluded' => false,
            'taxName' => 'Normala',
            'taxPercentage' => (float) ($line['tva'] ?? 21),
            'isService' => false,
        ];
    }
    $payload = [
        'companyVatCode' => $vat,
        'client' => [
            'name' => trim((string) (($customer['companie'] ?? '') !== '' ? $customer['companie'] : ($customer['nume'] ?? 'Client'))),
            'vatCode' => (string) ($customer['cif'] ?? ''),
            'address' => (string) ($customer['adresa'] ?? ''),
            'isTaxPayer' => true,
            'city' => 'Timisoara',
            'country' => 'Romania',
            'email' => '',
            'phone' => (string) ($customer['telefon'] ?? ''),
        ],
        'issueDate' => date('Y-m-d'),
        'seriesName' => $series !== '' ? $series : 'BPA_CAI',
        'isDraft' => false,
        'currency' => 'RON',
        'language' => 'RO',
        'products' => $products,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $endpoint = rtrim(caiet_env('SMARTBILL_API_URL', 'https://ws.smartbill.ro/SBZ/api'), '/') . '/invoice';
    $response = caiet_http('POST', $endpoint, $json === false ? '{}' : $json, [
        'Authorization: Basic ' . base64_encode($email . ':' . $apiKey),
        'Accept: application/json',
        'Content-Type: application/json',
    ]);
    $number = (string) ($response['json']['number'] ?? '');
    $remoteSeries = (string) ($response['json']['series'] ?? $series);
    if ($number === '' || $response['status'] >= 400) {
        $message = (string) ($response['json']['errorText'] ?? $response['json']['message'] ?? $response['body']);
        throw new InvalidArgumentException('SmartBill: ' . substr($message, 0, 300));
    }

    return ['series' => $remoteSeries, 'number' => $number];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function caiet_invoice_line_save(array $payload): array
{
    $orderId = caiet_comenzi_id($payload, 'OrderID');
    $productId = (int) ($payload['idprodus'] ?? 0);
    $qty = (float) ($payload['cantitate'] ?? 0);
    $price = round((float) ($payload['pret'] ?? 0), 2);
    $rate = (float) ($payload['tva'] ?? 21);
    if ($productId <= 0 || $qty == 0.0) {
        throw new InvalidArgumentException('Linia de factură este invalidă.');
    }
    $value = round($qty * $price, 2);
    $vat = round($value * ($rate / 100), 2);
    $total = round($value + $vat, 2);
    $pdo = caiet_parity_pdo();
    $exists = $pdo->prepare('SELECT ProductId FROM facturidetails WHERE OrderID = :order_id AND ProductId = :product LIMIT 1');
    $exists->execute([':order_id' => $orderId, ':product' => $productId]);
    if ($exists->fetchColumn()) {
        $stmt = $pdo->prepare('UPDATE facturidetails SET Quantity = :qty, UnitPrice = :price, tva = :tva, total = :total WHERE OrderID = :order_id AND ProductId = :product');
        $stmt->execute([':qty' => $qty, ':price' => $price, ':tva' => $vat, ':total' => $total, ':order_id' => $orderId, ':product' => $productId]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO facturidetails (OrderID, ProductId, UnitPrice, Quantity, tva, total) VALUES (:order_id, :product, :price, :qty, :tva, :total)');
        $stmt->execute([':order_id' => $orderId, ':product' => $productId, ':price' => $price, ':qty' => $qty, ':tva' => $vat, ':total' => $total]);
    }

    return ['OrderID' => $orderId];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, bool>
 */
function caiet_invoice_line_delete(array $payload): array
{
    $orderId = caiet_comenzi_id($payload, 'OrderID');
    $productId = (int) ($payload['idprodus'] ?? $payload['ProductId'] ?? 0);
    if ($productId <= 0) {
        throw new InvalidArgumentException('Produsul de pe factură este invalid.');
    }
    $stmt = caiet_parity_pdo()->prepare('DELETE FROM facturidetails WHERE ProductId = :product AND OrderID = :order_id');
    $stmt->execute([':product' => $productId, ':order_id' => $orderId]);

    return ['deleted' => $stmt->rowCount() > 0];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function caiet_user_save(array $payload): array
{
    $id = isset($payload['Id']) && is_numeric($payload['Id']) ? (int) $payload['Id'] : 0;
    $username = trim((string) ($payload['username'] ?? ''));
    $email = trim((string) ($payload['email'] ?? ''));
    if ($username === '' || $email === '') {
        throw new InvalidArgumentException('Utilizatorul și emailul sunt obligatorii.');
    }
    $pdo = caiet_parity_pdo();
    $data = [
        ':username' => $username,
        ':email' => $email,
        ':nume' => trim((string) ($payload['nume'] ?? '')),
        ':prenume' => trim((string) ($payload['prenume'] ?? '')),
        ':telefon' => trim((string) ($payload['telefon'] ?? '')),
        ':rol' => trim((string) ($payload['rol'] ?? 'user')),
        ':active' => !empty($payload['active']) ? 1 : 0,
    ];
    $data[':nume_complet'] = trim($data[':nume'] . ' ' . $data[':prenume']);
    if ($id > 0) {
        $sql = 'UPDATE users SET username = :username, email = :email, nume = :nume, prenume = :prenume, telefon = :telefon, rol = :rol, active = :active, nume_complet = :nume_complet';
        $password = (string) ($payload['password'] ?? '');
        if ($password !== '') {
            if (strlen($password) < 8) {
                throw new InvalidArgumentException('Parola trebuie să aibă cel puțin 8 caractere.');
            }
            $sql .= ', password = :password';
            $data[':password'] = password_hash($password, PASSWORD_BCRYPT);
        }
        $data[':id'] = $id;
        $pdo->prepare($sql . ' WHERE Id = :id')->execute($data);
    } else {
        $password = (string) ($payload['password'] ?? '');
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Parola trebuie să aibă cel puțin 8 caractere.');
        }
        $newId = (int) $pdo->query('SELECT COALESCE(MAX(Id), 0) + 1 FROM users')->fetchColumn();
        $userId = (int) $pdo->query('SELECT COALESCE(MAX(user_id), 0) + 1 FROM users')->fetchColumn();
        $pdo->prepare(
            'INSERT INTO users (Id, user_id, username, email, password, nume, prenume, telefon, rol, magazin_id, nume_complet, active, created_at)
             VALUES (:id, :user_id, :username, :email, :password, :nume, :prenume, :telefon, :rol, 1, :nume_complet, :active, :created)'
        )->execute($data + [
            ':id' => $newId,
            ':user_id' => $userId,
            ':password' => password_hash($password, PASSWORD_BCRYPT),
            ':created' => date('Y-m-d H:i:s'),
        ]);
        $id = $newId;
    }
    $permissions = $payload['permissions'] ?? [];
    if (is_array($permissions)) {
        $write = $pdo->prepare('INSERT INTO user_permissions (user_id, menu_key, permission) VALUES (:user_id, :menu, :allowed) ON DUPLICATE KEY UPDATE permission = VALUES(permission)');
        foreach ($permissions as $key => $allowed) {
            $menu = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $key)) ?? '';
            if ($menu === '') {
                continue;
            }
            $write->execute([':user_id' => $id, ':menu' => $menu, ':allowed' => !empty($allowed) ? 1 : 0]);
        }
    }

    return ['Id' => $id];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, bool>
 */
function caiet_user_delete(array $payload): array
{
    $id = caiet_comenzi_id($payload, 'Id');
    $pdo = caiet_parity_pdo();
    $pdo->prepare('DELETE FROM user_permissions WHERE user_id = :id')->execute([':id' => $id]);
    $stmt = $pdo->prepare('DELETE FROM users WHERE Id = :id');
    $stmt->execute([':id' => $id]);

    return ['deleted' => $stmt->rowCount() > 0];
}

/**
 * @return list<array<string, mixed>>
 */
function caiet_wishlist_list(int $userId): array
{
    $stmt = caiet_parity_pdo()->prepare('SELECT id, name, phone, vin, created_at FROM supplier_saved_carts WHERE user_id = :user ORDER BY id DESC LIMIT 100');
    $stmt->execute([':user' => $userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function caiet_wishlist_save(array $payload, int $userId): array
{
    $name = trim((string) ($payload['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('Numele listei este obligatoriu.');
    }
    $cartStmt = caiet_parity_pdo()->prepare('SELECT cart FROM supplier_carts WHERE user_id = :user LIMIT 1');
    $cartStmt->execute([':user' => $userId]);
    $cart = (string) ($cartStmt->fetchColumn() ?: '');
    if ($cart === '' || $cart === '[]' || $cart === '{}') {
        throw new InvalidArgumentException('Coșul furnizori este gol.');
    }
    $stmt = caiet_parity_pdo()->prepare(
        'INSERT INTO supplier_saved_carts (user_id, name, phone, vin, cart, alreadygenerated, created_at, updated_at)
         VALUES (:user, :name, :phone, :vin, :cart, 0, :created, :updated)'
    );
    $now = time();
    $stmt->execute([
        ':user' => $userId,
        ':name' => $name,
        ':phone' => trim((string) ($payload['phone'] ?? '')),
        ':vin' => trim((string) ($payload['vin'] ?? '')),
        ':cart' => $cart,
        ':created' => $now,
        ':updated' => date('Y-m-d H:i:s'),
    ]);

    return ['id' => (int) caiet_parity_pdo()->lastInsertId()];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, bool>
 */
function caiet_wishlist_delete(array $payload, int $userId): array
{
    $id = caiet_comenzi_id($payload, 'id');
    $stmt = caiet_parity_pdo()->prepare('DELETE FROM supplier_saved_carts WHERE id = :id AND user_id = :user');
    $stmt->execute([':id' => $id, ':user' => $userId]);

    return ['deleted' => $stmt->rowCount() > 0];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, string>
 */
function caiet_wishlist_offer(array $payload, int $userId): array
{
    $id = caiet_comenzi_id($payload, 'id');
    $stmt = caiet_parity_pdo()->prepare('SELECT phone, vin, cart FROM supplier_saved_carts WHERE id = :id AND user_id = :user LIMIT 1');
    $stmt->execute([':id' => $id, ':user' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new InvalidArgumentException('Lista salvată nu există.');
    }
    $cart = json_decode((string) $row['cart'], true);
    $lines = [];
    $total = 0.0;
    if (is_array($cart)) {
        foreach ($cart as $items) {
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $qty = (float) ($item['qty'] ?? 1);
                $price = (float) ($item['price'] ?? 0);
                $total += $qty * $price;
                $lines[] = trim((string) ($item['product_name'] ?? $item['product_code'] ?? 'Produs')) . ' x ' . $qty . ' = ' . number_format($qty * $price, 2, '.', '') . ' RON';
            }
        }
    }
    $text = strtr(caiet_template('whatsapp', 'wishlist_offer'), [
        '{{vin}}' => (string) ($row['vin'] ?? ''),
        '{{product_lines}}' => implode("\n", $lines),
        '{{total}}' => number_format($total, 2, '.', ''),
    ]);
    $phone = preg_replace('/\D+/', '', (string) ($row['phone'] ?? '')) ?? '';
    if (str_starts_with($phone, '0')) {
        $phone = '40' . substr($phone, 1);
    }
    $url = $phone !== '' ? 'https://web.whatsapp.com/send/?phone=' . $phone . '&text=' . rawurlencode($text) : '';

    return ['text' => $text, 'url' => $url];
}

/**
 * @return list<array<string, mixed>>
 */
function caiet_promo_list(): array
{
    return caiet_parity_pdo()->query('SELECT id, supplier, brand FROM promotions ORDER BY supplier, brand')->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function caiet_promo_save(array $payload): array
{
    $supplier = strtolower(trim((string) ($payload['supplier'] ?? '')));
    $brand = trim((string) ($payload['brand'] ?? ''));
    if ($supplier === '' || $brand === '') {
        throw new InvalidArgumentException('Furnizorul și marca promoției sunt obligatorii.');
    }
    $pdo = caiet_parity_pdo();
    $exists = $pdo->prepare('SELECT id FROM promotions WHERE supplier = :supplier AND brand = :brand LIMIT 1');
    $exists->execute([':supplier' => $supplier, ':brand' => $brand]);
    $id = (int) $exists->fetchColumn();
    if ($id <= 0) {
        $pdo->prepare('INSERT INTO promotions (supplier, brand, created_at, updated_at) VALUES (:supplier, :brand, :created, :updated)')->execute([
            ':supplier' => $supplier,
            ':brand' => $brand,
            ':created' => date('Y-m-d H:i:s'),
            ':updated' => date('Y-m-d H:i:s'),
        ]);
        $id = (int) $pdo->lastInsertId();
    }

    return ['id' => $id, 'supplier' => $supplier, 'brand' => $brand];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, bool>
 */
function caiet_promo_delete(array $payload): array
{
    $id = caiet_comenzi_id($payload, 'id');
    $stmt = caiet_parity_pdo()->prepare('DELETE FROM promotions WHERE id = :id');
    $stmt->execute([':id' => $id]);

    return ['deleted' => $stmt->rowCount() > 0];
}

/**
 * @return list<array<string, mixed>>
 */
function caiet_excluded_list(int $userId): array
{
    $stmt = caiet_parity_pdo()->prepare('SELECT id, product_name, product_code, qty, price, order_from, created_at FROM autototal_excluded_cart_items WHERE user_id = :user ORDER BY id DESC LIMIT 200');
    $stmt->execute([':user' => $userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, int>
 */
function caiet_excluded_save(array $payload, int $userId): array
{
    $name = trim((string) ($payload['product_name'] ?? ''));
    $code = trim((string) ($payload['product_code'] ?? ''));
    if ($name === '' && $code === '') {
        throw new InvalidArgumentException('Produsul exclus nu are cod.');
    }
    $stmt = caiet_parity_pdo()->prepare(
        'INSERT INTO autototal_excluded_cart_items (user_id, order_from, cart_item_key, supplier, product_code, variant_code, itemkey, product_name, manufacturer, qty, price, currency, created_at, updated_at)
         VALUES (:user, :order_from, :key, \'autototal\', :code, :variant, :itemkey, :name, :maker, :qty, :price, :currency, :created, :updated)'
    );
    $key = trim((string) ($payload['cart_item_key'] ?? $code)) . '#' . date('YmdHis');
    $stmt->execute([
        ':user' => $userId,
        ':order_from' => trim((string) ($payload['order_from'] ?? 'TIMISOARA')),
        ':key' => $key,
        ':code' => $code,
        ':variant' => trim((string) ($payload['variant_code'] ?? '')),
        ':itemkey' => trim((string) ($payload['itemkey'] ?? $payload['variant_code'] ?? $code)),
        ':name' => $name,
        ':maker' => trim((string) ($payload['manufacturer'] ?? '')),
        ':qty' => (int) ($payload['qty'] ?? 1),
        ':price' => (float) ($payload['price'] ?? 0),
        ':currency' => 'RON',
        ':created' => date('Y-m-d H:i:s'),
        ':updated' => date('Y-m-d H:i:s'),
    ]);

    return ['id' => (int) caiet_parity_pdo()->lastInsertId()];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, bool>
 */
function caiet_excluded_delete(array $payload, int $userId): array
{
    $id = caiet_comenzi_id($payload, 'id');
    $stmt = caiet_parity_pdo()->prepare('DELETE FROM autototal_excluded_cart_items WHERE id = :id AND user_id = :user');
    $stmt->execute([':id' => $id, ':user' => $userId]);

    return ['deleted' => $stmt->rowCount() > 0];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, int|string>
 */
function caiet_lkq_import(array $payload): array
{
    $secret = caiet_env('LKQ_CRON_SECRET');
    $given = trim((string) ($payload['key'] ?? ''));
    if ($secret === '' || !hash_equals($secret, $given)) {
        throw new InvalidArgumentException('Cheia de import LKQ este invalidă.');
    }
    $zipPath = trim((string) ($payload['zip'] ?? caiet_env('LKQ_ZIP_PATH')));
    if ($zipPath === '' || !is_file($zipPath)) {
        throw new InvalidArgumentException('Arhiva LKQ nu este pe disc. Setează LKQ_ZIP_PATH.');
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new InvalidArgumentException('Arhiva LKQ nu poate fi deschisă.');
    }
    $csv = '';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        if (str_ends_with(strtolower($name), '.csv')) {
            $csv = (string) $zip->getFromIndex($i);
            break;
        }
    }
    $zip->close();
    if ($csv === '') {
        throw new InvalidArgumentException('Arhiva LKQ nu conține CSV.');
    }
    $handle = fopen('php://temp', 'w+');
    if ($handle === false) {
        throw new InvalidArgumentException('Nu pot citi CSV-ul LKQ.');
    }
    fwrite($handle, $csv);
    rewind($handle);
    fgetcsv($handle, 0, ';');
    $pdo = caiet_parity_pdo();
    $insert = $pdo->prepare(
        'INSERT INTO lkq_prices (item_nr, supplier_catalog_nr, description_ro, description_en, brand_name, net_price, updated_at, created_at)
         VALUES (:item, :catalog, :ro, :en, :brand, :price, :updated, :created)
         ON DUPLICATE KEY UPDATE supplier_catalog_nr = VALUES(supplier_catalog_nr), description_ro = VALUES(description_ro), description_en = VALUES(description_en), brand_name = VALUES(brand_name), net_price = VALUES(net_price), updated_at = VALUES(updated_at)'
    );
    $count = 0;
    $now = date('Y-m-d H:i:s');
    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        $item = trim((string) ($row[0] ?? ''));
        if ($item === '') {
            continue;
        }
        $insert->execute([
            ':item' => $item,
            ':catalog' => trim((string) ($row[1] ?? '')),
            ':ro' => $row[2] ?? null,
            ':en' => $row[3] ?? null,
            ':brand' => $row[4] ?? null,
            ':price' => (float) ($row[5] ?? 0),
            ':updated' => $now,
            ':created' => $now,
        ]);
        $count++;
    }
    fclose($handle);

    return ['imported' => $count];
}

/**
 * Plasare comandă la furnizor, aceeași ramură ca SearchingController::placeOrder.
 *
 * @param array<string, array<string, array<string, mixed>>> $cart
 * @param list<string> $excludedKeys
 * @return array{warnings:list<string>,placed:list<string>}
 */
function caiet_supplier_place(array $cart, string $orderFrom, int $userId, array $excludedKeys): array
{
    $warnings = [];
    $placed = [];
    $orderFrom = strtoupper($orderFrom) === 'UTVIN' ? 'UTVIN' : 'TIMISOARA';
    foreach ($cart as $supplier => $items) {
        if (!is_array($items) || $items === []) {
            continue;
        }
        $name = strtolower((string) $supplier);
        if (in_array($name, ['site_produse', 'elit', 'intercars', 'bardi'], true)) {
            continue;
        }
        if ($name === 'autonet') {
            caiet_place_autonet($items, $orderFrom);
            $placed[] = 'autonet';
        } elseif ($name === 'autopartner') {
            $warning = caiet_place_autopartner($items, $orderFrom);
            if ($warning !== '') {
                $warnings[] = $warning;
            }
            $placed[] = 'autopartner';
        } elseif ($name === 'materom') {
            caiet_place_materom($items, $orderFrom);
            $placed[] = 'materom';
        } elseif ($name === 'autototal') {
            caiet_place_autototal($items, $orderFrom, $userId, $excludedKeys);
            $placed[] = 'autototal';
        } elseif ($name === 'szakal') {
            $warning = caiet_place_szakal($items, $orderFrom);
            if ($warning !== '') {
                $warnings[] = $warning;
            }
            $placed[] = 'szakal';
        }
    }

    return ['warnings' => $warnings, 'placed' => $placed];
}

/**
 * @param array<string, array<string, mixed>> $items
 */
function caiet_place_autonet(array $items, string $orderFrom): void
{
    $tax = SupplierSearchConfig::autonetTaxCode();
    $token = SupplierSearchConfig::autonetSecurityToken();
    if ($tax === '' || $token === '') {
        throw new InvalidArgumentException('Autonet nu este configurat.');
    }
    $branch = $orderFrom === 'UTVIN' ? 'MAG2' : SupplierSearchConfig::autonetBranch();
    $orderItems = [];
    foreach ($items as $item) {
        $part = trim((string) ($item['autonet_partno'] ?? $item['variant_code'] ?? $item['order_code'] ?? ''));
        if ($part === '') {
            throw new InvalidArgumentException('Lipsește PartNo Autonet pentru ' . (string) ($item['product_name'] ?? 'produs'));
        }
        $orderItems[] = ['PartNo' => $part, 'Quantity' => (float) ($item['qty'] ?? 1)];
    }
    $body = json_encode([
        'ExternalOrderNumber' => 'WEB-' . time() . '-' . random_int(1000, 9999),
        'OrderItems' => $orderItems,
    ], JSON_UNESCAPED_UNICODE);
    $response = caiet_http('POST', SupplierSearchConfig::autonetBaseUrl() . '/order/create/dynamic', $body === false ? '{}' : $body, [
        'TAX-CODE: ' . $tax,
        'SECURITY-TOKEN: ' . $token,
        'BRANCH: ' . $branch,
        'Content-Type: application/json',
        'Accept: application/json',
    ]);
    $code = $response['json']['Response']['Code'] ?? null;
    if ((int) $code !== 0) {
        throw new InvalidArgumentException('Autonet: ' . (string) ($response['json']['Response']['Message'] ?? $response['body']));
    }
    caiet_store_supplier_order('autonet', (string) ($response['json']['OrderNumber'] ?? ('AN-' . time())), $response['json'] ?? []);
}

/**
 * @param array<string, array<string, mixed>> $items
 */
function caiet_place_autopartner(array $items, string $orderFrom): string
{
    $furnizor = SupplierSearchConfig::autopartnerFurnizor();
    if ($furnizor === null) {
        throw new InvalidArgumentException('AutoPartner nu este configurat.');
    }
    $list = [];
    $position = 1;
    foreach ($items as $item) {
        $code = trim((string) ($item['product_code'] ?? $item['order_code'] ?? ''));
        if ($code === '') {
            throw new InvalidArgumentException('Lipsește codul AutoPartner.');
        }
        $list[] = [
            'ProductCode' => $code,
            'Quantity' => (int) ($item['qty'] ?? 1),
            'QuantityP' => 0,
            'PositionNumber' => $position++,
        ];
    }
    $response = SupplierHooks::autoPartnerClient()->configure($furnizor)->request('/InsertOrder', [
        'comments' => 'Order from web cart ' . $orderFrom,
        'orderItemList' => $list,
        'zone' => '03',
        'ownCollect' => '0',
        'separateDocsAndPackaging' => '0',
        'onlyWhenAllAvailable' => '0',
        'source' => 0,
    ]);
    if (empty($response['success'])) {
        throw new InvalidArgumentException('AutoPartner: ' . (string) ($response['error'] ?? 'comandă respinsă'));
    }
    $order = (array) ($response['data']['RestInsertOrderResult'] ?? []);
    $number = trim((string) ($order['OrderNumber'] ?? ''));
    if ($number === '') {
        $number = 'AP-PENDING-' . date('YmdHis');
    }
    caiet_store_supplier_order('autopartner', $number, $response['data']);
    $codes = [];
    $top = trim((string) ($order['ErrorCode'] ?? ''));
    if ($top !== '') {
        $codes[] = $top;
    }

    return $codes === [] ? '' : 'AutoPartner: ' . implode(', ', $codes);
}

/**
 * @param array<string, array<string, mixed>> $items
 */
function caiet_place_materom(array $items, string $orderFrom): void
{
    $token = $orderFrom === 'UTVIN'
        ? (caiet_env('MATEROM_TOKEN_UTVIN') ?: SupplierSearchConfig::materomToken())
        : SupplierSearchConfig::materomToken();
    if ($token === '') {
        throw new InvalidArgumentException('Materom nu este configurat.');
    }
    $codes = [];
    foreach ($items as $item) {
        $code = (string) ($item['order_code'] ?? '');
        $qty = (int) ($item['qty'] ?? 1);
        if ($code === '') {
            throw new InvalidArgumentException('Lipsește order_code Materom.');
        }
        $codes[] = (string) preg_replace('/qty:\d+/', 'qty:' . $qty, $code);
    }
    $body = json_encode(['items' => $codes, 'details' => 'Order from web cart'], JSON_UNESCAPED_UNICODE);
    $response = caiet_http('POST', SupplierSearchConfig::materomBaseUrl() . '/v2/sap/orders/standard', $body === false ? '{}' : $body, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
    ]);
    if ($response['status'] < 200 || $response['status'] >= 300) {
        throw new InvalidArgumentException('Materom: HTTP ' . $response['status']);
    }
    $orders = $response['json']['data']['orders'] ?? [];
    $number = is_array($orders) && isset($orders[0]['order_number']) ? (string) $orders[0]['order_number'] : ('MA-' . time());
    caiet_store_supplier_order('materom', $number, $response['json'] ?? []);
}

/**
 * @param array<string, array<string, mixed>> $items
 * @param list<string> $excludedKeys
 */
function caiet_place_autototal(array $items, string $orderFrom, int $userId, array $excludedKeys): void
{
    $toOrder = [];
    foreach ($items as $key => $item) {
        if (in_array((string) $key, $excludedKeys, true)) {
            caiet_excluded_save([
                'product_name' => $item['product_name'] ?? '',
                'product_code' => $item['product_code'] ?? '',
                'variant_code' => $item['variant_code'] ?? '',
                'itemkey' => $item['itemkey'] ?? ($item['variant_code'] ?? ''),
                'manufacturer' => $item['manufacturer'] ?? '',
                'qty' => $item['qty'] ?? 1,
                'price' => $item['price'] ?? 0,
                'order_from' => $orderFrom,
                'cart_item_key' => (string) $key,
            ], $userId);
            continue;
        }
        $toOrder[$key] = $item;
    }
    if ($toOrder === []) {
        return;
    }
    $creds = SupplierSearchConfig::autototalCredentials();
    if ($creds['username'] === '' || $creds['password'] === '') {
        throw new InvalidArgumentException('AutoTotal nu este configurat.');
    }
    $base = rtrim(caiet_env('AUTOTOTAL_ORDER_URL', 'https://atx.autototal.ro:15085'), '/');
    $authBody = json_encode(['username' => $creds['username'], 'password' => $creds['password']]);
    $auth = caiet_http('POST', $base . '/api/v1/user/authenticate', $authBody === false ? '{}' : $authBody, ['Content-Type: application/json', 'Accept: application/json']);
    $token = (string) ($auth['json']['token'] ?? $auth['json']['TOKEN'] ?? '');
    if ($token === '') {
        throw new InvalidArgumentException('Autentificare AutoTotal eșuată.');
    }
    $orderItems = [];
    foreach ($toOrder as $item) {
        $itemKey = trim((string) ($item['itemkey'] ?? $item['variant_code'] ?? $item['product_code'] ?? ''));
        if ($itemKey === '') {
            throw new InvalidArgumentException('Lipsește codul AutoTotal.');
        }
        $orderItems[] = ['ITEMKEY' => $itemKey, 'QUANTITY' => (int) ($item['qty'] ?? 1)];
    }
    $body = json_encode(['ITEM' => $orderItems, 'URL' => [], 'REMARKS' => 'Order from web cart'], JSON_UNESCAPED_UNICODE);
    $response = caiet_http('POST', $base . '/api/v1/order', $body === false ? '{}' : $body, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
    ]);
    $ok = isset($response['json'][0]['MESSAGE']) && $response['json'][0]['MESSAGE'] === 'OK';
    if (!$ok) {
        throw new InvalidArgumentException('AutoTotal a respins comanda.');
    }
    caiet_store_supplier_order('autototal', (string) ($response['json'][0]['STATUS'] ?? ('AT-' . time())), $response['json'] ?? []);
}

/**
 * Plasare comandă Szakal Metal prin /order_by_token (cu fallback pe /order după GLID dacă tokenul a expirat).
 *
 * @param array<string, array<string, mixed>> $items
 */
function caiet_place_szakal(array $items, string $orderFrom): string
{
    if (!SupplierSearchConfig::szakalHasCredentials()) {
        throw new InvalidArgumentException('Szakal Metal nu este configurat.');
    }

    $client = new \Besoiu\Modules\SupplierSearch\Service\Clients\SzakalClient();
    $warnings = [];

    foreach ($items as $item) {
        $qty = max(1, (int) ($item['qty'] ?? 1));
        $token = trim((string) ($item['szakal_token'] ?? ''));
        $glid = trim((string) ($item['szakal_glid'] ?? ''));
        if ($glid === '') {
            $apiLookup = trim((string) ($item['api_lookup_code'] ?? ''));
            if (preg_match('/^\d+-\d+$/', $apiLookup)) {
                $glid = $apiLookup;
            }
        }
        if ($glid === '') {
            $vc = trim((string) ($item['variant_code'] ?? $item['order_code'] ?? ''));
            if (str_starts_with($vc, 'SZ:')) {
                $parts = explode(':', $vc);
                $glid = trim((string) ($parts[1] ?? ''));
            }
        }

        $productCode = trim((string) ($item['product_code'] ?? $glid));
        $note = substr('WEB-' . $orderFrom . '-' . $productCode, 0, 50);

        if ($token !== '') {
            $res = $client->orderByToken($token, $qty, $note);
            if (empty($res['success']) && (int) ($res['http_code'] ?? 0) === 404 && $glid !== '') {
                $res = $client->orderByGlid($glid, $qty, 'fastest_first', $note);
            }
        } elseif ($glid !== '') {
            $res = $client->orderByGlid($glid, $qty, 'fastest_first', $note);
        } else {
            throw new InvalidArgumentException('Lipsește tokenul / GLID-ul Szakal Metal pentru ' . (string) ($item['product_name'] ?? 'produs'));
        }

        if (empty($res['success'])) {
            throw new InvalidArgumentException('Szakal Metal (' . $productCode . '): ' . (string) ($res['error'] ?? 'comandă respinsă'));
        }

        if ((int) ($res['http_code'] ?? 200) === 210) {
            $warnings[] = 'Szakal Metal: livrare parțială pentru ' . $productCode;
        }

        $orders = $res['data']['orders'] ?? [];
        $orderIds = [];
        if (is_array($orders)) {
            foreach ($orders as $ord) {
                if (is_array($ord) && !empty($ord['order_id'])) {
                    $orderIds[] = (string) $ord['order_id'];
                }
            }
        }
        $orderNumber = $orderIds !== [] ? implode(',', $orderIds) : ('SZ-' . time() . '-' . random_int(100, 999));
        caiet_store_supplier_order('szakal', $orderNumber, is_array($res['data'] ?? null) ? $res['data'] : []);
    }

    return implode(' · ', $warnings);
}

/**
 * @param array<string, mixed> $raw
 */
function caiet_store_supplier_order(string $supplier, string $number, array $raw): void
{
    if ($number === '') {
        return;
    }
    try {
        $json = json_encode($raw, JSON_UNESCAPED_UNICODE);
        caiet_parity_pdo()->prepare(
            'INSERT INTO supplier_orders (supplier, order_number, raw_response, created_at, updated_at)
             VALUES (:supplier, :number, :raw, :created, :updated)
             ON DUPLICATE KEY UPDATE raw_response = VALUES(raw_response), updated_at = VALUES(updated_at)'
        )->execute([
            ':supplier' => $supplier,
            ':number' => $number,
            ':raw' => $json === false ? '{}' : $json,
            ':created' => date('Y-m-d H:i:s'),
            ':updated' => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable) {
    }
}
