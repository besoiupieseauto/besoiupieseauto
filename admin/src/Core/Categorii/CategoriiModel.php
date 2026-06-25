<?php

declare(strict_types=1);

namespace Evasystem\Core\Categorii;

use Evasystem\Core\AdvancedCRUD;

final class CategoriiModel
{
    private const TABLE = 'categorii';

    private const ALLOWED_COLUMNS = [
        'slug',
        'label',
        'icon',
        'parent_id',
        'sort_order',
        'is_active',
        'type',
        'tecdoc_id',
        'meta',
    ];

    public function findAll(): array
    {
        return AdvancedCRUD::selectnew(self::TABLE, '*', '', 'sort_order ASC, id ASC');
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function findPaginated(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        $whereParts = [];
        $params = [];

        if (!empty($filters['type'])) {
            $whereParts[] = 'type = :type';
            $params[':type'] = (string) $filters['type'];
        }
        if (!empty($filters['q'])) {
            $whereParts[] = '(label LIKE :q OR slug LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        return AdvancedCRUD::selectPaginated(self::TABLE, '*', $where, 'sort_order ASC, id ASC', $page, $perPage, $params);
    }

    public function findActive(): array
    {
        return AdvancedCRUD::selectnew(
            self::TABLE,
            '*',
            'WHERE is_active = 1',
            'sort_order ASC, id ASC'
        );
    }

    public function findByType(string $type): array
    {
        return AdvancedCRUD::selectnew(
            self::TABLE,
            '*',
            'WHERE type = :type AND is_active = 1',
            'sort_order ASC, id ASC',
            null,
            [':type' => $type]
        );
    }

    public function findById(int $id): ?array
    {
        $rows = AdvancedCRUD::selectnew(
            self::TABLE,
            '*',
            'WHERE id = :id',
            '',
            null,
            [':id' => $id]
        );
        return $rows[0] ?? null;
    }

    public function findByParentId(?int $parentId): array
    {
        if ($parentId === null) {
            return AdvancedCRUD::selectnew(
                self::TABLE,
                '*',
                'WHERE parent_id IS NULL AND is_active = 1',
                'sort_order ASC'
            );
        }
        return AdvancedCRUD::selectnew(
            self::TABLE,
            '*',
            'WHERE parent_id = :pid AND is_active = 1',
            'sort_order ASC',
            null,
            [':pid' => $parentId]
        );
    }

    public function insert(array $payload): bool
    {
        return AdvancedCRUD::create(self::TABLE, $this->filterColumns($payload));
    }

    public function update(int $id, array $payload): bool
    {
        return AdvancedCRUD::update(
            self::TABLE,
            $this->filterColumns($payload),
            'WHERE id = ' . $id
        );
    }

    public function delete(int $id): bool
    {
        return AdvancedCRUD::delete(self::TABLE, 'WHERE id = ' . $id);
    }

    public function toggleActive(int $id, int $value): bool
    {
        return $this->update($id, ['is_active' => $value]);
    }

    private function filterColumns(array $payload): array
    {
        return array_intersect_key($payload, array_flip(self::ALLOWED_COLUMNS));
    }
}
