<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';
require_once __DIR__ . '/caiet_pieseauto.php';

use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Core\CaietComenzi\CaietComenziModel;
use Config\Database;

$config = ApiBootstrap::bootJsonApi();
Database::ensureLegacy($config);

$payload = $_POST;
$raw = file_get_contents('php://input');
if (is_string($raw) && trim($raw) !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

$type = trim((string) ($payload['type_product'] ?? ''));
$userId = (int) ($_SESSION['user_id'] ?? 0);

try {
    $model = new CaietComenziModel();
    $data = caiet_comenzi_dispatch($model, $type, $payload, $userId);
    ApiBootstrap::json(['success' => true, 'data' => $data]);
} catch (InvalidArgumentException $exception) {
    ApiBootstrap::json(['success' => false, 'message' => $exception->getMessage()], 400);
} catch (Throwable $exception) {
    ApiBootstrap::json(['success' => false, 'message' => $exception->getMessage()], 500);
}

/**
 * @param array<string, mixed> $payload
 * @return mixed
 */
function caiet_comenzi_dispatch(CaietComenziModel $model, string $type, array $payload, int $userId): mixed
{
    return match ($type) {
        'produse_list' => $model->findProdusePage($payload),
        'produse_save' => $model->saveProduct($payload),
        'produse_delete' => ['deleted' => $model->deleteProduct(caiet_comenzi_id($payload, 'idprodus'))],
        'produse_update_tva' => ['updated' => $model->updateAllProductsTva((float) ($payload['tva'] ?? 0))],
        'clienti_list' => $model->findClients($payload),
        'clienti_save' => $model->saveClient($payload),
        'clienti_delete' => ['deleted' => $model->deleteClient(caiet_comenzi_id($payload, 'idclienti'))],
        'facturi_list' => $model->findFacturi($payload),
        'facturi_save' => $model->saveFactura($payload),
        'facturi_delete' => ['deleted' => $model->deleteFactura(caiet_comenzi_id($payload, 'OrderID'))],
        'facturi_details' => caiet_comenzi_factura($model, caiet_comenzi_id($payload, 'OrderID')),
        'incasari_list' => $model->findIncasari($payload),
        'incasari_save' => $model->saveIncasare($payload, $userId),
        'incasari_delete' => ['deleted' => $model->deleteIncasare(caiet_comenzi_id($payload, 'id'))],
        'incasari_daily' => $model->getDailyCash(),
        'incasari_daily_save' => $model->updateDailyCash((float) ($payload['amount'] ?? 0)),
        'comenzi_list' => $model->findOrders($payload),
        'comenzi_stats' => $model->getStats(),
        'comenzi_details' => [
            'lines' => $model->getOrderLines(
                caiet_comenzi_id($payload, 'order_id'),
                caiet_comenzi_source($payload)
            ),
        ],
        'utilizatori_list' => caiet_users(),
        'api_list' => caiet_api_keys(),
        'api_save' => caiet_api_save($payload),
        'pieseauto_fetch' => caiet_pieseauto_fetch($payload),
        'pieseauto_import' => caiet_pieseauto_import($payload, $userId),
        'comenzi_create' => caiet_create_order($payload, $userId),
        'comenzi_delete' => caiet_delete_order($payload),
        'comenzi_location' => caiet_update_location($payload),
        'comenzi_total' => caiet_update_total($payload),
        'comenzi_line' => caiet_update_line($payload),
        'comenzi_status' => $model->updateOrderStatus(
            caiet_comenzi_id($payload, 'order_id'),
            (int) ($payload['new_status'] ?? $payload['status'] ?? -1),
            caiet_comenzi_source($payload),
            $userId
        ),
        default => throw new InvalidArgumentException('Acțiune necunoscută.'),
    };
}

/**
 * @param array<string, mixed> $payload
 */
function caiet_comenzi_id(array $payload, string $key): int
{
    $id = $payload[$key] ?? $payload['id'] ?? 0;
    if (!is_numeric($id) || (int) $id <= 0) {
        throw new InvalidArgumentException('ID invalid.');
    }

    return (int) $id;
}

/**
 * @param array<string, mixed> $payload
 */
function caiet_comenzi_source(array $payload): string
{
    $source = strtolower(trim((string) ($payload['source_type'] ?? '')));
    if (!in_array($source, ['interna', 'externa'], true)) {
        throw new InvalidArgumentException('Tipul comenzii trebuie să fie internă sau externă.');
    }

    return $source;
}

/**
 * @return array<string, mixed>
 */
/**
 * @return list<array<string, mixed>>
 */
function caiet_users(): array
{
    $stmt = Database::getDB('legacy')->query(
        'SELECT Id, username, email, nume_complet, telefon, rol, active FROM users ORDER BY Id DESC LIMIT 100'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * @return list<array<string, string>>
 */
function caiet_api_keys(): array
{
    $stmt = Database::getDB('legacy')->query(
        'SELECT service_name, data_key, data_value FROM api_credentials ORDER BY service_name, data_key'
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['data_value'] = caiet_laravel_decrypt((string) ($row['data_value'] ?? ''));
    }
    unset($row);

    return $rows;
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, int>
 */
function caiet_api_save(array $payload): array
{
    $items = $payload['items'] ?? [];
    if (!is_array($items)) {
        throw new InvalidArgumentException('Lista de credențiale este invalidă.');
    }
    $pdo = Database::getDB('legacy');
    $update = $pdo->prepare(
        'UPDATE api_credentials SET data_value = :value WHERE service_name = :service AND data_key = :key'
    );
    $saved = 0;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $service = trim((string) ($item['service_name'] ?? ''));
        $key = trim((string) ($item['data_key'] ?? ''));
        if ($service === '' || $key === '' || !preg_match('/^[A-Za-z0-9_]+$/', $service) || !preg_match('/^[A-Za-z0-9_]+$/', $key)) {
            continue;
        }
        $update->execute([
            ':value' => caiet_laravel_encrypt((string) ($item['data_value'] ?? '')),
            ':service' => $service,
            ':key' => $key,
        ]);
        $saved += $update->rowCount() > 0 ? 1 : 0;
    }

    return ['saved' => $saved];
}

function caiet_laravel_key(): string
{
    $raw = trim((string) ($_ENV['LEGACY_APP_KEY'] ?? ''));
    if ($raw === '') {
        $envFile = dirname((string) (defined('BESOIU_ROOT') ? BESOIU_ROOT : dirname(__DIR__, 3))) . '/caietdecomenzi/.env';
        if (is_file($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES) ?: [];
            foreach ($lines as $line) {
                if (str_starts_with($line, 'APP_KEY=')) {
                    $raw = trim(substr($line, 8), " \t\"'");
                    break;
                }
            }
        }
    }
    if (str_starts_with($raw, 'base64:')) {
        $raw = substr($raw, 7);
    }
    $key = base64_decode($raw, true);
    if (!is_string($key) || strlen($key) !== 32) {
        throw new InvalidArgumentException('Cheia de decriptare a caietului nu este disponibilă.');
    }

    return $key;
}

function caiet_laravel_decrypt(string $payload): string
{
    $json = json_decode(base64_decode($payload, true) ?: '', true);
    if (!is_array($json) || !isset($json['iv'], $json['value'], $json['mac'])) {
        return $payload;
    }
    $key = caiet_laravel_key();
    $mac = hash_hmac('sha256', $json['iv'] . $json['value'], $key);
    if (!hash_equals($mac, (string) $json['mac'])) {
        throw new InvalidArgumentException('Credențiala nu poate fi citită.');
    }
    $iv = base64_decode((string) $json['iv'], true);
    $plain = openssl_decrypt((string) $json['value'], 'AES-256-CBC', $key, 0, $iv);
    if (!is_string($plain) || $plain === '') {
        throw new InvalidArgumentException('Credențiala nu poate fi citită.');
    }
    $value = @unserialize($plain, ['allowed_classes' => false]);

    return is_string($value) ? $value : $plain;
}

function caiet_laravel_encrypt(string $value): string
{
    $key = caiet_laravel_key();
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt(serialize($value), 'AES-256-CBC', $key, 0, $iv);
    if (!is_string($encrypted) || $encrypted === '') {
        throw new InvalidArgumentException('Credențiala nu poate fi salvată.');
    }
    $ivB64 = base64_encode($iv);
    $packet = json_encode([
        'iv' => $ivB64,
        'value' => $encrypted,
        'mac' => hash_hmac('sha256', $ivB64 . $encrypted, $key),
        'tag' => '',
    ], JSON_UNESCAPED_SLASHES);

    return base64_encode((string) $packet);
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function caiet_create_order(array $payload, int $userId): array
{
    $locRaw = $payload['locatie_mgz'] ?? $payload['location'] ?? '';
    $locNum = is_numeric($locRaw) ? (int) $locRaw : (strtolower((string) $locRaw) === 'utvin' ? 2 : 1);
    $source = $locNum === 3 ? 'externa' : caiet_comenzi_source($payload);
    $table = $source === 'externa' ? 'comenzi_ext' : 'comenzi';
    $detailTable = $source === 'externa' ? 'detaliu_ext' : 'detaliu';
    $pdo = Database::getDB('legacy');
    $clientId = isset($payload['idclient']) && is_numeric($payload['idclient']) ? (int) $payload['idclient'] : 0;
    if ($clientId <= 0) {
        throw new InvalidArgumentException('Alege un client din listă.');
    }
    $lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
    if ($lines === []) {
        throw new InvalidArgumentException('Nu există produse în comandă.');
    }
    $stare = (int) ($payload['idstare'] ?? $payload['stare'] ?? 1);
    if ($stare < 1) {
        $stare = 1;
    }
    $date = trim((string) ($payload['data'] ?? date('Y-m-d')));
    $obs = trim((string) ($payload['observations'] ?? ''));
    $idmasina = (int) ($payload['idmasina'] ?? 1);
    if ($idmasina <= 0) {
        $idmasina = 1;
    }
    $total = 0.0;
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        $total += (float) ($line['cantitate'] ?? 1) * (float) ($line['pret'] ?? 0);
    }
    $total = round($total, 2);

    $pdo->beginTransaction();
    try {
        $id = (int) $pdo->query('SELECT COALESCE(MAX(idcomanda), 0) + 1 FROM ' . $table)->fetchColumn();
        $createdAt = time();
        if ($source === 'externa') {
            $stmt = $pdo->prepare(
                'INSERT INTO comenzi_ext (idcomanda, userid, cont_awb, idclient, idmasina, stare, retur, total, data, observations, created_at) VALUES (:id, :uid, :awb, :client, :masina, :stare, 0, :total, :data, :obs, :created_at)'
            );
            $stmt->execute([
                ':id' => $id,
                ':uid' => $userId,
                ':awb' => '',
                ':client' => $clientId,
                ':masina' => $idmasina,
                ':stare' => $stare,
                ':total' => $total,
                ':data' => $date,
                ':obs' => $obs,
                ':created_at' => $createdAt,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO comenzi (idcomanda, userid, cont_awb, idclient, idmasina, stare, retur, total, data, locatie_mgz, observations, created_at) VALUES (:id, :uid, :awb, :client, :masina, :stare, 0, :total, :data, :loc, :obs, :created_at)'
            );
            $stmt->execute([
                ':id' => $id,
                ':uid' => $userId,
                ':awb' => '',
                ':client' => $clientId,
                ':masina' => $idmasina,
                ':stare' => $stare,
                ':total' => $total,
                ':data' => $date,
                ':loc' => (string) ($locNum === 2 ? 2 : 1),
                ':obs' => $obs,
                ':created_at' => $createdAt,
            ]);
        }

        $lineStmt = $pdo->prepare(
            'INSERT INTO ' . $detailTable . ' (idcomanda, idprodus, cantitate, pret, furnizor, culoare, created_at) VALUES (:order_id, :product_id, :qty, :price, :supplier, :color, :created_at)'
        );
        foreach ($lines as $line) {
            if (!is_array($line) || !is_numeric($line['idprodus'] ?? null)) {
                continue;
            }
            $lineStmt->execute([
                ':order_id' => $id,
                ':product_id' => (int) $line['idprodus'],
                ':qty' => max(1, (float) ($line['cantitate'] ?? 1)),
                ':price' => round((float) ($line['pret'] ?? 0), 2),
                ':supplier' => trim((string) ($line['furnizor'] ?? '__')) ?: '__',
                ':color' => 'FFFFFF',
                ':created_at' => time(),
            ]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return ['order_id' => $id, 'source_type' => $source, 'total' => $total];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function caiet_delete_order(array $payload): array
{
    $source = caiet_comenzi_source($payload);
    $table = $source === 'externa' ? 'comenzi_ext' : 'comenzi';
    $detailTable = $source === 'externa' ? 'detaliu_ext' : 'detaliu';
    $id = caiet_comenzi_id($payload, 'order_id');
    $pdo = Database::getDB('legacy');
    $current = $pdo->prepare('SELECT stare FROM ' . $table . ' WHERE idcomanda = :id LIMIT 1');
    $current->execute([':id' => $id]);
    $stare = $current->fetchColumn();
    if ($stare === false) {
        throw new InvalidArgumentException('Comanda nu există.');
    }
    if ((int) $stare !== 1) {
        throw new InvalidArgumentException('Se poate șterge doar o comandă cu status Comandat.');
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM ' . $detailTable . ' WHERE idcomanda = :id')->execute([':id' => $id]);
        $pdo->prepare('DELETE FROM ' . $table . ' WHERE idcomanda = :id')->execute([':id' => $id]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return ['order_id' => $id, 'deleted' => true];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function caiet_update_location(array $payload): array
{
    $id = caiet_comenzi_id($payload, 'order_id');
    $loc = (int) ($payload['location'] ?? 0);
    if (!in_array($loc, [1, 2], true)) {
        throw new InvalidArgumentException('Magazin invalid.');
    }
    $pdo = Database::getDB('legacy');
    $stmt = $pdo->prepare('UPDATE comenzi SET locatie_mgz = :loc WHERE idcomanda = :id');
    $stmt->execute([':loc' => (string) $loc, ':id' => $id]);

    return ['order_id' => $id, 'location' => $loc];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function caiet_update_total(array $payload): array
{
    $source = caiet_comenzi_source($payload);
    $table = $source === 'externa' ? 'comenzi_ext' : 'comenzi';
    $id = caiet_comenzi_id($payload, 'order_id');
    $transport = round((float) ($payload['transport'] ?? 0), 2);
    $pdo = Database::getDB('legacy');
    $stmt = $pdo->prepare('UPDATE ' . $table . ' SET total = ROUND(COALESCE(total, 0) + :transport, 2) WHERE idcomanda = :id');
    $stmt->execute([':transport' => $transport, ':id' => $id]);

    return ['order_id' => $id, 'transport' => $transport];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function caiet_update_line(array $payload): array
{
    $source = caiet_comenzi_source($payload);
    $table = $source === 'externa' ? 'detaliu_ext' : 'detaliu';
    $id = caiet_comenzi_id($payload, 'iddetaliu');
    $field = trim((string) ($payload['field'] ?? ''));
    $pdo = Database::getDB('legacy');
    if ($field === 'culoare') {
        $value = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', (string) ($payload['value'] ?? '')) ?? '');
        if (strlen($value) !== 6) {
            throw new InvalidArgumentException('Culoare invalidă.');
        }
        $stmt = $pdo->prepare('UPDATE ' . $table . ' SET culoare = :value WHERE iddetaliu = :id');
        $stmt->execute([':value' => $value, ':id' => $id]);
    } elseif ($field === 'furnizor') {
        $allowed = ['ET', 'MA', 'IC', 'AN', 'AT', 'BA', 'AB', 'SZ', 'AP', 'AD', 'Stoc'];
        $value = trim((string) ($payload['value'] ?? ''));
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException('Furnizor invalid.');
        }
        $stmt = $pdo->prepare('UPDATE ' . $table . ' SET furnizor = :value WHERE iddetaliu = :id');
        $stmt->execute([':value' => $value, ':id' => $id]);
    } else {
        throw new InvalidArgumentException('Câmp invalid.');
    }

    return ['iddetaliu' => $id, 'field' => $field];
}

/**
 * @return array<string, mixed>
 */
function caiet_comenzi_factura(CaietComenziModel $model, int $orderId): array
{
    $details = $model->getFacturaDetails($orderId);
    if ($details === []) {
        throw new InvalidArgumentException('Factura nu există.');
    }

    return $details;
}
