<?php

declare(strict_types=1);

namespace Evasystem\Services\Orders;

use PDO;
use RuntimeException;

/**
 * Comenzi interne + externe legacy ERP (create, edit, linii detaliu).
 */
final class LegacyOrderService
{
    public function __construct(
        private readonly ?PDO $pdo,
        private readonly OrderTmpService $tmpService,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function createInternalFromTmp(string $sessionId, int $userId, array $payload): array
    {
        $this->assertInternalTables();
        $payload['locatie_mgz'] = (int) ($payload['locatie_mgz'] ?? 1);
        if ((int) $payload['locatie_mgz'] === 3) {
            return $this->createExternalFromTmp($sessionId, $userId, $payload);
        }

        return $this->createFromTmpTables($sessionId, $userId, $payload, 'interna');
    }

    /** @param array<string, mixed> $payload */
    public function createExternalFromTmp(string $sessionId, int $userId, array $payload): array
    {
        $this->assertExternalTables();

        return $this->createFromTmpTables($sessionId, $userId, $payload, 'externa');
    }

    /** @return array<string, mixed> */
    public function getOrder(int $orderId, string $sourceType): array
    {
        $this->assertPdo();
        $sourceType = $this->normalizeSourceType($sourceType);
        $headerTable = $sourceType === 'externa' ? 'comenzi_ext' : 'comenzi';
        $lineTable = $sourceType === 'externa' ? 'detaliu_ext' : 'detaliu';

        $stmt = $this->pdo->prepare(
            "SELECT o.*, cl.nume AS client_name, cl.telefon AS client_phone, cl.adresa AS client_address
             FROM {$headerTable} o
             LEFT JOIN clienti cl ON cl.idclienti = o.idclient
             WHERE o.idcomanda = ? LIMIT 1"
        );
        $stmt->execute([$orderId]);
        $header = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$header) {
            throw new RuntimeException('Comanda nu a fost găsită.');
        }

        $linesStmt = $this->pdo->prepare(
            "SELECT d.*, p.denumire AS product_name, p.cod_produs
             FROM {$lineTable} d
             LEFT JOIN produse p ON p.idprodus = d.idprodus
             WHERE d.idcomanda = ?
             ORDER BY d.iddetaliu ASC"
        );
        $linesStmt->execute([$orderId]);
        $lines = $linesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $total = 0.0;
        foreach ($lines as $line) {
            $total += ((float) ($line['cantitate'] ?? 0)) * ((float) ($line['pret'] ?? 0));
        }

        return [
            'source_type' => $sourceType,
            'header' => $header,
            'lines' => $lines,
            'calculated_total' => round($total, 2),
        ];
    }

    /** @param array<string, mixed> $payload */
    public function updateHeader(int $orderId, string $sourceType, array $payload): array
    {
        $this->assertPdo();
        $sourceType = $this->normalizeSourceType($sourceType);
        $table = $sourceType === 'externa' ? 'comenzi_ext' : 'comenzi';

        $fields = [];
        $values = [];
        $map = [
            'stare' => 'idstare',
            'idstare' => 'idstare',
            'observations' => 'observations',
            'data' => 'data',
            'cont_awb' => 'cont_awb',
            'total' => 'total',
            'locatie_mgz' => 'locatie_mgz',
        ];

        foreach ($map as $payloadKey => $column) {
            if (!array_key_exists($payloadKey, $payload)) {
                continue;
            }
            $value = $payload[$payloadKey];
            if ($payloadKey === 'data' || $column === 'data') {
                $value = $this->normalizeDate((string) $value);
            }
            if ($payloadKey === 'idstare' || $column === 'stare') {
                $column = 'stare';
                $value = (int) $value;
            }
            $fields[] = "{$column} = ?";
            $values[] = $value;
        }

        if ($fields === []) {
            throw new \InvalidArgumentException('Nimic de actualizat.');
        }

        $values[] = $orderId;
        $stmt = $this->pdo->prepare('UPDATE ' . $table . ' SET ' . implode(', ', $fields) . ' WHERE idcomanda = ?');
        $stmt->execute($values);

        return $this->getOrder($orderId, $sourceType);
    }

    /** @param array<string, mixed> $payload */
    public function addLine(int $orderId, string $sourceType, array $payload): array
    {
        $sourceType = $this->normalizeSourceType($sourceType);
        $lineTable = $sourceType === 'externa' ? 'detaliu_ext' : 'detaliu';
        $productId = (int) ($payload['id_produs'] ?? $payload['idprodus'] ?? 0);
        if ($productId <= 0) {
            throw new \InvalidArgumentException('id_produs invalid.');
        }

        $lineColumns = [
            'idcomanda' => $orderId,
            'idprodus' => $productId,
            'cantitate' => max(1, (int) ($payload['cantitate'] ?? $payload['qty'] ?? 1)),
            'pret' => max(0, (float) ($payload['pret'] ?? $payload['price'] ?? 0)),
            'furnizor' => (string) ($payload['furnizor'] ?? '__'),
            'culoare' => (string) ($payload['culoare'] ?? 'FFFFFF'),
            'created_at' => time() + 7200,
        ];
        if ($this->columnExists($lineTable, 'iddetaliu')) {
            $lineColumns = ['iddetaliu' => $this->nextId($lineTable, 'iddetaliu')] + $lineColumns;
        }

        $this->insertRow($lineTable, $lineColumns);
        $this->recalculateTotal($orderId, $sourceType);

        return $this->getOrder($orderId, $sourceType);
    }

    /** @param array<string, mixed> $payload */
    public function updateLine(int $orderId, string $sourceType, int $lineId, array $payload): array
    {
        $sourceType = $this->normalizeSourceType($sourceType);
        $lineTable = $sourceType === 'externa' ? 'detaliu_ext' : 'detaliu';

        $fields = [];
        $values = [];
        foreach (['cantitate', 'pret', 'furnizor', 'culoare', 'idprodus'] as $column) {
            if (!array_key_exists($column, $payload)) {
                continue;
            }
            $fields[] = "{$column} = ?";
            $values[] = $column === 'cantitate' ? max(1, (int) $payload[$column]) : $payload[$column];
        }
        if ($fields === []) {
            throw new \InvalidArgumentException('Nimic de actualizat pe linie.');
        }

        $values[] = $lineId;
        $values[] = $orderId;
        $stmt = $this->pdo->prepare("UPDATE {$lineTable} SET " . implode(', ', $fields) . ' WHERE iddetaliu = ? AND idcomanda = ?');
        $stmt->execute($values);
        $this->recalculateTotal($orderId, $sourceType);

        return $this->getOrder($orderId, $sourceType);
    }

    public function deleteLine(int $orderId, string $sourceType, int $lineId): array
    {
        $sourceType = $this->normalizeSourceType($sourceType);
        $lineTable = $sourceType === 'externa' ? 'detaliu_ext' : 'detaliu';
        $stmt = $this->pdo->prepare("DELETE FROM {$lineTable} WHERE iddetaliu = ? AND idcomanda = ?");
        $stmt->execute([$lineId, $orderId]);
        $this->recalculateTotal($orderId, $sourceType);

        return $this->getOrder($orderId, $sourceType);
    }

    /** @return array<int, array{value:int,label:string}> */
    public static function statusOptions(): array
    {
        return [
            ['value' => 1, 'label' => 'Comandat'],
            ['value' => 2, 'label' => 'Sosit'],
            ['value' => 3, 'label' => 'Expediat'],
            ['value' => 4, 'label' => 'Achitat'],
        ];
    }

    /** @return array<int, array{value:int,label:string}> */
    public static function locationOptions(): array
    {
        return [
            ['value' => 1, 'label' => 'Timișoara'],
            ['value' => 2, 'label' => 'Utvin'],
            ['value' => 3, 'label' => 'Externe'],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function createFromTmpTables(string $sessionId, int $userId, array $payload, string $sourceType): array
    {
        $this->assertPdo();

        $clientId = (int) ($payload['id_client'] ?? $payload['idclient'] ?? 0);
        $status = (int) ($payload['idstare'] ?? $payload['stare'] ?? 1);
        $orderDate = $this->normalizeDate((string) ($payload['data'] ?? date('Y-m-d')));
        $marca = trim((string) ($payload['marca'] ?? ''));
        $machineId = (int) ($payload['idmasina_cmd'] ?? $payload['idmasina'] ?? 0);
        $observations = trim((string) ($payload['observations'] ?? ''));
        $awbAccount = trim((string) ($payload['cont_awb'] ?? 'Utvin'));
        $location = (int) ($payload['locatie_mgz'] ?? 1);

        if ($clientId <= 0) {
            throw new \InvalidArgumentException('Clientul este obligatoriu.');
        }
        if (!$this->clientExists($clientId)) {
            throw new RuntimeException('Client ERP inexistent.');
        }

        $machineId = $this->resolveMachineId($marca, $machineId);
        $tmpProducts = $this->tmpService->listProducts($sessionId)['products'];
        if ($tmpProducts === []) {
            throw new RuntimeException('Nu există produse în coșul tmp.');
        }

        $total = 0.0;
        foreach ($tmpProducts as $product) {
            $total += ((float) ($product['cantitate_tmp'] ?? 0)) * ((float) ($product['pret_tmp'] ?? 0));
        }
        $total = round($total, 2);

        $orderId = $sourceType === 'externa' ? $this->nextOrderId('comenzi_ext') : $this->nextOrderId('comenzi');
        $createdAt = time() + 7200;
        $headerTable = $sourceType === 'externa' ? 'comenzi_ext' : 'comenzi';
        $lineTable = $sourceType === 'externa' ? 'detaliu_ext' : 'detaliu';
        $lastProductId = (int) ($tmpProducts[count($tmpProducts) - 1]['id_produs'] ?? 0);
        $lastQty = (int) ($tmpProducts[count($tmpProducts) - 1]['cantitate_tmp'] ?? 1);

        $this->pdo->beginTransaction();
        try {
            if ($sourceType === 'externa') {
                $headerColumns = [
                    'idcomanda' => $orderId,
                    'idclient' => $clientId,
                    'userid' => max(1, $userId),
                    'idprodus' => $lastProductId,
                    'cantitate' => $lastQty,
                    'total' => $total,
                    'idmasina' => $machineId,
                    'stare' => $status,
                    'retur' => 1,
                    'data' => $orderDate,
                    'awb' => '___',
                    'cont_awb' => $awbAccount !== '' ? $awbAccount : 'Utvin',
                    'observations' => $observations,
                    'created_at' => $createdAt,
                ];
                if ($this->columnExists('comenzi_ext', 'idcmd')) {
                    $headerColumns = ['idcmd' => $this->nextId('comenzi_ext', 'idcmd')] + $headerColumns;
                }
            } else {
                $headerColumns = [
                    'idcomanda' => $orderId,
                    'idclient' => $clientId,
                    'userid' => max(1, $userId),
                    'data' => $orderDate,
                    'idmasina' => $machineId,
                    'total' => $total,
                    'stare' => $status,
                    'cont_awb' => $awbAccount,
                    'observations' => $observations,
                    'locatie_mgz' => $location,
                    'created_at' => $createdAt,
                ];
                if ($this->columnExists('comenzi', 'idcmd')) {
                    $headerColumns = ['idcmd' => $this->nextId('comenzi', 'idcmd')] + $headerColumns;
                }
            }

            $this->insertRow($headerTable, $headerColumns);

            foreach ($tmpProducts as $product) {
                $lineColumns = [
                    'idcomanda' => $orderId,
                    'idprodus' => (int) ($product['id_produs'] ?? 0),
                    'cantitate' => (int) ($product['cantitate_tmp'] ?? 1),
                    'pret' => (float) ($product['pret_tmp'] ?? 0),
                    'furnizor' => (string) ($product['furnizor'] ?? '__'),
                    'culoare' => (string) (($product['culoare'] ?? '') !== '' ? $product['culoare'] : 'FFFFFF'),
                    'created_at' => $createdAt,
                ];
                if ($this->columnExists($lineTable, 'iddetaliu')) {
                    $lineColumns = ['iddetaliu' => $this->nextId($lineTable, 'iddetaliu')] + $lineColumns;
                }
                $this->insertRow($lineTable, $lineColumns);
            }

            $this->tmpService->clear($sessionId);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return [
            'idcomanda' => $orderId,
            'source_type' => $sourceType,
            'total' => $total,
            'lines' => count($tmpProducts),
            'locatie_mgz' => $sourceType === 'externa' ? 3 : $location,
            'redirect_tab' => $sourceType === 'externa' ? 'ext' : ($location === 2 ? 'utvin' : 'tm'),
        ];
    }

    private function recalculateTotal(int $orderId, string $sourceType): void
    {
        $sourceType = $this->normalizeSourceType($sourceType);
        $lineTable = $sourceType === 'externa' ? 'detaliu_ext' : 'detaliu';
        $headerTable = $sourceType === 'externa' ? 'comenzi_ext' : 'comenzi';

        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(cantitate * pret), 0) FROM {$lineTable} WHERE idcomanda = ?");
        $stmt->execute([$orderId]);
        $total = round((float) $stmt->fetchColumn(), 2);

        $upd = $this->pdo->prepare("UPDATE {$headerTable} SET total = ? WHERE idcomanda = ?");
        $upd->execute([$total, $orderId]);
    }

    /** @param array<string, mixed> $row */
    private function insertRow(string $table, array $row): void
    {
        $columns = array_keys($row);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = $this->pdo->prepare('INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')');
        $stmt->execute(array_values($row));
    }

    private function normalizeSourceType(string $sourceType): string
    {
        $sourceType = strtolower(trim($sourceType));

        return $sourceType === 'externa' || $sourceType === 'external' ? 'externa' : 'interna';
    }

    private function normalizeDate(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return date('Y-m-d');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }
        $dt = \DateTime::createFromFormat('d/m/Y', $raw);
        if ($dt instanceof \DateTime) {
            return $dt->format('Y-m-d');
        }
        $ts = strtotime($raw);

        return $ts !== false ? date('Y-m-d', $ts) : date('Y-m-d');
    }

    private function resolveMachineId(string $marca, int $machineId): int
    {
        if ($machineId > 1) {
            return $machineId;
        }
        if ($marca === '' || !$this->tableExists('masina')) {
            return 1;
        }
        $stmt = $this->pdo->prepare('SELECT idmasina FROM masina WHERE marca LIKE ? LIMIT 1');
        $stmt->execute(['%' . $marca . '%']);
        $found = (int) ($stmt->fetchColumn() ?: 0);
        if ($found > 0) {
            return $found;
        }
        try {
            $insert = $this->pdo->prepare('INSERT INTO masina (marca, sasiu, nrmat) VALUES (?, ?, ?)');
            $insert->execute([$marca, '', '']);

            return (int) $this->pdo->lastInsertId();
        } catch (\Throwable) {
            return 1;
        }
    }

    private function nextOrderId(string $table): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(MAX(idcomanda), 0) + 1 FROM ' . $table);

        return max(1, (int) $stmt->fetchColumn());
    }

    private function nextId(string $table, string $column): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(MAX(' . $column . '), 0) + 1 FROM ' . $table);

        return max(1, (int) $stmt->fetchColumn());
    }

    private function clientExists(int $clientId): bool
    {
        if (!$this->tableExists('clienti')) {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT idclienti FROM clienti WHERE idclienti = ? LIMIT 1');
        $stmt->execute([$clientId]);

        return (bool) $stmt->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    }

    private function assertPdo(): void
    {
        if ($this->pdo === null) {
            throw new RuntimeException('Conexiune legacy indisponibilă (LEGACY_DB_*).');
        }
    }

    private function assertInternalTables(): void
    {
        $this->assertPdo();
        if (!$this->tableExists('comenzi') || !$this->tableExists('detaliu')) {
            throw new RuntimeException('Tabelele comenzi/detaliu lipsesc.');
        }
    }

    private function assertExternalTables(): void
    {
        $this->assertPdo();
        if (!$this->tableExists('comenzi_ext') || !$this->tableExists('detaliu_ext')) {
            throw new RuntimeException('Tabelele comenzi_ext/detaliu_ext lipsesc.');
        }
    }
}
