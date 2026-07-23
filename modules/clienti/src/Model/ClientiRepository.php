<?php
declare(strict_types=1);

namespace Besoiu\Modules\Clienti\Model;

use Besoiu\Core\AdvancedCRUD;

/**
 * Acces la tabela `clienti` (modul plug-in).
 */
final class ClientiRepository
{
    private const TABLE = 'clienti';
    private const PRIMARY_LOGICAL_KEY = 'randomn_id';

    private const ALLOWED_COLUMNS = [
        'randomn_id',
        'name',
        'email',
        'phone',
        'city',
        'address',
        'status',
        'total_orders',
        'total_paid',
        'preferred_courier',
        'notes',
    ];

    /**
     * @param array<string, mixed> $filters
     * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int}
     */
    public function findPaginated(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        $whereParts = [];
        $params = [];

        if (!empty($filters['q'])) {
            $whereParts[] = '(name LIKE :q OR email LIKE :q OR phone LIKE :q OR city LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';
        $result = AdvancedCRUD::selectPaginated(self::TABLE, '*', $where, 'id DESC', $page, $perPage, $params);
        $result['items'] = array_map([$this, 'normalizeDatabaseRow'], $result['items']);

        return $result;
    }

    /** @return array<string, string|null>|null */
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
        return AdvancedCRUD::delete(
            self::TABLE,
            'WHERE ' . self::PRIMARY_LOGICAL_KEY . ' = ' . (int) $randomId
        );
    }

    public function updateStatusByRandomId(int $randomId, string $status): bool
    {
        return $this->updateByRandomId($randomId, ['status' => $status]);
    }

    /** @param array<string, string|int|float|null> $payload */
    private function mapPayloadToDatabase(array $payload): array
    {
        if (isset($payload['client_name'])) {
            $payload['name'] = $payload['client_name'];
            unset($payload['client_name']);
        }

        if (isset($payload['status'])) {
            $payload['status'] = $this->mapStatusToDatabase((string) $payload['status']);
        }

        return $payload;
    }

    /** @param array<string, mixed> $row */
    private function normalizeDatabaseRow(array $row): array
    {
        $row['client_name'] = $row['name'] ?? '';
        $row['status'] = $this->mapStatusFromDatabase($row['status'] ?? null);

        return $row;
    }

    private function mapStatusToDatabase(string $status): int
    {
        return in_array($status, ['activ', 'vip', 'nou'], true) ? 1 : 0;
    }

    /** @param mixed $status */
    private function mapStatusFromDatabase($status): string
    {
        return ((int) $status) === 1 ? 'activ' : 'inactiv';
    }

    /** @param array<string, string|int|float|null> $payload */
    private function filterAllowedColumns(array $payload): array
    {
        return array_intersect_key($payload, array_flip(self::ALLOWED_COLUMNS));
    }
}
