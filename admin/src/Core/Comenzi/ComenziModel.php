<?php

declare(strict_types=1);

namespace Evasystem\Core\Comenzi;

use Evasystem\Core\AdvancedCRUD;

/**
 * Acces la tabela `comenzi`.
 *
 * Modelul păstrează compatibilitatea cu tabela existentă și nu modifică
 * AdvancedCRUD. Coloanele persistate sunt controlate prin whitelist.
 */
final class ComenziModel
{
    private const TABLE = 'comenzi';
    private const PRIMARY_LOGICAL_KEY = 'randomn_id';

    private const ALLOWED_COLUMNS = [
        'randomn_id',
        'order_number',
        'name',
        'product_image',
        'client_name',
        'email',
        'phone',
        'vin',
        'channel',
        'payment_status',
        'delivery_method',
        'delivery_status',
        'order_status',
        'quantity',
        'total_amount',
        'coupon_code',
        'discount_amount',
        'payment_reference',
        'payment_status_detail',
        'invoice_randomn_id',
        'livrare_randomn_id',
        'status',
        'notes',
    ];

    /**
     * Returnează toate comenzile.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $rows = AdvancedCRUD::selectnew(self::TABLE, '*', '', 'id DESC');
        return array_map([$this, 'normalizeDatabaseRow'], $rows);
    }

    /** @param array<string, mixed> $filters */
    public function findPaginated(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        $whereParts = [];
        $params = [];

        if (!empty($filters['order_status'])) {
            $whereParts[] = 'order_status = :order_status';
            $params[':order_status'] = (string) $filters['order_status'];
        }
        if (!empty($filters['channel'])) {
            $whereParts[] = 'channel = :channel';
            $params[':channel'] = (string) $filters['channel'];
        }
        if (!empty($filters['payment_status'])) {
            $whereParts[] = 'payment_status = :payment_status';
            $params[':payment_status'] = (string) $filters['payment_status'];
        }
        if (!empty($filters['payment_status'])) {
            $whereParts[] = 'payment_status = :payment_status';
            $params[':payment_status'] = (string) $filters['payment_status'];
        }
        if (!empty($filters['q'])) {
            $whereParts[] = '(client_name LIKE :q OR phone LIKE :q OR vin LIKE :q OR order_number LIKE :q OR name LIKE :q OR notes LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';
        $result = AdvancedCRUD::selectPaginated(self::TABLE, '*', $where, 'id DESC', $page, $perPage, $params);
        $result['items'] = array_map([$this, 'normalizeDatabaseRow'], $result['items']);

        return $result;
    }

    /**
     * Caută o comandă după randomn_id.
     *
     * @return array<string, mixed>|null
     */
    public function findByRandomId(int $randomId): ?array
    {
        $rows = AdvancedCRUD::selectnew(
            self::TABLE,
            '*',
            'WHERE ' . self::PRIMARY_LOGICAL_KEY . ' = :random_id',
            '',
            null,
            [':random_id' => $randomId]
        );

        return isset($rows[0]) ? $this->normalizeDatabaseRow($rows[0]) : null;
    }

    /**
     * Verifică existența unei comenzi.
     */
    public function existsByRandomId(int $randomId): bool
    {
        return $this->findByRandomId($randomId) !== null;
    }

    /**
     * Inserează o comandă.
     *
     * @param array<string, string|int|float|null> $payload
     */
    public function insert(array $payload): bool
    {
        return AdvancedCRUD::create(self::TABLE, $this->filterAllowedColumns($this->mapPayloadToDatabase($payload)));
    }

    /**
     * Inserează o comandă și returnează PK-ul (`comenzi.id`).
     *
     * @param array<string, string|int|float|null> $payload
     */
    public function insertAndGetId(array $payload): int
    {
        $data = $this->filterAllowedColumns($this->mapPayloadToDatabase($payload));
        if ($data === []) {
            return 0;
        }

        $pdo = \Config\Database::getDB();
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $sql = 'INSERT INTO ' . self::TABLE . ' (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);

        $params = [];
        foreach ($data as $column => $value) {
            $params[':' . $column] = $value;
        }

        $stmt->execute($params);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Actualizează o comandă după randomn_id.
     *
     * @param array<string, string|int|float|null> $payload
     */
    public function updateByRandomId(int $randomId, array $payload): bool
    {
        return AdvancedCRUD::update(
            self::TABLE,
            $this->filterAllowedColumns($this->mapPayloadToDatabase($payload)),
            'WHERE ' . self::PRIMARY_LOGICAL_KEY . ' = ' . (int) $randomId
        );
    }

    /**
     * Șterge o comandă după randomn_id.
     */
    public function deleteByRandomId(int $randomId): bool
    {
        return AdvancedCRUD::delete(
            self::TABLE,
            'WHERE ' . self::PRIMARY_LOGICAL_KEY . ' = ' . (int) $randomId
        );
    }

    /**
     * Statistici agregate pentru dashboard admin.
     *
     * @return array<string, int|float|string>
     */
    public function getDashboardStats(): array
    {
        $pdo = \Config\Database::getDB();

        $todayStmt = $pdo->query(
            "SELECT
                COUNT(*) AS today_new,
                COALESCE(SUM(total_amount), 0) AS today_revenue
             FROM comenzi
             WHERE DATE(created_at) = CURDATE()"
        );
        $today = $todayStmt ? ($todayStmt->fetch(\PDO::FETCH_ASSOC) ?: []) : [];

        $statusRows = $pdo->query(
            "SELECT order_status, COUNT(*) AS cnt
             FROM comenzi
             GROUP BY order_status"
        ) ?: [];
        $byStatus = [];
        foreach ($statusRows as $row) {
            $byStatus[(string) ($row['order_status'] ?? 'necunoscut')] = (int) ($row['cnt'] ?? 0);
        }

        $total = (int) $pdo->query('SELECT COUNT(*) FROM comenzi')->fetchColumn();
        $newTotal = (int) ($byStatus['noua'] ?? 0);

        return [
            'total' => $total,
            'today_new' => (int) ($today['today_new'] ?? 0),
            'today_revenue' => round((float) ($today['today_revenue'] ?? 0), 2),
            'new_orders' => $newTotal,
            'by_status' => $byStatus,
        ];
    }

    /**
     * Ultimele comenzi pentru activitate dashboard.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findRecent(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $rows = AdvancedCRUD::selectnew(self::TABLE, '*', '', 'id DESC', (string) $limit);

        return array_map([$this, 'normalizeDatabaseRow'], $rows);
    }

    /**
     * Actualizează statusul operațional.
     */
    public function updateOrderStatusByRandomId(int $randomId, string $orderStatus): bool
    {
        return $this->updateByRandomId($randomId, ['order_status' => $orderStatus]);
    }

    /**
     * Mapează payload-ul UI pe coloanele reale.
     *
     * @param array<string, string|int|float|null> $payload
     * @return array<string, string|int|float|null>
     */
    private function mapPayloadToDatabase(array $payload): array
    {
        if (isset($payload['product_name'])) {
            $payload['name'] = $payload['product_name'];
            unset($payload['product_name']);
        }

        if (isset($payload['status'])) {
            $payload['status'] = ((string) $payload['status'] === 'activ') ? 1 : 0;
        }

        return $payload;
    }

    /**
     * Normalizează rândul DB pentru UI.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeDatabaseRow(array $row): array
    {
        $row['product_name'] = $row['name'] ?? '';
        $row['status'] = ((int) ($row['status'] ?? 1)) === 1 ? 'activ' : 'inactiv';

        return $row;
    }

    /**
     * Elimină coloanele necunoscute.
     *
     * @param array<string, string|int|float|null> $payload
     * @return array<string, string|int|float|null>
     */
    private function filterAllowedColumns(array $payload): array
    {
        return array_intersect_key($payload, array_flip(self::ALLOWED_COLUMNS));
    }
}
