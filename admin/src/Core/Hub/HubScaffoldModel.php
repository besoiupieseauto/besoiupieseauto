<?php

declare(strict_types=1);

namespace Evasystem\Core\Hub;

use Evasystem\Core\AdvancedCRUD;

/**
 * Model generic pentru tabele hub scaffold (name, email, phone, status).
 */
final class HubScaffoldModel
{
    private const ALLOWED_COLUMNS = ['name', 'email', 'phone', 'status'];

    private string $table;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    /** @return array<int, array<string, mixed>> */
    public function findAll(): array
    {
        return AdvancedCRUD::selectnew($this->table, '*', '', 'id DESC');
    }

    /** @param array<string, mixed> $filters @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function findPaginated(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        $whereParts = [];
        $params = [];

        if (!empty($filters['q'])) {
            $whereParts[] = '(name LIKE :q OR email LIKE :q OR phone LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        return AdvancedCRUD::selectPaginated($this->table, '*', $where, 'id DESC', $page, $perPage, $params);
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $rows = AdvancedCRUD::selectnew(
            $this->table,
            '*',
            'WHERE id = :id',
            '',
            null,
            [':id' => $id]
        );

        return $rows[0] ?? null;
    }

    public function existsById(int $id): bool
    {
        return $this->findById($id) !== null;
    }

    /** @param array<string, scalar|null> $payload */
    public function insert(array $payload): bool
    {
        return AdvancedCRUD::create($this->table, $this->filterColumns($payload));
    }

    /** @param array<string, scalar|null> $payload */
    public function updateById(int $id, array $payload): bool
    {
        return AdvancedCRUD::update(
            $this->table,
            $this->filterColumns($payload),
            'WHERE id = ' . $id
        );
    }

    public function deleteById(int $id): bool
    {
        return AdvancedCRUD::delete($this->table, 'WHERE id = ' . $id);
    }

    /** @param array<string, scalar|null> $payload @return array<string, scalar|null> */
    private function filterColumns(array $payload): array
    {
        $filtered = array_intersect_key($payload, array_flip(self::ALLOWED_COLUMNS));

        if (isset($filtered['status'])) {
            $filtered['status'] = ((int) $filtered['status']) === 1 ? 1 : 0;
        }

        return $filtered;
    }
}
