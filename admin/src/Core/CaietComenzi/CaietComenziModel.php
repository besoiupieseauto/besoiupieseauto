<?php

declare(strict_types=1);

namespace Evasystem\Core\CaietComenzi;

use Config\Database;
use PDO;

final class CaietComenziModel
{
    /** @var array<int, string> */
    private const STATUS_LABELS = [
        0 => 'Necunoscut',
        1 => 'Comandat',
        2 => 'Sosit',
        3 => 'Cash',
        4 => 'Avans',
        5 => 'Retur',
        6 => 'Card',
        7 => 'FD',
        8 => 'Anulat',
        9 => 'Facturat',
        10 => 'Pregatire',
        11 => 'Livrare',
        12 => 'In verificare',
    ];

    private PDO $legacyDb;

    public function __construct(?PDO $legacyDb = null)
    {
        $this->legacyDb = $legacyDb ?? Database::getDB('legacy');
    }

    /**
     * @return array<string, int|float|string>
     */
    public function getStats(): array
    {
        $today = date('Y-m-d');

        $internalTotal = (int) $this->legacyDb->query('SELECT COUNT(*) FROM comenzi')->fetchColumn();
        $externalTotal = (int) $this->legacyDb->query('SELECT COUNT(*) FROM comenzi_ext')->fetchColumn();
        $todayInternal = $this->countByDate('comenzi', $today);
        $todayExternal = $this->countByDate('comenzi_ext', $today);

        $todayRevenueInternal = $this->sumByDate('comenzi', $today);
        $todayRevenueExternal = $this->sumByDate('comenzi_ext', $today);

        return [
            'total_orders' => $internalTotal + $externalTotal,
            'total_internal' => $internalTotal,
            'total_external' => $externalTotal,
            'today_orders' => $todayInternal + $todayExternal,
            'today_revenue' => round($todayRevenueInternal + $todayRevenueExternal, 2),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function findOrders(array $filters = []): array
    {
        $limit = $this->resolveLimit($filters['limit'] ?? null);
        $location = trim((string) ($filters['location'] ?? ''));
        $sourceType = trim((string) ($filters['source_type'] ?? ''));

        if ($location === 'externa') {
            $sourceType = 'externa';
        }

        if ($sourceType === 'externa') {
            return $this->findOrdersFromTable('externa', 'comenzi_ext', $filters, $limit);
        }

        if ($sourceType === 'interna' || ($location !== '' && $location !== 'toate')) {
            return $this->findOrdersFromTable('interna', 'comenzi', $filters, $limit);
        }

        $half = (int) ceil($limit / 2);
        $internal = $this->findOrdersFromTable('interna', 'comenzi', $filters, $half);
        $external = $this->findOrdersFromTable('externa', 'comenzi_ext', $filters, $half);

        return $this->mergeOrderRows($internal, $external, $limit);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function findOrdersFromTable(string $sourceType, string $table, array $filters, int $limit): array
    {
        $params = [];
        $where = ['1 = 1'];
        $location = trim((string) ($filters['location'] ?? ''));

        if ($sourceType === 'interna' && $location !== '' && $location !== 'toate' && $location !== 'externa') {
            $locationFilter = $this->buildComenziLocationFilter($location, 'c.locatie_mgz', 'orders');
            if ($locationFilter['sql'] !== '') {
                $where[] = ltrim($locationFilter['sql'], ' AND ');
            }
            $params = array_merge($params, $locationFilter['params']);
        }

        if (isset($filters['status']) && $filters['status'] !== '' && is_numeric($filters['status'])) {
            $params[':status'] = (int) $filters['status'];
            $where[] = 'c.stare = :status';
        }

        $orderDate = trim((string) ($filters['order_date'] ?? ''));
        if ($orderDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $orderDate) === 1) {
            $params[':order_date'] = $orderDate;
            $where[] = 'c.data = :order_date';
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
            $params[':date_from'] = $dateFrom;
            $where[] = 'c.data >= :date_from';
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
            $params[':date_to'] = $dateTo;
            $where[] = 'c.data <= :date_to';
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $params[':search'] = '%' . $search . '%';
            $awbClause = $sourceType === 'externa' ? ' OR c.awb LIKE :search' : '';
            $where[] = '(cl.nume LIKE :search OR cl.telefon LIKE :search OR CAST(c.idcomanda AS CHAR) LIKE :search' . $awbClause . ')';
        }

        $awbSelect = $sourceType === 'externa' ? "COALESCE(c.awb, '')" : "''";
        $locationSelect = $sourceType === 'interna' ? "COALESCE(c.locatie_mgz, '')" : "''";

        $sql = "
            SELECT
                '{$sourceType}' AS source_type,
                c.idcomanda AS order_id,
                c.idcmd AS record_id,
                c.idclient AS client_id,
                COALESCE(cl.nume, '-') AS client_name,
                COALESCE(cl.telefon, '-') AS client_phone,
                COALESCE(c.stare, 0) AS status,
                COALESCE(c.total, 0) AS total_amount,
                c.data AS order_date,
                c.created_at AS created_raw,
                {$awbSelect} AS awb,
                COALESCE(c.observations, '') AS observations,
                {$locationSelect} AS location,
                COALESCE(cl.marca, '') AS marca
            FROM {$table} c
            LEFT JOIN clienti cl ON cl.idclienti = c.idclient
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.data DESC, c.idcomanda DESC
            LIMIT {$limit}
        ";

        $statement = $this->legacyDb->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $this->attachLineCounts($rows, $sourceType);

        return $this->normalizeOrderRows($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function attachLineCounts(array &$rows, string $sourceType): void
    {
        if ($rows === []) {
            return;
        }

        $ids = [];
        foreach ($rows as $row) {
            $orderId = (int) ($row['order_id'] ?? 0);
            if ($orderId > 0) {
                $ids[] = $orderId;
            }
        }

        if ($ids === []) {
            return;
        }

        $detailTable = $sourceType === 'externa' ? 'detaliu_ext' : 'detaliu';
        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $orderId) {
            $key = ':oid_' . $index;
            $placeholders[] = $key;
            $params[$key] = $orderId;
        }

        $sql = 'SELECT idcomanda, COUNT(*) AS lines_count, COALESCE(SUM(cantitate), 0) AS quantity_total
                FROM ' . $detailTable . '
                WHERE idcomanda IN (' . implode(', ', $placeholders) . ')
                GROUP BY idcomanda';

        $statement = $this->legacyDb->prepare($sql);
        $statement->execute($params);

        $counts = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $counts[(int) ($row['idcomanda'] ?? 0)] = $row;
        }

        foreach ($rows as &$row) {
            $orderId = (int) ($row['order_id'] ?? 0);
            $row['lines_count'] = (int) ($counts[$orderId]['lines_count'] ?? 0);
            $row['quantity_total'] = (float) ($counts[$orderId]['quantity_total'] ?? 0);
        }
        unset($row);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeOrderRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $statusCode = (int) ($row['status'] ?? 0);
            $row['status'] = $statusCode;
            $row['status_label'] = $this->statusLabel($statusCode);
            $row['total_amount'] = round((float) ($row['total_amount'] ?? 0), 2);
            $row['quantity_total'] = (float) ($row['quantity_total'] ?? 0);
            $row['lines_count'] = (int) ($row['lines_count'] ?? 0);
            $row['created_at'] = $this->normalizeCreatedAt($row['created_raw'] ?? null, (string) ($row['order_date'] ?? ''));
            unset($row['created_raw']);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $internal
     * @param array<int, array<string, mixed>> $external
     * @return array<int, array<string, mixed>>
     */
    private function mergeOrderRows(array $internal, array $external, int $limit): array
    {
        $merged = array_merge($internal, $external);
        usort($merged, static function (array $a, array $b): int {
            $dateCompare = strcmp((string) ($b['order_date'] ?? ''), (string) ($a['order_date'] ?? ''));
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return ((int) ($b['order_id'] ?? 0)) <=> ((int) ($a['order_id'] ?? 0));
        });

        return array_slice($merged, 0, $limit);
    }

    /**
     * @return array<string, int|float>
     */
    public function getStatsByLocation(string $location): array
    {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');

        if ($location === 'externa') {
            $totalMonth = $this->countByDateRange('comenzi_ext', $monthStart, $today);
            $totalDay = $this->countByDate('comenzi_ext', $today);
            $revenueMonth = $this->sumByDateRange('comenzi_ext', $monthStart, $today);
            return [
                'total_month' => $totalMonth,
                'total_day' => $totalDay,
                'revenue_month' => round($revenueMonth, 2),
            ];
        }

        $locationFilterMonth = $this->buildComenziLocationFilter($location, 'locatie_mgz', 'stats_month');
        $locationFilterDay = $this->buildComenziLocationFilter($location, 'locatie_mgz', 'stats_day');
        $locationFilterRevenue = $this->buildComenziLocationFilter($location, 'locatie_mgz', 'stats_revenue');

        $stmt = $this->legacyDb->prepare(
            "SELECT COUNT(*) FROM comenzi WHERE data >= :from_date AND data <= :to_date" . $locationFilterMonth['sql']
        );
        $stmt->execute(array_merge([':from_date' => $monthStart, ':to_date' => $today], $locationFilterMonth['params']));
        $totalMonth = (int) $stmt->fetchColumn();

        $stmt = $this->legacyDb->prepare(
            "SELECT COUNT(*) FROM comenzi WHERE data = :today" . $locationFilterDay['sql']
        );
        $stmt->execute(array_merge([':today' => $today], $locationFilterDay['params']));
        $totalDay = (int) $stmt->fetchColumn();

        $stmt = $this->legacyDb->prepare(
            "SELECT COALESCE(SUM(total), 0) FROM comenzi WHERE data >= :from_date AND data <= :to_date" . $locationFilterRevenue['sql']
        );
        $stmt->execute(array_merge([':from_date' => $monthStart, ':to_date' => $today], $locationFilterRevenue['params']));
        $revenueMonth = (float) $stmt->fetchColumn();

        return [
            'total_month' => $totalMonth,
            'total_day' => $totalDay,
            'revenue_month' => round($revenueMonth, 2),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findClients(array $filters = []): array
    {
        $params = [];
        $where = [];
        $limit = $this->resolveLimit($filters['limit'] ?? 100);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $params[':search'] = '%' . $search . '%';
            $where[] = '(cl.nume LIKE :search OR cl.telefon LIKE :search OR cl.adresa LIKE :search OR cl.companie LIKE :search OR cl.cif LIKE :search)';
        }

        $sql = "SELECT cl.idclienti, cl.nume, cl.telefon, '' AS email, cl.adresa, cl.companie, cl.cif,
                       cl.marca, cl.sasiu, cl.nr_inmat,
                       cl.localitate_livrare, cl.adresa_livrare, cl.localitate_facturare, cl.adresa_facturare,
                       cl.created_at,
                       COUNT(DISTINCT c.idcmd) AS total_orders_internal,
                       COUNT(DISTINCT ce.idcmd) AS total_orders_external
                FROM clienti cl
                LEFT JOIN comenzi c ON c.idclient = cl.idclienti
                LEFT JOIN comenzi_ext ce ON ce.idclient = cl.idclienti";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY cl.idclienti, cl.nume, cl.telefon, cl.adresa, cl.companie, cl.cif, cl.marca, cl.sasiu, cl.nr_inmat, cl.localitate_livrare, cl.adresa_livrare, cl.localitate_facturare, cl.adresa_facturare, cl.created_at
                  ORDER BY cl.idclienti DESC LIMIT ' . $limit;

        $stmt = $this->legacyDb->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findProduse(array $filters = []): array
    {
        if ($this->tableExists('produse')) {
            return $this->findProduseFromLegacyProduse($filters);
        }

        if (!$this->tableExists('parts_catalog')) {
            return [];
        }

        $params = [];
        $where = [];
        $limit = $this->resolveLimit($filters['limit'] ?? 100);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $params[':search'] = '%' . $search . '%';
            $where[] = '(p.mainart_brands LIKE :search OR p.mainart_code_parts LIKE :search OR p.brands LIKE :search OR p.code_parts LIKE :search)';
        }

        $sql = "SELECT
                    p.id AS idprodus,
                    TRIM(CONCAT(COALESCE(p.mainart_brands, ''), ' ', COALESCE(p.mainart_code_parts, ''))) AS denumire,
                    COALESCE(NULLIF(p.mainart_code_parts, ''), p.code_parts, '-') AS cod_produs,
                    0 AS pret,
                    '' AS TVA,
                    '' AS um,
                    0 AS created_at
                FROM parts_catalog p";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY p.id DESC LIMIT ' . $limit;

        $stmt = $this->legacyDb->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findFacturi(array $filters = []): array
    {
        $params = [];
        $where = [];
        $limit = $this->resolveLimit($filters['limit'] ?? 100);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $params[':search'] = '%' . $search . '%';
            $where[] = '(f.seria LIKE :search OR cl.nume LIKE :search OR CAST(f.OrderID AS CHAR) LIKE :search)';
        }

        $tipComanda = strtolower(trim((string) ($filters['tip_comanda'] ?? '')));
        if ($tipComanda !== '') {
            if ($tipComanda === 'interna') {
                $where[] = '(f.tip_comanda = 0 OR f.tip_comanda = 1)';
            } elseif ($tipComanda === 'externa') {
                $where[] = 'f.tip_comanda = 2';
            } elseif (is_numeric($tipComanda)) {
                $params[':tip_comanda'] = (int) $tipComanda;
                $where[] = 'f.tip_comanda = :tip_comanda';
            }
        }

        $sql = "SELECT f.OrderID, f.CustomerID, f.EmployeeID, f.OrderDate, f.RequiredDate,
                       f.seria, f.valid, f.tip_incas, f.id_comanda, f.tip_comanda,
                       f.id_chitanta, f.id_oferta, f.id_proforma, f.id_aviz, f.id_fact,
                       f.negative_issued, f.created_at,
                       COALESCE(cl.nume, '-') AS client_name,
                       COALESCE(cl.telefon, '-') AS client_phone
                FROM facturi f
                LEFT JOIN clienti cl ON cl.idclienti = f.CustomerID";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY f.OrderID DESC LIMIT ' . $limit;

        $stmt = $this->legacyDb->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findIncasari(array $filters = []): array
    {
        $params = [];
        $where = [];
        $limit = $this->resolveLimit($filters['limit'] ?? 100);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $params[':search'] = '%' . $search . '%';
            $where[] = '(i.cstmtext LIKE :search OR cl.nume LIKE :search)';
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
            $params[':date_from'] = $dateFrom;
            $where[] = 'i.data >= :date_from';
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
            $params[':date_to'] = $dateTo;
            $where[] = 'i.data <= :date_to';
        }

        $sql = "SELECT i.id, i.idcmd, i.userid, i.idclient, i.cstmtext, i.suma, i.data, i.data_time,
                       i.idstare, i.locatie_mgz,
                       COALESCE(cl.nume, '-') AS client_name
                FROM incasari i
                LEFT JOIN clienti cl ON cl.idclienti = i.idclient";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY i.id DESC LIMIT ' . $limit;

        $stmt = $this->legacyDb->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function saveClient(array $payload): array
    {
        $id = isset($payload['idclienti']) && is_numeric($payload['idclienti']) ? (int) $payload['idclienti'] : 0;
        $nume = trim((string) ($payload['nume'] ?? ''));
        if ($nume === '') {
            throw new \InvalidArgumentException('Numele clientului este obligatoriu.');
        }

        $data = [
            'nume' => $nume,
            'telefon' => trim((string) ($payload['telefon'] ?? '')),
            'adresa' => trim((string) ($payload['adresa'] ?? '')),
            'companie' => trim((string) ($payload['companie'] ?? '')),
            'cif' => trim((string) ($payload['cif'] ?? '')),
            'marca' => trim((string) ($payload['marca'] ?? '')),
            'sasiu' => trim((string) ($payload['sasiu'] ?? '')),
            'nr_inmat' => trim((string) ($payload['nr_inmat'] ?? '')),
        ];

        if ($id > 0) {
            $stmt = $this->legacyDb->prepare(
                'UPDATE clienti
                 SET nume = :nume, telefon = :telefon, adresa = :adresa, companie = :companie, cif = :cif,
                     marca = :marca, sasiu = :sasiu, nr_inmat = :nr_inmat
                 WHERE idclienti = :id'
            );
            $stmt->execute([
                ':id' => $id,
                ':nume' => $data['nume'],
                ':telefon' => $data['telefon'] !== '' ? $data['telefon'] : null,
                ':adresa' => $data['adresa'] !== '' ? $data['adresa'] : null,
                ':companie' => $data['companie'] !== '' ? $data['companie'] : null,
                ':cif' => $data['cif'] !== '' ? $data['cif'] : null,
                ':marca' => $data['marca'] !== '' ? $data['marca'] : null,
                ':sasiu' => $data['sasiu'] !== '' ? $data['sasiu'] : null,
                ':nr_inmat' => $data['nr_inmat'] !== '' ? $data['nr_inmat'] : null,
            ]);

            return ['idclienti' => $id] + $data;
        }

        $newId = $this->nextId('clienti', 'idclienti');
        $stmt = $this->legacyDb->prepare(
            'INSERT INTO clienti (
                idclienti, nume, telefon, adresa, companie, cif, marca, sasiu, nr_inmat, created_at
            ) VALUES (
                :id, :nume, :telefon, :adresa, :companie, :cif, :marca, :sasiu, :nr_inmat, :created_at
            )'
        );
        $stmt->execute([
            ':id' => $newId,
            ':nume' => $data['nume'],
            ':telefon' => $data['telefon'] !== '' ? $data['telefon'] : null,
            ':adresa' => $data['adresa'] !== '' ? $data['adresa'] : null,
            ':companie' => $data['companie'] !== '' ? $data['companie'] : null,
            ':cif' => $data['cif'] !== '' ? $data['cif'] : null,
            ':marca' => $data['marca'] !== '' ? $data['marca'] : null,
            ':sasiu' => $data['sasiu'] !== '' ? $data['sasiu'] : null,
            ':nr_inmat' => $data['nr_inmat'] !== '' ? $data['nr_inmat'] : null,
            ':created_at' => time(),
        ]);

        return ['idclienti' => $newId] + $data;
    }

    public function deleteClient(int $id): bool
    {
        $stmt = $this->legacyDb->prepare('DELETE FROM clienti WHERE idclienti = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function saveProduct(array $payload): array
    {
        if ($this->tableExists('produse')) {
            return $this->saveProductInProduse($payload);
        }

        return $this->saveProductInPartsCatalog($payload);
    }

    public function deleteProduct(int $id): bool
    {
        $table = $this->tableExists('produse') ? 'produse' : 'parts_catalog';
        $idColumn = $table === 'produse' ? 'idprodus' : 'id';
        $stmt = $this->legacyDb->prepare("DELETE FROM {$table} WHERE {$idColumn} = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function updateAllProductsTva(float $value): int
    {
        if (!$this->tableExists('produse')) {
            return 0;
        }

        $stmt = $this->legacyDb->prepare('UPDATE produse SET TVA = :tva');
        $stmt->execute([':tva' => $value]);
        return $stmt->rowCount();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function saveFactura(array $payload): array
    {
        $id = isset($payload['OrderID']) && is_numeric($payload['OrderID']) ? (int) $payload['OrderID'] : 0;
        $customerId = isset($payload['CustomerID']) && is_numeric($payload['CustomerID']) ? (int) $payload['CustomerID'] : null;
        $orderDate = trim((string) ($payload['OrderDate'] ?? date('Y-m-d')));
        $requiredDate = trim((string) ($payload['RequiredDate'] ?? $orderDate));
        $seria = trim((string) ($payload['seria'] ?? 'BPA_CAI'));
        $tipIncas = isset($payload['tip_incas']) && $payload['tip_incas'] !== '' && is_numeric($payload['tip_incas']) ? (int) $payload['tip_incas'] : null;
        $tipComandaRaw = $payload['tip_comanda'] ?? null;
        $tipComanda = $this->normalizeTipComanda($tipComandaRaw);
        $valid = trim((string) ($payload['valid'] ?? 'Da'));

        if ($id > 0) {
            $stmt = $this->legacyDb->prepare(
                'UPDATE facturi
                 SET CustomerID = :customer_id, OrderDate = :order_date, RequiredDate = :required_date,
                     seria = :seria, tip_incas = :tip_incas, tip_comanda = :tip_comanda, valid = :valid
                 WHERE OrderID = :id'
            );
            $stmt->execute([
                ':id' => $id,
                ':customer_id' => $customerId,
                ':order_date' => $orderDate,
                ':required_date' => $requiredDate !== '' ? $requiredDate : null,
                ':seria' => $seria !== '' ? $seria : 'BPA_CAI',
                ':tip_incas' => $tipIncas,
                ':tip_comanda' => $tipComanda,
                ':valid' => $valid,
            ]);

            return [
                'OrderID' => $id,
                'CustomerID' => $customerId,
                'OrderDate' => $orderDate,
                'RequiredDate' => $requiredDate,
                'seria' => $seria,
                'tip_incas' => $tipIncas,
                'tip_comanda' => $tipComanda,
                'valid' => $valid,
            ];
        }

        $newId = $this->nextId('facturi', 'OrderID');
        $stmt = $this->legacyDb->prepare(
            'INSERT INTO facturi (
                OrderID, CustomerID, OrderDate, RequiredDate, seria, valid,
                tip_incas, tip_comanda, created_at, generation_method, smartbill_in_cash
            ) VALUES (
                :id, :customer_id, :order_date, :required_date, :seria, :valid,
                :tip_incas, :tip_comanda, :created_at, :generation_method, :smartbill_in_cash
            )'
        );
        $stmt->execute([
            ':id' => $newId,
            ':customer_id' => $customerId,
            ':order_date' => $orderDate,
            ':required_date' => $requiredDate !== '' ? $requiredDate : null,
            ':seria' => $seria !== '' ? $seria : 'BPA_CAI',
            ':valid' => $valid,
            ':tip_incas' => $tipIncas,
            ':tip_comanda' => $tipComanda,
            ':created_at' => time(),
            ':generation_method' => 'manual',
            ':smartbill_in_cash' => 'no',
        ]);

        return [
            'OrderID' => $newId,
            'CustomerID' => $customerId,
            'OrderDate' => $orderDate,
            'RequiredDate' => $requiredDate,
            'seria' => $seria,
            'tip_incas' => $tipIncas,
            'tip_comanda' => $tipComanda,
            'valid' => $valid,
        ];
    }

    public function deleteFactura(int $orderId): bool
    {
        $this->legacyDb->beginTransaction();
        try {
            $stmtLines = $this->legacyDb->prepare('DELETE FROM facturidetails WHERE OrderID = :id');
            $stmtLines->execute([':id' => $orderId]);

            $stmt = $this->legacyDb->prepare('DELETE FROM facturi WHERE OrderID = :id');
            $stmt->execute([':id' => $orderId]);

            $this->legacyDb->commit();
            return $stmt->rowCount() > 0;
        } catch (\Throwable $exception) {
            $this->legacyDb->rollBack();
            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getFacturaDetails(int $orderId): array
    {
        $stmt = $this->legacyDb->prepare(
            'SELECT f.OrderID, f.CustomerID, f.OrderDate, f.RequiredDate, f.seria, f.valid, f.tip_incas, f.tip_comanda,
                    COALESCE(cl.nume, "-") AS client_name, COALESCE(cl.telefon, "-") AS client_phone
             FROM facturi f
             LEFT JOIN clienti cl ON cl.idclienti = f.CustomerID
             WHERE f.OrderID = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $orderId]);
        $header = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$header) {
            return [];
        }

        $linesStmt = $this->legacyDb->prepare(
            'SELECT ProductId, UnitPrice, Quantity, Discount, tva, total
             FROM facturidetails
             WHERE OrderID = :id
             ORDER BY ProductId ASC'
        );
        $linesStmt->execute([':id' => $orderId]);
        $lines = $linesStmt->fetchAll(PDO::FETCH_ASSOC);

        $grandTotal = 0.0;
        foreach ($lines as &$line) {
            $line['UnitPrice'] = round((float) ($line['UnitPrice'] ?? 0), 2);
            $line['Quantity'] = (float) ($line['Quantity'] ?? 0);
            $line['Discount'] = round((float) ($line['Discount'] ?? 0), 2);
            $line['tva'] = round((float) ($line['tva'] ?? 0), 2);
            $line['total'] = round((float) ($line['total'] ?? 0), 2);
            $grandTotal += (float) $line['total'];
        }

        $header['lines_count'] = count($lines);
        $header['grand_total'] = round($grandTotal, 2);
        $header['lines'] = $lines;
        return $header;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function saveIncasare(array $payload, int $userId): array
    {
        $id = isset($payload['id']) && is_numeric($payload['id']) ? (int) $payload['id'] : 0;
        $idClient = isset($payload['idclient']) && is_numeric($payload['idclient']) ? (int) $payload['idclient'] : 0;
        $idCmd = isset($payload['idcmd']) && is_numeric($payload['idcmd']) ? (int) $payload['idcmd'] : 0;
        $idStare = isset($payload['idstare']) && is_numeric($payload['idstare']) ? (int) $payload['idstare'] : 1;
        $suma = (float) ($payload['suma'] ?? 0);
        if ($suma <= 0) {
            throw new \InvalidArgumentException('Suma trebuie sa fie mai mare ca 0.');
        }

        $data = [
            'idcmd' => $idCmd,
            'userid' => $userId > 0 ? $userId : 0,
            'idstare' => $idStare,
            'suma' => round($suma, 2),
            'data' => trim((string) ($payload['data'] ?? date('Y-m-d'))),
            'data_time' => trim((string) ($payload['data_time'] ?? date('H:i:s'))),
            'idclient' => $idClient,
            'cstmtext' => trim((string) ($payload['cstmtext'] ?? '')),
            'locatie_mgz' => isset($payload['locatie_mgz']) && is_numeric($payload['locatie_mgz']) ? (int) $payload['locatie_mgz'] : 1,
        ];

        if ($id > 0) {
            $stmt = $this->legacyDb->prepare(
                'UPDATE incasari
                 SET idcmd = :idcmd, userid = :userid, idstare = :idstare, suma = :suma, data = :data, data_time = :data_time,
                     idclient = :idclient, cstmtext = :cstmtext, locatie_mgz = :locatie_mgz
                 WHERE id = :id'
            );
            $stmt->execute([
                ':id' => $id,
                ':idcmd' => $data['idcmd'],
                ':userid' => $data['userid'],
                ':idstare' => $data['idstare'],
                ':suma' => $data['suma'],
                ':data' => $data['data'],
                ':data_time' => $data['data_time'],
                ':idclient' => $data['idclient'],
                ':cstmtext' => $data['cstmtext'] !== '' ? $data['cstmtext'] : null,
                ':locatie_mgz' => $data['locatie_mgz'],
            ]);

            return ['id' => $id] + $data;
        }

        $newId = $this->nextId('incasari', 'id');
        $stmt = $this->legacyDb->prepare(
            'INSERT INTO incasari (
                id, idcmd, userid, idstare, suma, data, data_time, idclient, cstmtext, locatie_mgz
            ) VALUES (
                :id, :idcmd, :userid, :idstare, :suma, :data, :data_time, :idclient, :cstmtext, :locatie_mgz
            )'
        );
        $stmt->execute([
            ':id' => $newId,
            ':idcmd' => $data['idcmd'],
            ':userid' => $data['userid'],
            ':idstare' => $data['idstare'],
            ':suma' => $data['suma'],
            ':data' => $data['data'],
            ':data_time' => $data['data_time'],
            ':idclient' => $data['idclient'],
            ':cstmtext' => $data['cstmtext'] !== '' ? $data['cstmtext'] : null,
            ':locatie_mgz' => $data['locatie_mgz'],
        ]);

        return ['id' => $newId] + $data;
    }

    public function deleteIncasare(int $id): bool
    {
        $stmt = $this->legacyDb->prepare('DELETE FROM incasari WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @return array<string, int|float>
     */
    public function getDailyCash(): array
    {
        $todayTs = strtotime(date('Y-m-d 00:00:00'));
        $stmt = $this->legacyDb->prepare('SELECT id, amount, date FROM incasari_entries WHERE date = :date LIMIT 1');
        $stmt->execute([':date' => $todayTs]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $latestStmt = $this->legacyDb->query('SELECT id, amount, date FROM incasari_entries ORDER BY id DESC LIMIT 1');
            $row = $latestStmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$row) {
            return ['id' => 0, 'amount' => 0.0, 'date' => $todayTs];
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'amount' => round((float) ($row['amount'] ?? 0), 2),
            'date' => (int) ($row['date'] ?? $todayTs),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    public function updateDailyCash(float $amount): array
    {
        $todayTs = strtotime(date('Y-m-d 00:00:00'));
        $stmt = $this->legacyDb->prepare('SELECT id FROM incasari_entries WHERE date = :date LIMIT 1');
        $stmt->execute([':date' => $todayTs]);
        $existingId = (int) ($stmt->fetchColumn() ?: 0);

        if ($existingId > 0) {
            $update = $this->legacyDb->prepare('UPDATE incasari_entries SET amount = :amount WHERE id = :id');
            $update->execute([':amount' => $amount, ':id' => $existingId]);
            return ['id' => $existingId, 'amount' => round($amount, 2), 'date' => $todayTs];
        }

        $newId = $this->nextId('incasari_entries', 'id');
        $insert = $this->legacyDb->prepare('INSERT INTO incasari_entries (id, amount, date) VALUES (:id, :amount, :date)');
        $insert->execute([':id' => $newId, ':amount' => $amount, ':date' => $todayTs]);

        return ['id' => $newId, 'amount' => round($amount, 2), 'date' => $todayTs];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOrderLines(int $orderId, string $sourceType): array
    {
        $table = $sourceType === 'externa' ? 'detaliu_ext' : 'detaliu';
        $sql = "SELECT iddetaliu, idprodus, cantitate, pret, culoare, furnizor, created_at FROM {$table} WHERE idcomanda = :order_id ORDER BY iddetaliu DESC";

        $statement = $this->legacyDb->prepare($sql);
        $statement->execute([':order_id' => $orderId]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['cantitate'] = (float) ($row['cantitate'] ?? 0);
            $row['pret'] = round((float) ($row['pret'] ?? 0), 2);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function updateOrderStatus(int $orderId, int $newStatus, string $sourceType, int $userId): ?array
    {
        $table = $sourceType === 'externa' ? 'comenzi_ext' : 'comenzi';

        $statement = $this->legacyDb->prepare("SELECT idcomanda, stare FROM {$table} WHERE idcomanda = :order_id LIMIT 1");
        $statement->execute([':order_id' => $orderId]);
        $current = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$current) {
            return null;
        }

        $oldStatus = (int) ($current['stare'] ?? 0);

        $this->legacyDb->beginTransaction();

        try {
            $update = $this->legacyDb->prepare("UPDATE {$table} SET stare = :new_status WHERE idcomanda = :order_id");
            $update->execute([
                ':new_status' => $newStatus,
                ':order_id' => $orderId,
            ]);

            if ($this->tableExists('order_status_history')) {
                $history = $this->legacyDb->prepare(
                    'INSERT INTO order_status_history (order_id, old_status, new_status, user_id, created_at, updated_at)
                     VALUES (:order_id, :old_status, :new_status, :user_id, NOW(), NOW())'
                );
                $history->execute([
                    ':order_id' => $orderId,
                    ':old_status' => $oldStatus,
                    ':new_status' => $newStatus,
                    ':user_id' => $userId,
                ]);
            }

            $this->legacyDb->commit();
        } catch (\Throwable $exception) {
            $this->legacyDb->rollBack();
            throw $exception;
        }

        return [
            'order_id' => $orderId,
            'source_type' => $sourceType,
            'old_status' => $oldStatus,
            'old_status_label' => $this->statusLabel($oldStatus),
            'new_status' => $newStatus,
            'new_status_label' => $this->statusLabel($newStatus),
        ];
    }

    private function countByDate(string $table, string $date): int
    {
        $statement = $this->legacyDb->prepare("SELECT COUNT(*) FROM {$table} WHERE data = :order_date");
        $statement->execute([':order_date' => $date]);

        return (int) $statement->fetchColumn();
    }

    private function countByDateRange(string $table, string $from, string $to): int
    {
        $statement = $this->legacyDb->prepare("SELECT COUNT(*) FROM {$table} WHERE data >= :from_date AND data <= :to_date");
        $statement->execute([':from_date' => $from, ':to_date' => $to]);

        return (int) $statement->fetchColumn();
    }

    private function sumByDate(string $table, string $date): float
    {
        $statement = $this->legacyDb->prepare("SELECT COALESCE(SUM(total), 0) FROM {$table} WHERE data = :order_date");
        $statement->execute([':order_date' => $date]);

        return (float) $statement->fetchColumn();
    }

    private function sumByDateRange(string $table, string $from, string $to): float
    {
        $statement = $this->legacyDb->prepare("SELECT COALESCE(SUM(total), 0) FROM {$table} WHERE data >= :from_date AND data <= :to_date");
        $statement->execute([':from_date' => $from, ':to_date' => $to]);

        return (float) $statement->fetchColumn();
    }

    private function statusLabel(int $statusCode): string
    {
        return self::STATUS_LABELS[$statusCode] ?? ('Status ' . $statusCode);
    }

    private function tableExists(string $tableName): bool
    {
        $statement = $this->legacyDb->prepare('SHOW TABLES LIKE :table_name');
        $statement->execute([':table_name' => $tableName]);

        return $statement->fetch(PDO::FETCH_NUM) !== false;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function findProduseFromLegacyProduse(array $filters): array
    {
        $params = [];
        $where = [];
        $limit = $this->resolveLimit($filters['limit'] ?? 100);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $params[':search'] = '%' . $search . '%';
            $where[] = '(p.denumire LIKE :search OR p.cod_produs LIKE :search)';
        }

        $sql = 'SELECT p.idprodus, p.denumire, p.cod_produs, p.pret, p.TVA, p.um, p.created_at FROM produse p';

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY p.idprodus DESC LIMIT ' . $limit;

        $stmt = $this->legacyDb->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{sql:string, params:array<string, string>}
     */
    private function buildComenziLocationFilter(string $location, string $column, string $prefix): array
    {
        $normalized = strtolower(trim($location));

        if ($normalized === '' || $normalized === 'toate') {
            return ['sql' => '', 'params' => []];
        }

        $variantsMap = [
            'timisoara' => ['1', 'timisoara', 'tm'],
            'tm' => ['1', 'timisoara', 'tm'],
            'utvin' => ['2', 'utvin'],
        ];

        if (isset($variantsMap[$normalized])) {
            $params = [];
            $placeholders = [];
            foreach ($variantsMap[$normalized] as $index => $value) {
                $key = ':' . $prefix . '_loc_' . $index;
                $params[$key] = $value;
                $placeholders[] = $key;
            }

            return [
                'sql' => ' AND LOWER(CAST(' . $column . ' AS CHAR)) IN (' . implode(', ', $placeholders) . ')',
                'params' => $params,
            ];
        }

        $exactKey = ':' . $prefix . '_loc_exact';
        return [
            'sql' => ' AND LOWER(CAST(' . $column . ' AS CHAR)) = ' . $exactKey,
            'params' => [$exactKey => $normalized],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function saveProductInProduse(array $payload): array
    {
        $id = isset($payload['idprodus']) && is_numeric($payload['idprodus']) ? (int) $payload['idprodus'] : 0;
        $denumire = trim((string) ($payload['denumire'] ?? ''));
        if ($denumire === '') {
            throw new \InvalidArgumentException('Denumirea produsului este obligatorie.');
        }

        $data = [
            'denumire' => $denumire,
            'cod_produs' => trim((string) ($payload['cod_produs'] ?? '')),
            'pret' => round((float) ($payload['pret'] ?? 0), 2),
            'TVA' => trim((string) ($payload['TVA'] ?? '')),
            'um' => trim((string) ($payload['um'] ?? '')),
        ];

        if ($id > 0) {
            $stmt = $this->legacyDb->prepare(
                'UPDATE produse
                 SET denumire = :denumire, cod_produs = :cod_produs, pret = :pret, TVA = :tva, um = :um
                 WHERE idprodus = :id'
            );
            $stmt->execute([
                ':id' => $id,
                ':denumire' => $data['denumire'],
                ':cod_produs' => $data['cod_produs'] !== '' ? $data['cod_produs'] : null,
                ':pret' => $data['pret'],
                ':tva' => $data['TVA'] !== '' ? $data['TVA'] : null,
                ':um' => $data['um'] !== '' ? $data['um'] : null,
            ]);

            return ['idprodus' => $id] + $data;
        }

        $newId = $this->nextId('produse', 'idprodus');
        $stmt = $this->legacyDb->prepare(
            'INSERT INTO produse (idprodus, denumire, cod_produs, pret, TVA, um, created_at)
             VALUES (:id, :denumire, :cod_produs, :pret, :tva, :um, :created_at)'
        );
        $stmt->execute([
            ':id' => $newId,
            ':denumire' => $data['denumire'],
            ':cod_produs' => $data['cod_produs'] !== '' ? $data['cod_produs'] : null,
            ':pret' => $data['pret'],
            ':tva' => $data['TVA'] !== '' ? $data['TVA'] : null,
            ':um' => $data['um'] !== '' ? $data['um'] : null,
            ':created_at' => time(),
        ]);

        return ['idprodus' => $newId] + $data;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function saveProductInPartsCatalog(array $payload): array
    {
        $id = isset($payload['idprodus']) && is_numeric($payload['idprodus']) ? (int) $payload['idprodus'] : 0;
        $denumire = trim((string) ($payload['denumire'] ?? ''));
        $cod = trim((string) ($payload['cod_produs'] ?? ''));
        if ($denumire === '' && $cod === '') {
            throw new \InvalidArgumentException('Introdu denumire sau cod produs.');
        }

        $brand = trim((string) ($payload['brand'] ?? ''));
        if ($brand === '') {
            $parts = preg_split('/\s+/', $denumire);
            $brand = strtoupper((string) ($parts[0] ?? 'GENERIC'));
        }
        $brand = substr($brand, 0, 100);
        $code = $cod !== '' ? $cod : strtoupper(preg_replace('/\s+/', '-', $denumire));
        $code = substr((string) $code, 0, 100);

        if ($id > 0) {
            $stmt = $this->legacyDb->prepare(
                'UPDATE parts_catalog
                 SET mainart_brands = :main_brand, mainart_code_parts = :main_code,
                     brands = :brand, code_parts = :code
                 WHERE id = :id'
            );
            $stmt->execute([
                ':id' => $id,
                ':main_brand' => $brand,
                ':main_code' => $code,
                ':brand' => $brand,
                ':code' => $code,
            ]);

            return [
                'idprodus' => $id,
                'denumire' => trim($brand . ' ' . $code),
                'cod_produs' => $code,
                'pret' => 0,
                'TVA' => '',
                'um' => '',
            ];
        }

        $newId = $this->nextId('parts_catalog', 'id');
        $stmt = $this->legacyDb->prepare(
            'INSERT INTO parts_catalog (
                id, mainart_brands, mainart_code_parts, brands, code_parts
            ) VALUES (
                :id, :main_brand, :main_code, :brand, :code
            )'
        );
        $stmt->execute([
            ':id' => $newId,
            ':main_brand' => $brand,
            ':main_code' => $code,
            ':brand' => $brand,
            ':code' => $code,
        ]);

        return [
            'idprodus' => $newId,
            'denumire' => trim($brand . ' ' . $code),
            'cod_produs' => $code,
            'pret' => 0,
            'TVA' => '',
            'um' => '',
        ];
    }

    /**
     * @param mixed $tipComandaRaw
     */
    private function normalizeTipComanda($tipComandaRaw): ?int
    {
        if ($tipComandaRaw === null || $tipComandaRaw === '') {
            return null;
        }

        if (is_numeric($tipComandaRaw)) {
            return (int) $tipComandaRaw;
        }

        $value = strtolower(trim((string) $tipComandaRaw));
        if ($value === 'interna') {
            return 1;
        }
        if ($value === 'externa') {
            return 2;
        }

        return null;
    }

    private function nextId(string $tableName, string $idColumn): int
    {
        $stmt = $this->legacyDb->query("SELECT COALESCE(MAX({$idColumn}), 0) + 1 FROM {$tableName}");
        return (int) $stmt->fetchColumn();
    }

    private function resolveLimit($limit): int
    {
        $value = is_numeric($limit) ? (int) $limit : 120;
        if ($value < 10) {
            return 10;
        }

        if ($value > 500) {
            return 500;
        }

        return $value;
    }

    private function normalizeCreatedAt($raw, string $orderDate): string
    {
        if (is_numeric($raw)) {
            $timestamp = (int) $raw;
            if ($timestamp > 0 && $timestamp < 2147483647) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        }

        if (is_string($raw) && trim($raw) !== '') {
            return trim($raw);
        }

        if ($orderDate !== '') {
            return $orderDate . ' 00:00:00';
        }

        return '';
    }
}

