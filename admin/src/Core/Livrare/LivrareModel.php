<?php

declare(strict_types=1);

namespace Evasystem\Core\Livrare;

use Evasystem\Core\AdvancedCRUD;

/**
 * Acces la tabela `livrare`, folosind AdvancedCRUD existent.
 */
final class LivrareModel
{
    private const TABLE = 'livrare';
    private const PRIMARY_LOGICAL_KEY = 'randomn_id';

    private const ALLOWED_COLUMNS = [
        'randomn_id',
        'awb',
        'order_number',
        'order_id',
        'name',
        'client_name',
        'email',
        'phone',
        'address',
        'courier',
        'courier_provider',
        'service_type',
        'delivery_status',
        'delivery_date',
        'delivery_time',
        'total_amount',
        'notes',
        'courier_response',
        'status',
    ];

    /** @return array<int, array<string, mixed>> */
    public function findAll(): array
    {
        $rows = AdvancedCRUD::selectnew(self::TABLE, '*', '', 'id DESC');
        return array_map([$this, 'normalizeDatabaseRow'], $rows);
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function findPaginated(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        $whereParts = [];
        $params = [];
        if (!empty($filters['q'])) {
            $whereParts[] = '(awb LIKE :q OR order_number LIKE :q OR client_name LIKE :q OR name LIKE :q OR courier LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }
        if (!empty($filters['delivery_status'])) {
            $whereParts[] = 'delivery_status = :delivery_status';
            $params[':delivery_status'] = (string) $filters['delivery_status'];
        }
        if (!empty($filters['courier'])) {
            $whereParts[] = 'courier = :courier';
            $params[':courier'] = (string) $filters['courier'];
        }
        $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';
        $result = AdvancedCRUD::selectPaginated(self::TABLE, '*', $where, 'id DESC', $page, $perPage, $params);
        $result['items'] = array_map([$this, 'normalizeDatabaseRow'], $result['items']);
        return $result;
    }

    /** @return array<string, mixed>|null */
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

    public function existsByRandomId(int $randomId): bool
    {
        return $this->findByRandomId($randomId) !== null;
    }

    /** @return array<string, mixed>|null */
    public function findByOrderId(int $orderId): ?array
    {
        if ($orderId <= 0) {
            return null;
        }

        $rows = AdvancedCRUD::selectnew(
            self::TABLE,
            '*',
            'WHERE order_id = :order_id',
            'id DESC',
            '1',
            [':order_id' => $orderId]
        );

        return isset($rows[0]) ? $this->normalizeDatabaseRow($rows[0]) : null;
    }

    /**
     * @param array<int, int> $orderIds
     * @return array<int, array<string, mixed>>
     */
    public function findByOrderIds(array $orderIds): array
    {
        $orderIds = array_values(array_filter(array_map('intval', $orderIds), static fn (int $id): bool => $id > 0));
        if ($orderIds === []) {
            return [];
        }

        $pdo = \Config\Database::getDB();
        $placeholders = [];
        $params = [];
        foreach ($orderIds as $index => $orderId) {
            $key = ':oid_' . $index;
            $placeholders[] = $key;
            $params[$key] = $orderId;
        }

        $sql = 'SELECT * FROM `' . self::TABLE . '` WHERE order_id IN (' . implode(', ', $placeholders) . ') ORDER BY id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $mapped = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $orderId = (int) ($row['order_id'] ?? 0);
            if ($orderId > 0 && !isset($mapped[$orderId])) {
                $mapped[$orderId] = $this->normalizeDatabaseRow($row);
            }
        }

        return $mapped;
    }

    /** @param array<string, string|int|float|null> $payload */
    public function insert(array $payload): bool
    {
        return AdvancedCRUD::create(self::TABLE, $this->filterAllowedColumns($this->mapPayloadToDatabase($payload)));
    }

    /** @param array<string, string|int|float|null> $payload */
    public function updateByRandomId(int $randomId, array $payload): bool
    {
        return AdvancedCRUD::update(
            self::TABLE,
            $this->filterAllowedColumns($this->mapPayloadToDatabase($payload)),
            'WHERE ' . self::PRIMARY_LOGICAL_KEY . ' = ' . (int) $randomId
        );
    }

    public function deleteByRandomId(int $randomId): bool
    {
        return AdvancedCRUD::delete(self::TABLE, 'WHERE ' . self::PRIMARY_LOGICAL_KEY . ' = ' . (int) $randomId);
    }

    public function updateDeliveryStatusByRandomId(int $randomId, string $deliveryStatus): bool
    {
        return $this->updateByRandomId($randomId, ['delivery_status' => $deliveryStatus]);
    }

    /** @param array<string, string|int|float|null> $payload */
    private function mapPayloadToDatabase(array $payload): array
    {
        if (isset($payload['delivery_title'])) {
            $payload['name'] = $payload['delivery_title'];
            unset($payload['delivery_title']);
        }
        if (isset($payload['status'])) {
            $payload['status'] = ((string) $payload['status'] === 'activ') ? 1 : 0;
        }
        return $payload;
    }

    /** @param array<string, mixed> $row */
    private function normalizeDatabaseRow(array $row): array
    {
        $row['delivery_title'] = $row['name'] ?? '';
        $row['status'] = ((int) ($row['status'] ?? 1)) === 1 ? 'activ' : 'inactiv';
        return $row;
    }

    /** @param array<string, string|int|float|null> $payload */
    private function filterAllowedColumns(array $payload): array
    {
        return array_intersect_key($payload, array_flip(self::ALLOWED_COLUMNS));
    }
}
